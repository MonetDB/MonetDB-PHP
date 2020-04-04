<?php

namespace Program;

use MonetDB\Connection;


ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);

require((__DIR__)."/vendor/autoload.php");

$connection = new Connection("127.0.0.1", 50000, "monetdb", "monetdb", "myDatabase");

