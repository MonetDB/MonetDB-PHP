MonetDB-PHP-Deux
================

A PHP client library for accessing MonetDB.

Main features:
- Parameterized queries, using cached prepared statements.
- Extensively tested with Japanese characters for the UTF-8 compliance.
- Multiple, concurrent connections.
- Allows access to response stats, like execution time and affected row count, etc.
- The thrown `MonetException` exception objects contain user-friendly error messages.
- Provides information about the columns of the response data, like name, SQL type and length.

# Table of contents

- [MonetDB-PHP-Deux](#monetdb-php-deux)
- [Table of contents](#table-of-contents)
- [Installation](#installation)
- [Examples](#examples)
  - [Example 1: Simple query](#example-1-simple-query)
  - [Example 2: Get execution stats](#example-2-get-execution-stats)
  - [Example 3: Parameterized query with prepared statement](#example-3-parameterized-query-with-prepared-statement)
  - [Example 4: Using escaping](#example-4-using-escaping)
  - [Example 5: Renaming fields, using column info](#example-5-renaming-fields-using-column-info)
  - [Example 6: Query the first record only](#example-6-query-the-first-record-only)
  - [Example 7: Transactions](#example-7-transactions)
  - [Example 8: Importing data the fastest way](#example-8-importing-data-the-fastest-way)
  - [Example 9: Using multiple connections](#example-9-using-multiple-connections)
- [API Reference](#api-reference)
  - [Connection Class](#connection-class)
  - [Response Class](#response-class)
  - [StatusRecord Class](#statusrecord-class)
  - [ColumnInfo Class](#columninfo-class)
- [Development setup through the Docker image](#development-setup-through-the-docker-image)

# Installation

This library is available on Packagist at:
- https://packagist.org/packages/tbolner/monetdb-php-deux

First install [Composer](https://getcomposer.org/download/), then execute the following in your project's directory:

```
composer require tbolner/monetdb-php-deux
```

Or add the following line to your `composer.json` file's `require` section:

```
"tbolner/monetdb-php-deux": "^1.0"
```

# Examples

Example projects:

- [Data modification](Examples/DataModification/)
- [Web query](Examples/WebQuery/)
- [Japanese test](Examples/JapaneseTest/)

## Example 1: Simple query

```php
use MonetDB\Connection;

$connection = new Connection("127.0.0.1", 50000,
    "monetdb", "monetdb", "myDatabase");

$result = $connection->Query('
    select
        name, weight_kg, category, birth_date, net_worth_usd
    from
        cats
');

$columnNames = $result->GetColumnNames();

foreach($result as $record) {
    echo "Name: {$record["name"]}\n";
    echo "Weight: {$record["weight_kg"]} kg\n";
    echo "Category: {$record["category"]}\n";
    echo "Birth date: {$record["birth_date"]}\n";
    echo "Net worth: ${$record["net_worth_usd"]}\n\n";
}
```

## Example 2: Get execution stats

```php
$result = $connection->Query(<<<EOF
    update
        "cats"
    set
        "weight_kg" = 9.2
    where
        "name" = 'Ginger';
    
    insert into
        "cats"
        ("name", "weight_kg", "category", "birth_date", "net_worth_usd")
    values
        ('Mew', 8.2, 'shorthair', '2015-03-11', 1250000);
EOF
);

foreach($result->GetStatusRecords() as $stat) {
    echo "Affected rows: {$stat->GetAffectedRows()}\n";
}
```

## Example 3: Parameterized query with prepared statement

```php
$result = $connection->Query('
    select
        *
    from
        "cats"
    where
        "name" = ?
        and "weight_kg" > ?
    limit
        10
', [ "D'artagnan", 5.3 ]);
```

## Example 4: Using escaping

```php
$name = $connection->Escape("D'artagnan");
$weight = floatval("5.3");

$result = $connection->Query(<<<EOF
    select
        *
    from
        "cats"
    where
        "name" = '{$name}'
        and "weight_kg" > {$weight}
EOF
);
```

## Example 5: Renaming fields, using column info

```php
$result = $connection->Query('
    select
        "category",
        sys.stddev_samp("weight_kg") as "weight_stddev",
        sys.median("weight_kg") as "weight_median",
        avg("weight_kg") as "weight_mean"
    from
        "cats"
    group by
        "category"
');

echo "The columns of the response data:\n\n";

foreach($result->GetColumnInfo() as $info) {
    echo "Table/resource name: {$info->GetTableName()}\n";
    echo "Field name: {$info->GetFieldName()}\n";
    echo "Type: {$info->GetType()}\n";
    echo "Length: {$info->GetLength()}\n\n";
}

echo "Data:\n\n";

foreach($result as $record) {
    echo "{$record["category"]} : Mean: {$record["weight_mean"]} kg, "
        ."Median: {$record["weight_median"]} kg, "
        ."StdDev: {$record["weight_stddev"]} kg\n";
}
```

## Example 6: Query the first record only

```php
$record = $connection->QueryFirst('
    select
        sum("weight_kg") as "weight"
    from
        "cats"
');

echo "Sum: {$record["weight"]}\n";
```

## Example 7: Transactions

```php
$connection->Query(<<<EOF
    start transaction;

    update
        "cats"
    set
        "weight_kg" = 9.2
    where
        "name" = 'Ginger';
    
    insert into
        "cats"
        ("name", "weight_kg", "category", "birth_date", "net_worth_usd")
    values
        ('Mew', 8.2, 'shorthair', '2015-03-11', 1250000);
    
    commit;
EOF
);
```

Or:

```php
$connection->Query('start transaction');

$connection->Query(<<<EOF
    update
        "cats"
    set
        "weight_kg" = 9.2
    where
        "name" = 'Ginger'
EOF
);

$connection->Query(<<<EOF
    insert into
        "cats"
        ("name", "weight_kg", "category", "birth_date", "net_worth_usd")
    values
        ('Mew', 8.2, 'shorthair', '2015-03-11', 1250000)
EOF
);

$connection->Query('commit');
```

## Example 8: Importing data the fastest way

```php
$connection->Query(<<<EOF
    copy offset 2 into cats
    from
        '/home/meow/cats.csv'
        ("name", "weight_kg", "category", "birth_date", "net_worth_usd")
    delimiters ',', '\n', '"'
    NULL as '';
EOF
);
```

## Example 9: Using multiple connections

```php
$connection1 = new Connection("127.0.0.1", 50000,
    "monetdb", "monetdb", "myDatabase");
$connection2 = new Connection("127.0.0.1", 50000,
    "monetdb", "monetdb", "myDatabase");
$connection3 = new Connection("127.0.0.1", 50000,
    "monetdb", "monetdb", "myDatabase");

$result1 = $connection1->Query("...");
$result2 = $connection2->Query("...");
$result3 = $connection3->Query("...");
```

<hr><br>

# API Reference

<!-- API DOC -->

| Class | Summary |
| --- | --- |
| [Connection](#connection-class) | Class for encapsulating a connection to a MonetDB server. |
| [Response](#response-class) | This class represents a response for an SQL query or for a command. In case of a 'select' query, this class can be iterated through, using a 'foreach' loop.  The records are returned as associative arrays, indexed by the column names. |
| [StatusRecord](#statusrecord-class) | This class shares the information returned by MonetDB about the executed queries. Like execution time, number of rows affected, etc. Note that only specific fields are populated for specific queries, the others remain NULL. |
| [ColumnInfo](#columninfo-class) | This class contains inforation about the columns of a table response to a 'select' query. |

<hr><br>

## Connection Class

<em>Class for encapsulating a connection to a MonetDB server.</em>

| Method | Documentation |
| --- | --- |
| <strong>__construct</strong> | Create a new connection to a MonetDB database. <br><br><strong>@param</strong> <em>string</em> <strong>$host</strong> : The host of the database. Use '127.0.0.1' if the DB is on the same machine.<br><strong>@param</strong> <em>int</em> <strong>$port</strong> : The port of the database. For MonetDB this is usually 50000.<br><strong>@param</strong> <em>string</em> <strong>$user</strong> : The user name.<br><strong>@param</strong> <em>string</em> <strong>$password</strong> : The password of the user.<br><strong>@param</strong> <em>string</em> <strong>$database</strong> : The name of the database to connect to. Don't forget to release and start it.<br><strong>@param</strong> <em>string</em> <strong>$saltedHashAlgo</strong> <em>= "SHA1"</em> : Optional. The preferred hash algorithm to be used for exchanging the password. It has to be supported by both the server and PHP. This is only used for the salted hashing. Another stronger algorithm is used first (usually SHA512).<br><strong>@param</strong> <em>bool</em> <strong>$syncTimeZone</strong> <em>= true</em> : If true, then tells the clients time zone offset to the server, which will convert all timestamps is case there's a difference. If false, then the timestamps will end up on the server unmodified.<br><strong>@param</strong> <em>int</em> <strong>$maxReplySize</strong> <em>= 200</em> : The maximal number of tuples returned in a response. A higher value results in smaller number of memory allocations and string operations, but also in higher memory footprint. |
| <strong>Close</strong> | Close the connection |
| <strong>Query</strong> | Execute an SQL query and return its response. For 'select' queries the response can be iterated using a 'foreach' statement. You can pass an array as second parameter to execute the query as prepared statement, where the array contains the parameter values. SECURITY WARNING: For prepared statements in MonetDB, the parameter values are passed in a regular 'EXECUTE' command, using escaping. Therefore the same security considerations apply here as for using the Connection->Escape(...) method. Please read the comments for that method. <br><br><strong>@param</strong> <em>string</em> <strong>$sql</strong><br><strong>@param</strong> <em>array</em> <strong>$params</strong> <em>= null</em> : An optional array for prepared statement parameters. If not provided (or null), then a normal query is executed, instead of a prepared statement. The parameter values will retain their PHP type if possible. The following values won't be converted to string: null, true, false and numeric values.<br><strong>@return</strong> <em>Response</em> |
| <strong>QueryFirst</strong> | Execute an SQL query and return only the first row as an associative array. If there is more data on the stream, then discard all. Returns null if the query has empty result. You can pass an array as second parameter to execute the query as prepared statement, where the array contains the parameter values. <br><br><strong>@param</strong> <em>string</em> <strong>$sql</strong><br><strong>@param</strong> <em>array</em> <strong>$params</strong> <em>= null</em> : An optional array for prepared statement parameters. If not provided (or null), then a normal query is executed, instead of a prepared statement. See the 'Query' method for more information about the parameter values.<br><strong>@return</strong> <em>string[] -or- null</em> |
| <strong>Command</strong> | Send a 'command' to MonetDB. Commands are used for configuring the database, for example setting the maximal response size, or for requesting unread parts of a query response ('export').<br><br><strong>@param</strong> <em>string</em> <strong>$command</strong><br><strong>@param</strong> <em>bool</em> <strong>$noResponse</strong> <em>= true</em> : If true, then returns NULL and makes no read to the underlying socket.<br><strong>@return</strong> <em>Response -or- null</em> |
| <strong>Escape</strong> | Escape a string value, to be inserted into a query, inside single quotes. SECURITY WARNING: Currently no successful SQL-injection attacks are known, but this function was implemented without full knowledge of the parsing algorithm on the server side, therefore it cannot be trusted completely. Use this library only for data analysis, but don't use it for authentication or session management, etc. Non-authenticated users should never have the opportunity to execute parameterized queries with it, and never run the server as root. As a security measure this library forces the use of multi-byte support and UTF-8 encoding, which is also used by MonetDB, avoiding the SQL-injection attacks, which play with differences between character encodings. The following characters are escaped by this method: backslash, single quote, carriage return, line feed, tabulator, null character, CTRL+Z.<br><br><strong>@param</strong> <em>string</em> <strong>$value</strong><br><strong>@return</strong> <em>string</em> |
| <strong>ClearPsCache</strong> | Clears the in-memory cache of prepared statements. This is called automatically when an error is received from MonetDB, because that also purges the prepared statements and all session state in this case. |
| <strong>GetMaxReplySize</strong> | The maximal number of tuples returned in a response.<br><br><strong>@return</strong> <em>int</em> |

<hr><br>

## Response Class

<em>This class represents a response for an SQL query or for a command. In case of a 'select' query, this class can be iterated through, using a 'foreach' loop.  The records are returned as associative arrays, indexed by the column names.</em>

| Method | Documentation |
| --- | --- |
| <strong>Discard</strong> | Read through all of the data and discard it. Use this method when you don't want to iterate through a long query, but you would like to start a new one instead. |
| <strong>IsDiscarded</strong> | Returns true if this response is no longer connected to an input TCP stream.<br><br><strong>@return</strong> <em>boolean</em> |
| <strong>GetColumnNames</strong> | Returns the names of columns for the table. If you would like to have more information about the columns, than just their names, then use the 'GetColumnInfo()' method.<br><br><strong>@return</strong> <em>string[]</em> |
| <strong>Fetch</strong> | Returns the next row as an associative array, or null if the query ended.<br><br><strong>@return</strong> <em>array -or- null</em> |
| <strong>GetStatusRecords</strong> | Returns one or more Status records that tell information about the queries executed through a single request.<br><br><strong>@return</strong> <em>StatusRecord[]</em> |
| <strong>GetColumnInfo</strong> | Returns an array of ColumnInfo objects that contain inforation about the columns of a table response to a 'select' query.<br><br><strong>@return</strong> <em>ColumnInfo[]</em> |

<hr><br>

## StatusRecord Class

<em>This class shares the information returned by MonetDB about the executed queries. Like execution time, number of rows affected, etc. Note that only specific fields are populated for specific queries, the others remain NULL.</em>

| Method | Documentation |
| --- | --- |
| <strong>GetQueryType</strong> | Returns a short string which identifies the type of the query.<br><br><strong>@return</strong> <em>string</em> |
| <strong>GetDescription</strong> | Returns a user-friendly text which describes the effect of the query.<br><br><strong>@return</strong> <em>string</em> |
| <strong>GetExecutionTime</strong> | The time the server spent on executing the query. In milliseconds.<br><br><strong>@return</strong> <em>float -or- null</em> |
| <strong>GetQueryParsingTime</strong> | The time it took to parse and optimize the query. In milliseconds.<br><br><strong>@return</strong> <em>float -or- null</em> |
| <strong>GetAffectedRows</strong> | The number of rows updated or inserted.<br><br><strong>@return</strong> <em>integer -or- null</em> |
| <strong>GetRowCount</strong> | The number of rows in the response.<br><br><strong>@return</strong> <em>integer -or- null</em> |
| <strong>GetAsText</strong> | Get a description of the status response in a human-readable format.<br><br><strong>@return</strong> <em>string</em> |
| <strong>GetPreparedStatementID</strong> | Get the ID of a created prepared statement. This ID can be used in an 'EXECUTE' statement, but only in the same session.<br><br><strong>@return</strong> <em>integer -or- null</em> |
| <strong>GetQueryID</strong> | Returns the ID of the query response that is returned in the result set.<br><br><strong>@return</strong> <em>integer -or- null</em> |

<hr><br>

## ColumnInfo Class

<em>This class contains inforation about the columns of a table response to a 'select' query.</em>

| Method | Documentation |
| --- | --- |
| <strong>GetTableName</strong> | The name of the table the field belongs to, or the name of a temporary resource if the value is the result of an expression.<br><br><strong>@return</strong> <em>string</em> |
| <strong>GetFieldName</strong> | Field name.<br><br><strong>@return</strong> <em>string</em> |
| <strong>GetType</strong> | The SQL data type of the field.<br><br><strong>@return</strong> <em>string</em> |
| <strong>GetLength</strong> | A length value that can be used for deciding the width of the columns when rendering the response.<br><br><strong>@return</strong> <em>integer</em> |

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
