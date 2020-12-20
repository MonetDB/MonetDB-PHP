MonetDB-PHP
===========

The official PHP client library for accessing MonetDB. For PHP 7.2 or above.

Main features:
- Parameterized queries, using cached prepared statements.
- Extensively tested with Japanese characters for the UTF-8 compliance.
- Multiple, concurrent connections.
- Allows access to response stats, like execution time and affected row count, etc.
- The thrown `MonetException` exception objects contain user-friendly error messages.
- Provides information about the columns of the response data, like name, SQL type and length.

If you wish to implement your own client library either for PHP or for another language,
then please read the [guide about the client-server protocol](protocol_doc/README.md).

# Table of contents

- [MonetDB-PHP](#monetdb-php)
- [Table of contents](#table-of-contents)
- [Installation with Composer](#installation-with-composer)
- [Usage without installation](#usage-without-installation)
- [Examples](#examples)
  - [Example 1: Simple query](#example-1-simple-query)
  - [Example 2: Get execution stats](#example-2-get-execution-stats)
  - [Example 3: Parameterized query with prepared statement](#example-3-parameterized-query-with-prepared-statement)
  - [Example 4: Using escaping](#example-4-using-escaping)
  - [Example 5: Renaming fields and using column info](#example-5-renaming-fields-and-using-column-info)
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
- [IDE setup](#ide-setup)

# Installation with Composer

This library is available on Packagist at:
- https://packagist.org/packages/tbolner/monetdb-php

First install [Composer](https://getcomposer.org/download/), then execute the following in your project's directory:

```
composer require tbolner/monetdb-php
```

Or add the following line to your `composer.json` file's `require` section and then execute `composer update`:

```
"tbolner/monetdb-php": "^1.1"
```

# Usage without installation

You don't need to use [Composer](https://getcomposer.org) in your project. You can
just copy all files in the ['src' folder](https://github.com/MonetDB/MonetDB-PHP/tree/master/src),
and include them in your project through the [include.php](src/include.php) file, which
was created just for this purpose.

```php
require_once(__DIR__."/../path/to/include.php");
```

Then either reference the classes by a combination of a `use` statement and the short class name
(as it is done in the [example projects](https://github.com/MonetDB/MonetDB-PHP/tree/master/Examples)):

```php
use MonetDB\Connection;

$connection = new Connection("127.0.0.1", 50000, "monetdb", "monetdb", "myDatabase");
```

Or just use the fully qualified class name (if your project doesn't use namespaces):

```php
$connection = new \MonetDB\Connection("127.0.0.1", 50000, "monetdb", "monetdb", "myDatabase");
```

Please make sure that the `php-mbstring` (multi-byte string) extension is installed and enabled,
and the character encoding for your project is set to UTF-8: (This is required for preventing SQL injection attacks)

```php
mb_internal_encoding('UTF-8');
mb_regex_encoding('UTF-8');
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

The returned values are always in string representation except the null, which
is always returned as `null`.

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

In MonetDB the placeholders of prepared statements have specific types.
This library auto-converts some of PHP types to the corresponding MonetDB types.

| MonetDB type | Accepted PHP types | Value examples |
| --- | --- | --- |
| timestamp | `string`, `DateTime` | `"2020-12-20 11:14:26.123456"` |
| date | `string`, `DateTime` | `"2020-12-20"` |
| boolean | `boolean`, `string`, `integer` | `true`, `false`, `"true"`, `0`, `"0"`, `1`, `"t"`, `"f"`, `"yes"`, `"no"`, `"enabled"`, `"disabled"` |
| Numeric values | `integer`, `float`, `string` | `12.34`, `"12.34"` (use string for huge numbers) |
| Character types | `string` | `"Hello World!"` |
| Binary | `string` | `"0f44ba12"` (always interpreted as hexadecimal) |
| time | `string`, `DateTime` | `"11:28"`, `"12:28:34"` |

Always pass the null values as `null`, and not as a string.

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

## Example 5: Renaming fields and using column info

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
    echo "Field name: {$info->GetColumnName()}\n";
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
| [ColumnInfo](#columninfo-class) | This class contains information about the columns of a table response to a 'select' query. |

<hr><br>

## Connection Class

<em>Class for encapsulating a connection to a MonetDB server.</em>

| Method | Documentation |
| --- | --- |
| <strong>__construct</strong> | Create a new connection to a MonetDB database. <br><br><strong>@param</strong> <em>string</em> <strong>$host</strong> : The host of the database. Use '127.0.0.1' if the DB is on the same machine.<br><strong>@param</strong> <em>int</em> <strong>$port</strong> : The port of the database. For MonetDB this is usually 50000.<br><strong>@param</strong> <em>string</em> <strong>$user</strong> : The user name.<br><strong>@param</strong> <em>string</em> <strong>$password</strong> : The password of the user.<br><strong>@param</strong> <em>string</em> <strong>$database</strong> : The name of the database to connect to. Don't forget to release and start it.<br><strong>@param</strong> <em>string</em> <strong>$saltedHashAlgo</strong> <em>= "SHA1"</em> : Optional. The preferred hash algorithm to be used for exchanging the password. It has to be supported by both the server and PHP. This is only used for the salted hashing. Another stronger algorithm is used first (usually SHA512).<br><strong>@param</strong> <em>bool</em> <strong>$syncTimeZone</strong> <em>= true</em> : If true, then tells the clients time zone offset to the server, which will convert all timestamps is case there's a difference. If false, then the timestamps will end up on the server unmodified.<br><strong>@param</strong> <em>int</em> <strong>$maxReplySize</strong> <em>= 200</em> : The maximal number of tuples returned in a response. A higher value results in smaller number of memory allocations and string operations, but also in higher memory footprint. |
| <strong>Close</strong> | Close the connection |
| <strong>Query</strong> | Execute an SQL query and return its response. For 'select' queries the response can be iterated using a 'foreach' statement. You can pass an array as second parameter to execute the query as prepared statement, where the array contains the parameter values. SECURITY WARNING: For prepared statements in MonetDB, the parameter values are passed in a regular 'EXECUTE' command, using escaping. Therefore the same security considerations apply here as for using the Connection->Escape(...) method. Please read the comments for that method. <br><br><strong>@param</strong> <em>string</em> <strong>$sql</strong><br><strong>@param</strong> <em>array</em> <strong>$params</strong> <em>= null</em> : An optional array for prepared statement parameters. If not provided (or null), then a normal query is executed instead of a prepared statement. The parameter values will retain their PHP type if possible. The following values won't be converted to string: null, true, false and numeric values.<br><strong>@return</strong> <em>Response</em> |
| <strong>QueryFirst</strong> | Execute an SQL query and return only the first row as an associative array. If there is more data on the stream, then discard all. Returns null if the query has empty result. You can pass an array as second parameter to execute the query as prepared statement, where the array contains the parameter values. <br><br><strong>@param</strong> <em>string</em> <strong>$sql</strong><br><strong>@param</strong> <em>array</em> <strong>$params</strong> <em>= null</em> : An optional array for prepared statement parameters. If not provided (or null), then a normal query is executed instead of a prepared statement. See the 'Query' method for more information about the parameter values.<br><strong>@return</strong> <em>string[] -or- null</em> |
| <strong>Command</strong> | Send a 'command' to MonetDB. Commands are used for configuring the database, for example setting the maximal response size, or for requesting unread parts of a query response ('export').<br><br><strong>@param</strong> <em>string</em> <strong>$command</strong><br><strong>@param</strong> <em>bool</em> <strong>$noResponse</strong> <em>= true</em> : If true, then returns NULL and makes no read to the underlying socket.<br><strong>@return</strong> <em>Response -or- null</em> |
| <strong>Escape</strong> | Escape a string value, to be inserted into a query, inside single quotes. The following characters are escaped by this method: backslash, single quote, carriage return, line feed, tabulator, null character, CTRL+Z. As a security measure this library forces the use of multi-byte support and UTF-8 encoding, which is also used by MonetDB, avoiding the SQL-injection attacks, which play with differences between character encodings. <br><br><strong>@param</strong> <em>string</em> <strong>$value</strong><br><strong>@return</strong> <em>string</em> |
| <strong>ClearPsCache</strong> | Clears the in-memory cache of prepared statements. This is called automatically when an error is received from MonetDB, because that also purges the prepared statements and all session state in this case. |
| <strong>GetMaxReplySize</strong> | The maximal number of tuples returned in a response.<br><br><strong>@return</strong> <em>int</em> |

<hr><br>

## Response Class

<em>This class represents a response for an SQL query or for a command. In case of a 'select' query, this class can be iterated through, using a 'foreach' loop.  The records are returned as associative arrays, indexed by the column names.</em>

| Method | Documentation |
| --- | --- |
| <strong>Discard</strong> | Read through all of the data and discard it. Use this method when you don't want to iterate through a long query, but you would like to start a new one instead. |
| <strong>IsDiscarded</strong> | Returns true if this response is no longer connected to an input TCP stream.<br><br><strong>@return</strong> <em>boolean</em> |
| <strong>GetColumnNames</strong> | Returns the names of columns for the table. If you would like to have more information about the columns than just their names, then use the 'GetColumnInfo()' method.<br><br><strong>@return</strong> <em>string[]</em> |
| <strong>Fetch</strong> | Returns the next row as an associative array, or null if the query ended.<br><br><strong>@return</strong> <em>array -or- null</em> |
| <strong>GetStatusRecords</strong> | Returns one or more Status records that tell information about the queries executed through a single request.<br><br><strong>@return</strong> <em>StatusRecord[]</em> |
| <strong>GetColumnInfo</strong> | Returns an array of ColumnInfo objects that contain information about the columns of a table response to a 'select' query.<br><br><strong>@return</strong> <em>ColumnInfo[]</em> |

<hr><br>

## StatusRecord Class

<em>This class shares the information returned by MonetDB about the executed queries. Like execution time, number of rows affected, etc. Note that only specific fields are populated for specific queries, the others remain NULL.</em>

| Method | Documentation |
| --- | --- |
| <strong>GetQueryType</strong> | Returns a short string which identifies the type of the query.<br><br><strong>@return</strong> <em>string</em> |
| <strong>GetDescription</strong> | Returns a user-friendly text which describes the effect of the query.<br><br><strong>@return</strong> <em>string</em> |
| <strong>GetQueryTime</strong> | The time the server spent on executing the query. In milliseconds.<br><br><strong>@return</strong> <em>float -or- null</em> |
| <strong>GetSqlOptimizerTime</strong> | SQL optimizer time in milliseconds.<br><br><strong>@return</strong> <em>float -or- null</em> |
| <strong>GetMalOptimizerTime</strong> | MAL optimizer time in milliseconds.<br><br><strong>@return</strong> <em>float -or- null</em> |
| <strong>GetAffectedRows</strong> | The number of rows updated or inserted.<br><br><strong>@return</strong> <em>integer -or- null</em> |
| <strong>GetTotalRowCount</strong> | The total number of rows in the result set. This includes those rows too, which are not in the current response.<br><br><strong>@return</strong> <em>integer -or- null</em> |
| <strong>GetAsText</strong> | Get a description of the status response in a human-readable format.<br><br><strong>@return</strong> <em>string</em> |
| <strong>GetPreparedStatementID</strong> | Get the ID of a created prepared statement. This ID can be used in an 'EXECUTE' statement, but only in the same session.<br><br><strong>@return</strong> <em>integer -or- null</em> |
| <strong>GetResultID</strong> | Returns the ID of the result set that is returned for a query. It is stored on the server for this session, and parts of it can be queried using the "export" command.<br><br><strong>@return</strong> <em>integer -or- null</em> |
| <strong>GetAutoCommitState</strong> | Available after "start transaction", "commit" or "rollback". Tells whether the current session is in auto-commit mode or not.<br><br><strong>@return</strong> <em>boolean -or- null</em> |
| <strong>GetRowCount</strong> | The number of rows (tuples) in the current response only.<br><br><strong>@return</strong> <em>integer -or- null</em> |
| <strong>GetColumnCount</strong> | Column count. If the response contains tabular data, then this tells the number of columns.<br><br><strong>@return</strong> <em>integer -or- null</em> |
| <strong>GetQueryID</strong> | Query ID. A global ID which is also used in functions such as sys.querylog_catalog().<br><br><strong>@return</strong> <em>integer -or- null</em> |
| <strong>GetLastInsertID</strong> | The last automatically generated ID by an insert statement. (Usually auto_increment) NULL if none.<br><br><strong>@return</strong> <em>integer -or- null</em> |
| <strong>GetExportOffset</strong> | The index (offset) of the first row in a block response. (For an "export" command.)<br><br><strong>@return</strong> <em>integer -or- null</em> |

<hr><br>

## ColumnInfo Class

<em>This class contains information about the columns of a table response to a 'select' query.</em>

| Method | Documentation |
| --- | --- |
| <strong>GetTableName</strong> | The name of the table the field belongs to, or the name of a temporary resource if the value is the result of an expression.<br><br><strong>@return</strong> <em>string</em> |
| <strong>GetColumnName</strong> | Column name.<br><br><strong>@return</strong> <em>string</em> |
| <strong>GetType</strong> | The SQL data type of the field.<br><br><strong>@return</strong> <em>string</em> |
| <strong>GetLength</strong> | A length value that can be used for deciding the width of the columns when rendering the response.<br><br><strong>@return</strong> <em>integer</em> |

<hr><br>

<!-- API DOC -->

# Development setup through the Docker image

- Build the Docker image:

        docker/build.sh

- Create the Docker container with Apache listening on port 9292:

        docker/create.sh

- Login into the container as the host user or as root:

        docker/login.sh
        docker/root_login.sh

- When you don't need the MonetDB-PHP container anymore, you can get rid of it easily: (this also removes the unused images)

        docker/cleanup.sh

# IDE setup

- IDE: Visual Studio Code
- Plugins:
  - [PHP Intelephense (Ben Mewburn)](https://marketplace.visualstudio.com/items?itemName=bmewburn.vscode-intelephense-client)
  - [PHP DocBlocker (Neil Brayfield)](https://marketplace.visualstudio.com/items?itemName=neilbrayfield.php-docblocker)
  - [Remote-SSH (Microsoft)](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-ssh)
  - [Markdown All in One (Yu Zhang)](https://marketplace.visualstudio.com/items?itemName=yzhang.markdown-all-in-one)
- Plugins for the [Monet-Explorer](protocol_doc/monet-explorer/) project:
  - [C/C++ IntelliSense, debugging, and code browsing. (Microsoft)](https://marketplace.visualstudio.com/items?itemName=ms-vscode.cpptools)
  - [Doxygen Documentation Generator (Christoph Schlosser)](https://marketplace.visualstudio.com/items?itemName=cschlosser.doxdocgen)
