<?php

namespace Program;

use MonetDB\Connection;
use MonetDB\MonetException;

ini_set('display_errors', '1');
error_reporting(E_ALL);

require((__DIR__)."/vendor/autoload.php");

define("MonetDB-PHP-Deux-DEBUG", 1);

try {
    $connection = new Connection("127.0.0.1", 50000, "monetdb", "monetdb", "myDatabase");
} catch(MonetException $ex) {
    echo "\n{$ex->getMessage()}\n";
}
