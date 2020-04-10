<?php

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
            $filterValues[] = $args["min_weight"];
        }

        if (isset($args["max_weight"])) {
            $filterExp[] = 'weight_kg <= ?';
            $filterValues[] = $args["max_weight"];
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
