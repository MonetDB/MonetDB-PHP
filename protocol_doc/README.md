MonetDB client-server protocol
==============================

*(By Tamas Bolner, 2020-07)*

This document aims to summarize the key points of the TCP/IP-based
client-server protocol between the MonetDB server and the clients
connecting to it. The goal is to provide information for the
development of future client applications.

# Table of contents

- [MonetDB client-server protocol](#monetdb-client-server-protocol)
- [Table of contents](#table-of-contents)
- [1. Overview](#1-overview)
- [2. Messages and packets](#2-messages-and-packets)
- [3. Authentication](#3-authentication)
  - [3.1. Possible responses to an authentication request](#31-possible-responses-to-an-authentication-request)
  - [3.2. The Merovingian redirect](#32-the-merovingian-redirect)
- [4. Commands and queries in a nutshell](#4-commands-and-queries-in-a-nutshell)
- [5. Message types](#5-message-types)
- [6. SQL queries](#6-sql-queries)
  - [6.1. Escaping](#61-escaping)
  - [6.2. Table format](#62-table-format)
  - [6.3. Pagination](#63-pagination)
- [7. Prepared statements](#7-prepared-statements)
- [8. Channels, sessions and error handling](#8-channels-sessions-and-error-handling)

# 1. Overview

MonetDB has a main process, which usually listens on port 50000 for
incoming connections. Each database runs on separate processes, spawned
by the main. The client application connects only to the main process
directly, which acts as a proxy and transfers the data packages in both
directions between the client and the database it is connected to.

<img src="png/01_overview.png" alt="drawing" width="512"/>

# 2. Messages and packets

The client communicates with the server by sending and receiving UTF-8
encoded text messages of one or multiple lines. The EOL character is
`\n` (line feed).

When a connection starts, the first message is sent by the server (it
contains the `server challenge`, which is required for the authentication),
and afterwards the client can always expect a response for its messages to
the server. (Although the response can be an empty message.)

<img src="png/02_a_messages.png" alt="drawing" width="512"/>

These text messages are transferred in packets. The maximal size of a
packet is 8190 bytes. It is not guaranteed that a packet contains a
proper UTF-8 encoded text, because it's possible that a multi-byte
character is cut in half at the end of its payload.

<img src="png/02_b_packets.png" alt="drawing" width="512"/>

Therefore the simplest way to parse a message is to concatenate the
payloads of the packets first. You can indirectly limit the size
of the messages received from the server, using the `reply_size` command,
which tells the server the maximal number of database rows, that can be
returned in a message. (= pagination)

Every packet starts with a 16 bit (2 byte) integer, called header. The LSB
of the header is only 1 for the last packet in the message, and 0 for
all the others. You can get the number of bytes in the payload by shifting
the header by 1 bit to the right (to remove the LSB).

    header: int16

    is_last = header & 1
    byte_count = header >> 1

The header for an empty message contains 0x0001, because:

    (0x0000 << 1) | 0x0001 = 0x0001

If the message contains 4321 bytes, then there's a single packet,
which has the header:

    (0x10E1 << 1) | 0x0001 = 0x21C3

If the message contains 12345 bytes, then there are two packages. The first
contains 8190 bytes, while the second the remaining 4155. The `is_last` bit
is only set for the second:

    (0x1FFE << 1) | 0x0000 = 0x3FFC
    (0x103B << 1) | 0x0001 = 0x2077

As stated before, the client reads first at the beginning of the connection,
then after every message it sent. When reading packets, the client should
first read the 2 bytes of the header, then determine the byte count, and
read the exact number of bytes (if not 0, as empty messages are valid). The
only exception to this flow is when a `Merovingian redirect` happens during
authentication. At that time, the client has to read twice.

# 3. Authentication

The first message is sent by the server and it is called `server challenge`.
An example:

    bDRlm4zbfhxAI23:merovingian:9:PROT10,RIPEMD160,SHA512,SHA384,SHA256,SHA224,SHA1:LIT:SHA512:

The `:` (colon) characters are delimiters for 6 fields:

| Value | Description |
| --- | --- |
| bDRlm4zbfhxAI23 | Random string to be used as salt for the hashing of the password. |
| merovingian | Type of the endpoint. `merovingian` is the main process, `mserver` is a specific database process. |
| 9 | Protocol version |
| PROT10, RIPEMD160, SHA512, SHA384, SHA256, SHA224, SHA1 | Comma-separated list of accepted algorithms for the salted hashing. |
| LIT | ? |
| SHA512 | The accepted algorithm for the password hashing. |

In a nutshell, the client sends a hash of the user password to the server, and that responds
whether it is correct or not for a specific user.

The password is hashed twice. First by the (usually stronger) `password hash`, and then by
the `salted hash`. The algorithm used for the salted hashing can be chosen by the client
from the comma separated list offered in the server challenge. It is recommended not to use
the same algorithm for both, because if a vulnerability is found for one of them, then
the other still can provide protection.

If the client selected SHA1 for the salted hashing and the server offered SHA512 for the
password hashing, then the formula for getting the hash string is the following:

<img src="png/03_a_hash.png" alt="drawing" width="512"/>

Where the hash functions output hexadecimal values. After the client calculated the hash,
it sends it in a message like the following:

    BIG:monetdb:{SHA1}b8cb82cca07f379e25e99262e3b4b70054546136:sql:myDatabase:

Which is also separated by colons and the meanings of the values are:

| Value | Description |
| --- | --- |
| BIG | ? |
| monetdb | User name |
| {SHA1} | The name of the salted hash algorithm chosen by the client. It has to be uppercase and inside curly brackets. |
| b8cb82cca07f379e25e99262e3b4b70054546136 | The hash of the password, generated by the above formula. |
| sql | The requested query language. |
| myDatabase | The name of the database. |

After the client sent the authentication message, it must read a message from the server.
The next paragraph enumerates the valid responses of the server.

## 3.1. Possible responses to an authentication request

After the client has sent the hashed password to the server, it can receive 3 kinds of responses.

- An empty response (called "prompt") if the authentication was successful. See section [Messages and packets](#2-messages-and-packets) about the empty message.
- An error message if the authentication failed. Example:

        !InvalidCredentialsException:checkCredentials:invalid credentials for user 'monetdb'

    Detection: the response starts with the `"!InvalidCredentialsException:"` string.
    See section [Channels, sessions and error handling](#8-channels-sessions-and-error-handling)
    for more information on how to detect and handle error responses from the server.

- A message requesting a Merovingian redirect. See the next paragraph about this case. Example:

        ^mapi:merovingian://proxy?database=myDatabase
    
    Detection: the response starts with the `"^mapi:merovingian:"` string.

## 3.2. The Merovingian redirect

The `Merovingian redirect` is not an actual redirect, but a request for the repetition of the authentication
process. This repetition is required because the client has to authenticate at all the processes
it is proxied through and also at the destination database process.

In practice this means usually only two authentications:

<img src="png/03_b_merovingian.png" alt="drawing" width="512"/>

This means that the flow drawn in paragraph [Messages and packets](#2-messages-and-packets)
is not fully realistic, because at the repetition of the authentication process
the client reads twice.

<img src="png/03_c_flow.png" alt="drawing" width="512"/>

If the redirect happens more than 10 times, then throw an error, because this means
an error on the server side.

# 4. Commands and queries in a nutshell

# 5. Message types

# 6. SQL queries

## 6.1. Escaping

## 6.2. Table format

## 6.3. Pagination

# 7. Prepared statements

# 8. Channels, sessions and error handling
