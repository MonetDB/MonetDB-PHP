#!/usr/bin/php
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

/*
    This is just a quick solution for generating
    API reference doc inside the README.md
*/

$parse_files = [
    "src/Connection.php",
    "src/Response.php",
    "src/StatusRecord.php",
    "src/ColumnInfo.php"
];

$target_file = "README.md";
$doc_marker = "<!-- API DOC -->";
$output = [];
$toc = [
    "| Class | Summary |",
    "| --- | --- |"
];

foreach($parse_files as $path) {
    $segments = explode("/**\n", preg_replace('/[\r\n]+/', "\n", file_get_contents(__DIR__."/".$path)));
    $className = "";
    $classDoc = "";
    $methods = [];

    foreach($segments as $segment) {
        try {
            /*
                Class
            */
            $classBoundaries = [];
            preg_match_all('/\nclass\s+([a-z0-9_]+)\s+/is', $segment, $classBoundaries, PREG_OFFSET_CAPTURE);

            if (isset($classBoundaries[0][0][1]) && isset($classBoundaries[1][0][0])) {
                $docEnds = $classBoundaries[0][0][1];
                $className = $classBoundaries[1][0][0];

                $classDoc = implode(" ", ParseDoc(substr($segment, 0, $docEnds))["head"]);

                continue;
            }

            /*
                Constructor
            */
            $constructorIdentifier = "function __construct(";
            $constructorPos = strpos($segment, $constructorIdentifier);
            if ($constructorPos !== false) {
                $docArray = ParseDoc(substr($segment, 0, $constructorPos));
                $methods["__construct"] = implode(" ", $docArray["head"]).
                    ParseParams($segment, $constructorPos, $constructorIdentifier, $docArray);

                continue;
            }
            
            /*
                Methods
            */
            $methodBoundaries = [];
            preg_match_all('/public function ([a-z0-9_]+)\s*\(/is', $segment, $methodBoundaries, PREG_OFFSET_CAPTURE);

            if (isset($methodBoundaries[0][0][0]) && isset($methodBoundaries[0][0][1])
                    && isset($methodBoundaries[1][0][0])) {

                $docArray = ParseDoc(substr($segment, 0, $methodBoundaries[0][0][1]));
                $methodName = $methodBoundaries[1][0][0];
                $methods[$methodName] = implode(" ", $docArray["head"]).
                    ParseParams($segment, $methodBoundaries[0][0][1],
                    $methodBoundaries[0][0][0], $docArray);

                continue;
            }
        } catch (\Exception $ex) {
            if ($ex->getMessage() == "ignore") {
                continue;
            }

            throw $ex;
        }
    }

    $toc[] = "| [{$className}](#".strtolower($className)."-class) | {$classDoc} |";

    $outLines = [];

    $outLines[] = "<hr><br>\n";
    $outLines[] = "## {$className} Class";
    $outLines[] = "\n<em>{$classDoc}</em>\n";
    $outLines[] = "| Method | Documentation |";
    $outLines[] = "| --- | --- |";
    foreach($methods as $methodName => $methodDoc) {
        $outLines[] = "| <strong>{$methodName}</strong> | {$methodDoc} |";
    }

    $output[] = implode("\n", $outLines);
}

$contentParts = explode($doc_marker, file_get_contents(__DIR__."/".$target_file));
$finalOutput = $contentParts[0].$doc_marker."\n\n".implode("\n", $toc)
    ."\n\n".implode("\n\n", $output)
    ."\n\n<hr><br>\n\n".$doc_marker.$contentParts[2];

file_put_contents(__DIR__."/".$target_file, $finalOutput);

/******************************************************************************/

function ParseParams(string $segment, int $start, string $functionDef, $docArray): string {
    $paramStart = $start + strlen($functionDef);
    $paramEnd = strpos($segment, ")", $paramStart);
    $body = trim(substr($segment, $paramStart, $paramEnd - $paramStart), " ()");
    $body = preg_replace('/[\s\r\n]+/', ' ', $body);
    $parts = [];
    
    $params = explode(",", $body);
    foreach($params as $param) {
        $paramParts = [];
        $matches = [];
        preg_match('/([^\$]*)[\s]*(\$[a-z0-9_]+)[\s]*([^\$]*)/i', trim($param), $matches);

        if (count($matches) < 4) {
            continue;
        }
        
        if ($matches[1] != "") {
            $paramParts[] = "<em>".trim($matches[1])."</em>";
        }

        if ($matches[2] != "") {
            $paramParts[] = "<strong>".trim($matches[2])."</strong>";
        }

        if ($matches[3] != "") {
            $paramParts[] = "<em>".trim($matches[3])."</em>";
        }

        if (isset($docArray["param"][$matches[2]])) {
            $doc = trim(implode(" ", $docArray["param"][$matches[2]]));
            if ($doc != "") {
                $paramParts[] = ": ".$doc;
            }
        }

        if (count($paramParts) > 0) {
            $parts[] = implode(" ", $paramParts);
        }
    }

    $result = [];

    if (count($parts) > 0) {
        $result[] = '<strong>@param</strong> '.implode('<br><strong>@param</strong> ', $parts);
    }
    
    if (isset($docArray["return"])) {
        $result[] = trim("<strong>@return</strong> <em>".implode(" ", $docArray["return"]))."</em>";
    }

    $str = implode("<br>", $result);
    if ($str != "") {
        $str = "<br><br>{$str}";
    }

    return str_replace("|", " -or- ", $str);
}

function ParseDoc(string $doc): array {
    $doc = trim($doc, "/*\r\n\t ");

    $docArray = [];
    $lines = preg_split('/[\r\n]+[\s]+\* /', $doc);
    $current = "head";
    $currentParam = null;

    foreach($lines as $line) {
        if (strpos($line, "@ignore") === 0) {
            throw new Exception("ignore");
        }

        $matches = [];
        preg_match('/^@return[s]{0,1}\s+(.*)$/i', $line, $matches);

        if (count($matches) >= 2) {
            $current = "return";
            $currentParam = null;
            @$docArray[$current][] = trim($matches[1], "/*\r\n\t ");
            continue;
        }

        preg_match('/^@([a-z]+)\s+[a-z\|\[\]]+\s+(\$[a-z]+)\s*(.*)$/i', $line, $matches);

        if (count($matches) < 1) {
            if ($currentParam === null) {
                @$docArray[$current][] = trim($line, "/*\r\n\t ");
            } else {
                @$docArray[$current][$currentParam][] = trim($line, "/*\r\n\t ");
            }
        } else {
            $current = trim($matches[1]);

            if ($current == "param") {
                $currentParam = trim($matches[2]);
                @$docArray["param"][$currentParam][] = trim($matches[3], "/*\r\n\t ");
            }
        }
    }

    return $docArray;
}
