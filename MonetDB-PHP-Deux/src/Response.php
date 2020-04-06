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

namespace MonetDB;


class Response implements \Iterator {
    /**
     * Discarded means that it is not attached anymore
     * to an input stream.
     *
     * @var bool
     */
    private $isDiscarded;

    /**
     * In case of a longer response, the data is read on the fly,
     * (when the user iterates through the records) instead of
     * just fetching all at the beginning.
     *
     * @var InputStream|null
     */
    private $inputStream;

    /**
     * In case of a table response, after the header is parsed,
     * this field contains the list of column names.
     *
     * @var string[]
     */
    private $columnNames;

    /**
     * The current row without any processing / parsing.
     *
     * @var string|null
     */
    private $rawCurrentRow;

    /**
     * Numerical index of the row. The first is 0.
     *
     * @var integer
     */
    private $rowIndex;

    function __construct(InputStream $inputStream)
    {
        $this->columnNames = null;
        $this->inputStream = $inputStream;
        $this->isDiscarded = false;
        $this->rawCurrentRow = null;
        $this->rowIndex = 0;
        
        $headLine = $this->inputStream->ReadUntilString("\n");

        if ($headLine == Connection::MSG_PROMPT) {
            if (!$this->inputStream->EOF()) {
                throw new MonetException("Invalid response from MonetDB. PROMPT packet without closing bit set in header.");
            }

            $this->inputStream->Discard();
            $this->isDiscarded = true;
            $this->inputStream = null;
        }
        elseif (strlen($headLine) > 1) {
            $first = $headLine[0];
            $second = $headLine[1];

            if ($first == Connection::MSG_QUERY) {
                if ($second == InputStream::Q_TABLE) {
                    /*
                        Process the header
                    */
                    for($i = 0; $i < 4; $i++) {
                        $line = $this->inputStream->ReadUntilString("\n");
                        if (@$line[0] !== Connection::MSG_SCHEMA_HEADER) {
                            throw new MonetException("Invalid response from MonetDB. Broken schema header in response.");
                        }

                        if ($i == 1) {
                            // Before: "% "          (length: 2)
                            // After: " # name\n"    (length: 8)
                            if (substr($line, -8) != " # name\n") {
                                throw new MonetException("Invalid response from MonetDB. The second header line of a table "
                                    ."is supposed to contain the row of field names. Found something else:\n\n{$line}\n");
                            }

                            $this->columnNames = $this->ParseRow(
                                substr($line, 2, strlen($line) - 10)    // 10 = 2 + 8
                            );
                        }
                    }
                }
                else if ($second == InputStream::Q_BLOCK) {
                    throw new MonetException("Q_BLOCK not yet implemented");
                }
                else if ($second == InputStream::Q_UPDATE) {
                    // Get number of affected rows, etc.
                    throw new MonetException("Q_UPDATE not yet implemented");
                }
                else {
                    /*
                        All the other sub-types mean OK,
                        and they won't have any more data.
                    */
                    $this->inputStream->Discard();
                    $this->isDiscarded = true;
                    $this->inputStream = null;
                }
            } else if ($first == Connection::MSG_INFO) {
                throw new MonetException("Error from MonetDB: ".substr($headLine, 1));
            } else {
                throw new MonetException("Invalid response from MonetDB:\n\n{$headLine}\n");
            }
        }
    }

    /**
     * Read through all of the data and discard it.
     * Use this method when you don't want to iterate
     * through a long query, but you would like to
     * start a new one instead.
     *
     * @return void
     */
    public function Discard()
    {
        if ($this->inputStream != null) {
            if ($this === $this->inputStream->GetCurrentResponse()) {
                $this->inputStream->Discard();
            }
        }
        
        $this->isDiscarded = true;
        $this->inputStream = null;
    }

    /**
     * Returns true if this response is no longer connected to an
     * input TCP stream.
     *
     * @return boolean
     */
    public function IsDiscarded(): bool {
        return $this->isDiscarded;
    }

    /**
     * Parse values out of a string row
     *
     * @param string $row
     * @return array
     */
    public function ParseRow(string $row): array {
        $response = explode(",\t", $row);

        foreach($response as &$field) {
            /*
                Can't use trim, because that would remove
                quotes from the data at the end of the field.
            */
            if (@$field[0] === '"') {
                $field = @stripcslashes(substr($field, 1, strlen($field) - 2));
            }
        }

        return $response;
    }

    /**
     * Returns the names of columns for the table.
     *
     * @return string[]
     */
    public function GetColumnNames()
    {
        return $this->columnNames;
    }

    /**
     * Return the current row as an associative array.
     *
     * @return string[]
     */
    public function current() : array {
        if (@$this->rawCurrentRow[0] !== "[") {
            throw new MonetException("Invalid response from MonetDB. Expected a table row, "
                ."received something else:\n{$this->rawCurrentRow}");
        }

        return array_combine(
            $this->columnNames,
            $this->ParseRow(
                substr($this->rawCurrentRow, 2, strlen($this->rawCurrentRow) - 5)
            )
        );
    }

    public function key() : int {
        return $this->rowIndex;
    }

    public function next() {
        if ($this->inputStream->EOF()) {
            $this->rawCurrentRow = null;
        } else {
            $this->rawCurrentRow = $this->inputStream->ReadUntilString("\n");
        }

        $this->rowIndex++;
    }

    /**
     * Can't rewind a TCP stream
     * But this is also called at the very beginning.
     */
    public function rewind() {
        $this->rawCurrentRow = $this->inputStream->ReadUntilString("\n");
    }

    public function valid() : bool {
        return $this->rawCurrentRow !== null;
    }
}
