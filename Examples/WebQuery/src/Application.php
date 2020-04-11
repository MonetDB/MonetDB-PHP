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

namespace Example;

use MonetDB\Connection;


class Application {
    public function Run(array $args) {
        header('Content-Type: text/html; charset=utf-8');

        /*
            Process parameters
            (Generate a prepared statement)
        */
        $filterExp = [];
        $filterValues = [];

        if (isset($args["name"])) {
            $filterExp[] = 'name like ?';
            $filterValues[] = $args["name"];
        }

        if (isset($args["min_weight"])) {
            $filterExp[] = 'weight_kg >= ?';
            $filterValues[] = floatval($args["min_weight"]);
        }

        if (isset($args["max_weight"])) {
            $filterExp[] = 'weight_kg <= ?';
            $filterValues[] = floatval($args["max_weight"]);
        }

        if (count($filterExp) < 1) {
            $filterExpSQL = "true";
            $filterValues = null;
        } else {
            $filterExpSQL = implode(" and ", $filterExp);
        }

        /*
            Query data
        */
        $connection = new Connection("127.0.0.1", 50000, "monetdb", "monetdb", "myDatabase");

        $connection->Query('set schema "mySchema"');

        $result = $connection->Query(<<<EOF
            select
                *
            from
                "cats"
            where
                {$filterExpSQL}
EOF
        , $filterValues);

        /*
            Display result
        */
        $response = [];

        $response[] = '<!DOCTYPE html>';
        $response[] = '<html lang="en">';
        $response[] = '<head><title>MonetDB-PHP-Deux</title>';
        $response[] = '<style>table, td { border: 1px; border-style: solid; border-collapse: collapse; } td { padding: 5px; }</style>';
        $response[] = '</head>';
        $response[] = '<body>';
        $response[] = '<table>';

        $columns = $result->GetColumnNames();
        $response[]  = '<tr><td><b>'.implode('</b></td><td><b>', $columns).'</b></td></tr>';

        foreach($result as $record) {
            $row = [];
            foreach($record as $value) {
                $row[] = '<td>'.htmlspecialchars($value).'</td>';
            }

            $response[] = '<tr>'.implode($row).'</tr>';
        }

        $response[] = '</table>';
        $response[] = '</body>';
        $response[] = '</html>';

        echo implode("\n", $response);
    }
}
