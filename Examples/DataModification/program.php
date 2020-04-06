<?php
/*
    Copyright 2020 Tamas Bolner

    Licensed under the Apache License, Version 2.0 (the "License");
    you may not use this file except in compliance with the License.
    You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

    Unless required by applicable law or agreed to in writing, software
    distributed under the License is distributed on an "AS IS" BASIS,
    WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
    See the License for the specific language governing permissions and
    limitations under the License.
*/

namespace Program;

use MonetDB\Connection;
use MonetDB\MonetException;

ini_set('display_errors', '1');
error_reporting(E_ALL);

require((__DIR__)."/vendor/autoload.php");

define("MonetDB-PHP-Deux-DEBUG", 1);

try {
    $connection = new Connection("127.0.0.1", 50000, "monetdb", "monetdb", "myDatabase");

    $response = $connection->Query(<<<EOF
        select
            *
        from
            sys.tables
        limit
            8
EOF
    );

    echo implode(" | ", $response->GetColumnNames())."\n";

    foreach($response as $record) {
        echo json_encode($record)."\n";
    }
} catch(MonetException $ex) {
    echo "\n{$ex->getMessage()}\n";
}
