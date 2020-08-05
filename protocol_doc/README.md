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
- [5. Response types](#5-response-types)
  - [5.1. Redirect - **^**](#51-redirect---)
  - [5.2. Query response - **&**](#52-query-response---)
    - [5.2.1. Data response - **&1**](#521-data-response---1)
    - [5.2.2. Modification results - **&2**](#522-modification-results---2)
    - [5.2.3. Stats only - **&3**](#523-stats-only---3)
    - [5.2.4. Transaction status - **&4**](#524-transaction-status---4)
    - [5.2.5. Prepared statement creation - **&5**](#525-prepared-statement-creation---5)
    - [5.2.6. Block response - **&6**](#526-block-response---6)
  - [5.3. Table header - **%**](#53-table-header---)
  - [5.4. Error - **!**](#54-error---)
  - [5.5. Tuple - **&#91;**](#55-tuple---)
  - [5.6. Empty message (prompt)](#56-empty-message-prompt)
- [6. SQL queries](#6-sql-queries)
  - [6.1. Escaping](#61-escaping)
  - [6.2. The tabular format of the data response](#62-the-tabular-format-of-the-data-response)
  - [6.3. Pagination](#63-pagination)
  - [6.4. Multiple queries in a single message](#64-multiple-queries-in-a-single-message)
- [7. Prepared statements](#7-prepared-statements)
- [8. Channels, sessions and error handling](#8-channels-sessions-and-error-handling)

# 1. Overview

MonetDB has a main process, which usually listens on port 50000 for
incoming connections. Each database runs on separate processes, spawned
by the main. The client application connects only to the main process
directly, which acts as a proxy and transfers the data packages in both
directions between the client and the database it is connected to.

<img src="png/01_overview.png" alt="drawing" width="640"/>

# 2. Messages and packets

The client communicates with the server by sending and receiving UTF-8
encoded text messages of one or multiple lines. The EOL character is
`\n` (line feed).

When a connection starts, the first message is sent by the server (it
contains the `server challenge`, which is required for the authentication),
and afterwards the client can always expect a response for its messages to
the server. (Although the response can be an empty message.)

<img src="png/02_a_messages.png" alt="drawing" width="640"/>

These text messages are transferred in packets. The maximal size of a
packet is 8190 bytes. It is not guaranteed that a packet contains a
proper UTF-8 encoded text, because it's possible that a multi-byte
character is cut in half at the end of its payload.

<img src="png/02_b_packets.png" alt="drawing" width="640"/>

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
| LIT | ?? |
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

<img src="png/03_a_hash.png" alt="drawing" width="640"/>

Where the hash functions output hexadecimal values. After the client calculated the hash,
it sends it in a message like the following:

    BIG:monetdb:{SHA1}b8cb82cca07f379e25e99262e3b4b70054546136:sql:myDatabase:

Which is also separated by colons and the meanings of the values are:

| Value | Description |
| --- | --- |
| BIG | ?? |
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
process. It happens in the existing TCP connection. No new connections are created. This repetition is
required because the client has to authenticate at all the processes it is proxied through and also at the
destination database process.

In practice this means usually only two authentications:

<img src="png/03_b_merovingian.png" alt="drawing" width="640"/>

Therefore the flow drawn in paragraph [Messages and packets](#2-messages-and-packets)
is not fully realistic, because at the repetition of the authentication process
the client reads twice.

<img src="png/03_c_flow.png" alt="drawing" width="640"/>

If the redirect happens more than 10 times, then throw an error in the client application,
because this shows an error on the server side.

# 4. Commands and queries in a nutshell

After a successful authentication, the client can start to send requests to the server
and read the responses. There are 2 main types of requests: Commands and queries.

- **Commands**: They always start with an upper-case `X`. Can be used to configure properties
    of the current session, or to request the next page of a table response. Examples:<br><br>
    Set the `reply_size` to 200 (See chapter [Pagination](#63-pagination)):

        Xreply_size 200

    Request the rows 400-599 from the query with ID 2 (See chapter [Pagination](#63-pagination)):

        Xexport 2 400 200

- **SQL Queries**: They always start with a lower-case `s` and must end with a `;` colon.
    With SQL queries you can either create, update, modify or delete data, modify the
    database schema, etc. or you can also set session properties, like the time zone.<br><br>
    Configure automatic conversion for date-time values in the current session:

        sSET TIME ZONE INTERVAL '+02:00' HOUR TO MINUTE;
    
    Select the default schema:

        sSET SCHEMA mySchema;

    Query the contents of a table:

        sSELECT *
        FROM myTable;

    Notice that the query can contain EOL characters.

# 5. Response types

Responses are messages sent from the server to the client, that answer requests
previously sent by the client. Different kinds of requests trigger different
kinds of responses. The type of a response can be identified by their first
character.

| First character | Name | Description |
| --- | --- | --- |
| **^** (caret) | Redirect | Used during authentication only, to indicate a [Merovingian redirect](#32-the-merovingian-redirect). |
| **&** (ampersand) | Query response | A response to an SQL query or to an export command. |
| **%** (percent) | Table header | When tabular data is returned (mostly for a select query), then there are 4 header lines which come before the tuples and tell information about the columns. |
| **!** (exclamation  mark) | Error | The response is an error message. |
| **[** (bracket) | Tuple | Contains tuples, a row of a tabular data set. |

In addition to these, a valid response is also the `empty message`. See chapter
[Messages and packets](#2-messages-and-packets) for more information.

Each message type can return different kinds of information in different formats.
The next chapters will discuss these formats in detail.

## 5.1. Redirect - **^**

This response has been discussed already in chapter [The Merovingian redirect](#32-the-merovingian-redirect).
Redirect messages always start with the `^` (caret) character. An example response:

    ^mapi:merovingian://proxy?database=myDatabase

?? Investigate if there are different kinds of redirects.

## 5.2. Query response - **&**

A response to an SQL query or to an export command. This type has multiple sub-types.
While the ampersand (&) character is the first, it is followed by a number from
1 to 6, which tells the sub-type.

### 5.2.1. Data response - **&1**

This is a response for a select query. For example let's see the response for query:
(Don't forget that all queries have to start with an `s` character and end with a colon `;`.)

    sselect
        "category",
        round(sys.stddev_samp("weight_kg"), 2) as "weight_stddev",
        round(sys.median("weight_kg"), 2) as "weight_median",
        round(avg("weight_kg"), 2) as "weight_mean"
    from
        "cats"
    group by
        "category";

The first row of the response tells with the `&1` beginning that this is a data response to a query.
The `&1` is followed by a list of space-separated values, which will be discussed in detail below.
After the first line come 4 header lines and then the data rows.

<img src="png/05_a_data_response.png" alt="drawing" width="640"/>

The first line contains 9 fields:

| Index | Sample value | Description |
| --- | --- | --- |
| 0 | &1 | Identifies the response type. (data response to a query) |
| 1 | 0 | Query ID. Can be used later to reference the query in the same session. For example in an `export` command. |
| 2 | 3 | Number of rows in the full response. This includes those which didn't fit into this message. |
| 3 | 4 | ?? |
| 4 | 3 | ?? |
| 5 | 2107 | Execution time in microseconds. (??) |
| 6 | 246 | Query parsing time in microseconds. (??) |
| 7 | 143 | ?? |
| 8 | 19 | ?? |

The 4 header lines describe the columns of the response. Each line ends with the name of the header
which helps to avoid confusion, although their order is always the same.

| Order | Header name | Description |
| --- | --- | --- |
| 1 | table_name | If the value is from a reference to a table's field, then this contains the name of the table. Otherwise if the value is a result of an expression, then it contains the name of a temporary resource. |
| 2 | name | The name of the column. |
| 3 | type | The SQL type of the column. |
| 4 | length | This length value can help displaying the table in a console window. (Fixed-length character display) |

Since the string values in the tuples contain escaped values (like `"\t"`), you can freely split or scan through the rows
by looking for tabulator characters or for their combinations with the commas.

### 5.2.2. Modification results - **&2**

Reponse for `INSERT` or `UPDATE` queries. Example:

    &2 15 -1 2113 439 1596 234

It is a single line, without additional lines, composed of 7 space-separated values:

| Index | Sample value | Description |
| --- | --- | --- |
| 0 | &2 | Identifies the response type. (Data modification result) |
| 1 | 15 | Number of inserted or affected rows. |
| 2 | -1 | ?? |
| 3 | 2113 | Execution time in microseconds (??) |
| 4 | 439 | Query parsing time in microseconds (??) |
| 5 | 1596 | ?? |
| 6 | 234 | ?? |

### 5.2.3. Stats only - **&3**

This response is usually returned when a table or a schema is created,
and for statements like `SET TIME ZONE` or `SET SCHEMA`. Example response:

    &3 733 79

A sinlge line of 3 space-separated values:

| Index | Sample value | Description |
| --- | --- | --- |
| 0 | &3 | Identifies the response type. (Stats only) |
| 1 | 733 | Execution time in microseconds (??) |
| 2 | 79 | Query parsing time in microseconds (??) |

### 5.2.4. Transaction status - **&4**

Returned after SQL statements that deal with transactions, like:
`START TRANSACTION`, `COMMIT`, `ROLLBACK`. It tells whether the current session
is now in auto-commit state or not. Example response:

    &4 f

A sinlge line of 2 space-separated values:

| Index | Sample value | Description |
| --- | --- | --- |
| 0 | &4 | Identifies the response type. (Transaction status) |
| 1 | f | Boolean value. `f` = auto-commit mode is disabled (a transaction is started). `t` = auto-commit mode is enabled, there's no active transaction. |

### 5.2.5. Prepared statement creation - **&5**

This response is returned for an SQL query which creates a prepared statement. Example query:

    sPREPARE select * from cats where weight_kg > ?;

An example reponse:

    &5 15 5 6 5

The response consists of 5 space-separated values:

| Index | Sample value | Description |
| --- | --- | --- |
| 0 | &5 | Identifies the response type. (Prepared statement creation) |
| 1 | 15 | The ID of the created prepared statement. This can be used in an `EXECUTE` statement. |
| 2 | 5 | ?? |
| 3 | 6 | ?? |
| 4 | 5 | ?? |

### 5.2.6. Block response - **&6**

Returned for an `EXPORT` command. See chapter [Pagination](#63-pagination) for more information.
It's similar to the [Data response](#521-data-response---1), but there are no header lines,
only the tuples. Example response:

    &6 2 11 200 600
    [ ...,\t...,\t...,\t...\t]
    [ ...,\t...,\t...,\t...\t]
    ...

The first line of the response consists of 5 space-separated values:

| Index | Sample value | Description |
| --- | --- | --- |
| 0 | &6 | Identifies the response type. (Block response) |
| 1 | 2 | Query ID. This ID was referenced in the export command too. |
| 2 | 11 | ?? |
| 3 | 200 | Number of rows in this current response. (not total) |
| 4 | 600 | The offset (index) of the first row in the response. |

Fields 3 and 4 are actually the two parameters of the export command.

## 5.3. Table header - **%**

A line that contains information about the columns of a tabular data response.
Discussed in chapter [Data response](#521-data-response---1).

## 5.4. Error - **!**

Error responses start with an exclamation mark `!`, followed by an error code, then a text
message after a second exclamation mark. When the server returns an error message,
then it clears the complete session state (forgets everything). See section
[Channels, sessions and error handling](#8-channels-sessions-and-error-handling)
for more information.

Examples:

    !42S02!SELECT: no such table 'notexists'

    !42000!syntax error, unexpected IDENT in: "

## 5.5. Tuple - **&#91;**

A line that contains tabular data. Discussed in chapter [Data response](#521-data-response---1).

## 5.6. Empty message (prompt)

It is returned only for a successful authentication request. See chapter: [Authentication](#3-authentication)

An empty message consists only of the 2-byte header, containing the value: 0x0001

# 6. SQL queries

As it was mentioned already in section [Commands and queries in a nutshell](#4-commands-and-queries-in-a-nutshell),


## 6.1. Escaping

## 6.2. The tabular format of the data response

## 6.3. Pagination

## 6.4. Multiple queries in a single message

# 7. Prepared statements

# 8. Channels, sessions and error handling
