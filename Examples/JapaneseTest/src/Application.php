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
        Can't test zero/null character \0, because MonetDB truncates the string there.
        (Can be reproduced on the console)
    */
    const SAMPLE = "日本語の「そろばん」'は「算盤」の中国読み「スワンパン」が変化したものだといわれている。"
        ."中国から日本に伝わった\"のがいつ頃か詳しいことは分かってい\x1aないが、少なくとも15世紀初頭には使用されていた[5]。"
        ."『日本風土記\t』（1570年代）には「そおはん」と言'う表現でそろばんのことが記\x1aされており、その頃には日本に既に伝来し"
        ."ていたことがうかがえる。なお使用できる状\"態でと言う限定ではあるが、'現存する日本最古のそろばんは前田利家所有のもの"
        ."で尊経閣文庫に保存さ\nれている。近年は、黒田藩家臣久野重勝\rの家に伝来した'秀吉拝\"領の四兵衛重\"勝拝領算盤"
        ."というそろばんの方が古いという[6][7]。";

    public function Run(array $args) {
        $connection = new Connection("127.0.0.1", 50000, "monetdb", "monetdb", "myDatabase");
        $sampleLength = mb_strlen(self::SAMPLE);

        echo "Sample length: {$sampleLength} / Bytes: ".strlen(self::SAMPLE)."\n";
        /*
            Create schema
        */
        echo "Creating schema...\n";

        $result = $connection->Query('
            drop schema if exists "JapaneseTest" cascade;
            create schema "JapaneseTest";
            set schema "JapaneseTest";

            create table "TestTable" (
                "text1" text(900), "text2" text(900), "text3" text(900), "text4" text(900), "text5" text(900),
                "text6" text(900), "text7" text(900), "text8" text(900), "text9" text(900), "text10" text(900)
            );
        ');

        /*
            Pushing data to MonetDB (and escaping it)
        */
        echo "Pushing data to MonetDB...\n";
        $pushedCharCount = 0;
        $pushedRows = 0;

        for($i = 0; $i < 1234; $i++) {
            $values = [];

            for($j = 1; $j <= 10; $j++) {
                $size = rand(0, $sampleLength);
                $startPos = rand(0, $sampleLength - $size);
                $value = mb_substr(self::SAMPLE, $startPos, $size);
                $pushedCharCount += mb_strlen($value);
                $values[] = (string)$value;
            }

            $connection->Query('
                insert into
                    "TestTable"
                    ("text1", "text2", "text3", "text4", "text5",
                    "text6", "text7", "text8", "text9", "text10")
                values
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ', $values);

            $pushedRows++;
        }

        echo "Pushed character count: {$pushedCharCount}\n";
        echo "Pushed rows: {$pushedRows}\n";
        
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

        echo "Querying and counting characters...\n";

        $result = $connection->Query('
            select
                *
            from
                "TestTable"
        ');

        $readCharCount = 0;
        $readRows = 0;

        foreach($result as $record) {
            foreach($record as $value) {
                $readCharCount += mb_strlen($value);
            }

            $readRows++;
        }

        echo "Read character count: {$readCharCount}\n";
        echo "Read row count: {$readRows}\n";
    }
}
