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
    /*
        Randomly placed special characters into the text.
        Source: https://ja.wikipedia.org/wiki/%E3%81%9D%E3%82%8D%E3%81%B0%E3%82%93#%E6%97%A5%E6%9C%AC%E3%81%B8%E3%81%AE%E4%BC%9D%E6%9D%A5
        Can't test zero/null character \0, because MonetDB truncates the string there.
        (Can be reproduced on the console)
    */
    const SAMPLE = "日本語の「そろばん」は'「算盤」\nの中国読み'「スワンパン」が変化したものだといわれている。"
        ."中国から日本に伝わったのがいつ頃か詳しいことは分かっ\tていないが、少なくとも15世紀初頭には使用されていた[5]。"
        ."『日本風土記』（1570年代）には「そおはん」と言う表現でそろばんのことが記されており、その頃には日本に既に伝来し"
        ."ていたことがうかがえる。\"なお使用できる状態でと\032言う限定ではあるが、\"現存する日本最古のそろばんは前田利家所有のもの"
        ."で尊経閣文庫に保存されている。近年は、黒田藩家臣久野重\r勝の家に伝来した秀吉拝領の四兵衛重勝拝領算盤"
        ."というそろばんの方が古いという[6][7]。";

    public function Run(array $args) {
        $connection = new Connection("127.0.0.1", 50000, "monetdb", "monetdb", "myDatabase");
        $sampleLength = mb_strlen(self::SAMPLE);

        /*
            Create schema
        */
        echo "Creating schema...\n";

        $connection->Query('
            drop schema if exists "JapaneseTest" cascade;
            create schema "JapaneseTest";
            set schema "JapaneseTest";

            create table "TestTable" (
                "id" int,
                "text1" text, "text2" text, "text3" text, "text4" text, "text5" text,
                "text6" text, "text7" text, "text8" text, "text9" text, "text10" text
            );
        ');

        /*
            Pushing data to MonetDB (and escaping it)
        */
        echo "Pushing data to MonetDB...\n";
        $dataCache = [];
        $pushedCharCount = 0;
        $pushedRows = 0;

        for($i = 0; $i < 1234; $i++) {
            $values = [ $i + 1 ];

            for($j = 1; $j <= 10; $j++) {
                $size = rand(0, $sampleLength);
                $startPos = rand(0, $sampleLength - $size);
                $value = mb_substr(self::SAMPLE, $startPos, $size);
                $pushedCharCount += mb_strlen($value);
                $values[] = $value;

                $dataCache[$i + 1]["text{$j}"] = $value;
            }

            $connection->Query('
                insert into
                    "TestTable"
                    ("id", "text1", "text2", "text3", "text4", "text5",
                    "text6", "text7", "text8", "text9", "text10")
                values
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ', $values);

            $pushedRows++;
        }

        echo "Pushed character count: {$pushedCharCount}\n";
        echo "Pushed rows: {$pushedRows}\n";
        
        /*
            Determine lengths through SQL queries
        */
        $sum = $connection->QueryFirst('
            select
                sum(length("text1") + length("text2") + length("text3") + length("text4") + length("text5") +
                    length("text6") + length("text7") + length("text8") + length("text9") + length("text10")) as "total"
            from
                "TestTable"
        ');

        echo "SQL character count: {$sum["total"]}\n";

        $rows = $connection->QueryFirst('
            select
                count(*) as "count"
            from
                "TestTable"
        ');

        echo "SQL row count: {$rows["count"]}\n";

        /*
            Read back the data, calculate counts and compare strings in PHP
        */
        echo "Querying and validating characters in PHP...\n";

        $result = $connection->Query('
            select
                *
            from
                "TestTable"
        ');

        $readCharCount = 0;
        $readRows = 0;

        foreach($result as $record) {
            foreach($record as $field => $value) {
                if ($field == "id") {
                    continue;
                }

                if ($dataCache[$record["id"]][$field] !== $value) {
                    echo "No match:\n\n{$dataCache[$record["id"]][$field]}\n\n{$value}\n\n";
                }

                $readCharCount += mb_strlen($value);
            }

            $readRows++;
        }

        echo "Read character count: {$readCharCount}\n";
        echo "Read row count: {$readRows}\n";
    }
}
