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
        $connection = new Connection("127.0.0.1", 50000, "monetdb", "monetdb", "myDatabase");

        $result = $connection->Query('
            drop schema if exists "mySchema" cascade;
            create schema "mySchema";
            set schema "mySchema";

            create table "cats" (
                "name" text,
                "weight_kg" decimal(8, 2),
                "category" text,
                "birth_date" date,
                "net_worth_usd" decimal(20, 4)
            );
        ');

        foreach($result->GetStatusRecords() as $stat) {
            echo $stat->GetAsText()."\n\n";
        }

        /* *** */

        $result = $connection->Query(<<<EOF
            start transaction;

            insert into
                "cats"
                ("name", "weight_kg", "category", "birth_date", "net_worth_usd")
            values
                ('Tiger', 8.2, 'fluffy', '2012-04-23', 2340000),
                ('Oscar', 3.4, 'spotted', '2014-02-11', 556235.34),
                ('Coco', 2.52, 'spotted', '2008-12-31', 1470500000),
                ('Max', 4.23, 'spotted', '2010-01-15', 100),
                ('Sooty', 7.2, 'shorthair', '2016-10-01', 580000),
                ('Milo', 5.87, 'spotted', '2015-06-23', 1500.53),
                ('Muffin', 12.6, 'fluffy', '2013-04-07', 230000),
                ('Ginger', 9.4, 'shorthair', '2012-06-19', 177240.5),
                ('Fluffor', 13.12, 'fluffy', '2000-10-07', 5730180200.12),
                ('Lucy', 3.12, 'shorthair', '2018-06-29', 5780000),
                ('Chloe', 2.12, 'spotted', '2013-05-01', 13666200),
                ('Misty', 1.96, 'shorthair', '2014-11-24', 12000000),
                ('Sam', 3.45, 'fluffy', '2018-12-19', 580.4),
                ('Gizmo', 4.65, 'fluffy', '2016-05-11', 120300),
                ('Kimba', 1.23, 'spotted', '2020-01-08', 890000);

            update
                "cats"
            set
                "weight_kg" = 9.42
            where
                "name" = 'Ginger';
            
            commit;
EOF
        );

        $stats = $result->GetStatusRecords();

        echo "Inserted rows: {$stats[0]->GetAffectedRows()}\n";
        echo "Updated rows: {$stats[1]->GetAffectedRows()}\n\n";

        /* *** */

        $result = $connection->Query('
            select
                "category",
                round(sys.stddev_samp("weight_kg"), 2) as "weight_stddev",
                round(sys.median("weight_kg"), 2) as "weight_median",
                round(avg("weight_kg"), 2) as "weight_mean"
            from
                "cats"
            group by
                "category"
        ');

        echo "Columns:\n\n";

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
    }
}
