MonetDB-PHP-Deux
================

A PHP client library for accessing MonetDB.

# Examples

```php
$connection = new Connection("127.0.0.1", 50000,
    "monetdb", "monetdb", "myDatabase");

$result = $connection->Query(<<<EOF
    select
        "name"
    from
        "cats"
    where
        "weight" > 35
EOF
);

foreach($result as $cat) {
    echo $cat["name"]."\n";
}

$stats = $result->GetStatusRecords()[0];

echo "Execution time: {$stats->GetExecutionTime()} ms\n";

```

<hr><br>

# API Reference

<!-- API DOC -->

| Class | Summary |
| --- | --- |
| [Connection](##connection-class) | Class for encapsulating a connection to a MonetDB server. |
| [Response](##response-class) | This class represents a response for an SQL query or for a command. In case of a 'select' query, this class can be iterated through, using a 'foreach' loop.  The records are returned as associative arrays, indexed by the column names. |
| [StatusRecord](##statusrecord-class) | This class shares the information returned by MonetDB about the executed queries. Like execution time, number of rows affected, etc. Note that only specific fields are populated for specific queries, the others remain NULL. |

<hr><br>

## Connection Class

<em>Class for encapsulating a connection to a MonetDB server.</em>

| Method | Documentation |
| --- | --- |
| <strong>__construct</strong> | Create a new connection to a MonetDB database. <br><br><strong>@param</strong> <em>string</em> <strong>$host</strong> : The host of the database. Use '127.0.0.1' if the DB is on the same machine.<br><strong>@param</strong> <em>int</em> <strong>$port</strong> : The port of the database. For MonetDB this is usually 50000.<br><strong>@param</strong> <em>string</em> <strong>$user</strong> : The user name.<br><strong>@param</strong> <em>string</em> <strong>$password</strong> : The password of the user.<br><strong>@param</strong> <em>string</em> <strong>$database</strong> : The name of the datebase to connect. Don't forget to release and start it.<br><strong>@param</strong> <em>string</em> <strong>$saltedHashAlgo</strong> <em>= "SHA1"</em> : Optional. The preferred hash algorithm to be used for exchanging the password. It has to be supported by both the server and PHP. This is only used for the salted hashing. Another stronger algorithm is used first (usually SHA512).<br><strong>@param</strong> <em>bool</em> <strong>$syncTimeZone</strong> <em>= true</em> : If true, then tells the clients time zone offset to the server, which will convert all timestamps is case there's a difference. If false, then the timestamps will end up on the server unmodified.<br><strong>@param</strong> <em>?int</em> <strong>$maxReplySize</strong> <em>= 1000000</em> : The maximal number of tuples returned in a response. Set it to NULL to avoid configuring the server, but that might have a default for it. |
| <strong>Close</strong> | Close the connection |
| <strong>Query</strong> | Execute an SQL query and return its response. For 'select' queries the response can be iterated using a 'foreach' statement. You can pass an array as second parameter to execute the query as prepared-statement, where the array contains the parameter values. SECURITY NOTICE: For prepared statements in MonetDB, the parameter values are passed in a regular 'EXECUTE' command, using escaping. Therefore the same security considerations apply here as for using the Connection->Escape(...) method. Please read the comments for that method. <br><br><strong>@param</strong> <em>string</em> <strong>$sql</strong><br><strong>@param</strong> <em>array</em> <strong>$params</strong> <em>= null</em> : An optional array for prepared-statement parameters. If not provided (or null), then a normal query is executed, instead of a prepared-statement. The parameter values will retain their PHP type if possible. The following values won't be converted to string: null, true, false and numeric values.<br><strong>@return</strong> <em>Response</em> |
| <strong>QueryFirst</strong> | Execute an SQL query and return only the first row as an associative array. If there is more data on the stream, then discard all. Returns null if the query has empty result. You can pass an array as second parameter to execute the query as prepared-statement, where the array contains the parameter values. <br><br><strong>@param</strong> <em>string</em> <strong>$sql</strong><br><strong>@param</strong> <em>array</em> <strong>$params</strong> <em>= null</em> : An optional array for prepared-statement parameters. If not provided (or null), then a normal query is executed, instead of a prepared-statement. See the 'Query' method for more information about the parameter values.<br><strong>@return</strong> <em>string[] -or- null</em> |
| <strong>Command</strong> | Send a 'command' to MonetDB. Commands are used for configuring the database, for example setting the maximal response size.<br><br><strong>@param</strong> <em>string</em> <strong>$command</strong><br><strong>@return</strong> <em>Response</em> |
| <strong>Escape</strong> | Escape a string value, to be inserted into a query, inside single quotes. SECURITY WARNING: This library forces the use of multi-byte support and UTF-8 encoding, which is also used by MonetDB, avoiding the SQL-insertion attacks, which play with conversions between character encodings. This function uses the 'addcslashes' function of PHP, which is based on C-style escaping, like MonetDB. But even with these two precautionary measures, full security cannot be guaranteed. It's better if one never trusts security on MonetDB. Use it only for data analysis, but don't use it for authentication or session management, etc. Non-authenticated users should never have the opportunity to execute parameterized queries with it, and never run the server as root.<br><br><strong>@param</strong> <em>string</em> <strong>$value</strong><br><strong>@return</strong> <em>string</em> |
| <strong>ClearPsCache</strong> | Clears the in-memory cache of prepared statements. This is called automatically when an error is received from MonetDB, because that also purges the prepared statements and all session state in this case. |

<hr><br>

## Response Class

<em>This class represents a response for an SQL query or for a command. In case of a 'select' query, this class can be iterated through, using a 'foreach' loop.  The records are returned as associative arrays, indexed by the column names.</em>

| Method | Documentation |
| --- | --- |
| <strong>Discard</strong> | Read through all of the data and discard it. Use this method when you don't want to iterate through a long query, but you would like to start a new one instead. |
| <strong>IsDiscarded</strong> | Returns true if this response is no longer connected to an input TCP stream.<br><br><strong>@return</strong> <em>boolean</em> |
| <strong>GetColumnNames</strong> | Returns the names of columns for the table.<br><br><strong>@return</strong> <em>string[]</em> |
| <strong>Fetch</strong> | Returns the next row as an associative array, or null if the query ended.<br><br><strong>@return</strong> <em>array -or- null</em> |
| <strong>GetStatusRecords</strong> | Returns one or more Status records that tell information about the queries executed through a single request.<br><br><strong>@return</strong> <em>StatusRecord[]</em> |

<hr><br>

## StatusRecord Class

<em>This class shares the information returned by MonetDB about the executed queries. Like execution time, number of rows affected, etc. Note that only specific fields are populated for specific queries, the others remain NULL.</em>

| Method | Documentation |
| --- | --- |
| <strong>GetQueryType</strong> | Returns a short string, which identifies the type of the query.<br><br><strong>@return</strong> <em>string</em> |
| <strong>GetDescription</strong> | Returns a user-friendly text, which describes the effect of the query.<br><br><strong>@return</strong> <em>string</em> |
| <strong>GetExecutionTime</strong> | The time the server spent on executing the query. In milliseconds.<br><br><strong>@return</strong> <em>float -or- null</em> |
| <strong>GetQueryParsingTime</strong> | The time it took to parse and optimize the query.<br><br><strong>@return</strong> <em>float -or- null</em> |
| <strong>GetAffectedRows</strong> | The number of rows updated or inserted.<br><br><strong>@return</strong> <em>integer -or- null</em> |
| <strong>GetRowCount</strong> | The number of rows in the response.<br><br><strong>@return</strong> <em>integer -or- null</em> |
| <strong>GetAsText</strong> | Get a description of the status response in a human-readable format.<br><br><strong>@return</strong> <em>string</em> |
| <strong>GetPreparedStatementID</strong> | Get the ID of a created prepared statement. This ID can be used in an 'EXECUTE' statement, but only in the same session.<br><br><strong>@return</strong> <em>integer -or- null</em> |

<hr><br>

<!-- API DOC -->

# Development setup through the Docker image

- Build the Docker image:

        docker build --tag monetdb-php ./

- Create the Docker container and the host user inside it:

        docker run -d -h monetdb-php --restart unless-stopped -p 9292:80\
            -v ${PWD}:/var/MonetDB-PHP-Deux --name monetdb-php monetdb-php
        
        docker exec --user root -it monetdb-php sh -c \
            "useradd -m -s /bin/bash -u $(id -u) $(whoami)\
            && usermod -a -G monetdb $(whoami)"

- Login into the container as the host user, create a database and log into it:

        docker exec --user $(whoami) -it monetdb-php /bin/bash
        monetdb create myDatabase
        monetdb release myDatabase
        monetdb start myDatabase
        mclient -d myDatabase

- When you don't need the MonetDB-PHP-Deux container anymore, you can get rid of it easily: (this also removes the unused images)

        docker stop monetdb-php\
        && docker image rm monetdb-php --force\
        && docker system prune --force
