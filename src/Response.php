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

use Exception;

/**
 * This class represents a response for an SQL query
 * or for a command.
 * In case of a 'select' query, this class can be
 * iterated through, using a 'foreach' loop.
 * 
 * The records are returned as associative arrays,
 * indexed by the column names.
 */
class Response implements \Iterator {
    /**
     * Discarded means that it is not attached anymore
     * to an input stream.
     *
     * @var bool
     */
    private $isDiscarded;

    /**
     * The related connection class
     *
     * @var Connection
     */
    private $connection;

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
     * @var string[]|null
     */
    private $columnNames;

    /**
     * Array of ColumnInfo objects that contain inforation about
     * the columns of a table response to a 'select' query.
     *
     * @var ColumnInfo[]|null
     */
    private $columnInfoRecords;

    /**
     * The current line without any processing / parsing.
     *
     * @var string|null
     */
    private $currentLine;

    /**
     * An array of the string/null values.
     * 
     * @var string[]|null
     */
    private $currentRow;

    /**
     * The number of rows processed, which are related
     * to the current query ID.
     *
     * @var integer
     */
    private $rowCount;

    /**
     * Status records that tell information about one
     * or more queries passed to the server and executed.
     *
     * @var StatusRecord[]
     */
    private $statusRecords;

    /**
     * The status record of the first table response
     * encountered in the response. Query data sets
     * related to other query IDs are ignored.
     * 
     * @var StatusRecord
     */
    private $queryStatusRecord;

    /**
     * This mode is activated when a new data set arrives
     * in the response, related to a new query ID.
     * Ignore all tuples when this property is true.
     *
     * @var bool
     */
    private $ignoreTuples;

    /**
     * Constructor
     *
     * @ignore
     * @param Connection $connection
     * @param InputStream $inputStream
     */
    function __construct(Connection $connection, InputStream $inputStream)
    {
        $this->connection = $connection;
        $this->columnNames = [];
        $this->inputStream = $inputStream;
        $this->isDiscarded = false;
        $this->currentLine = null;
        $this->currentRow = null;
        $this->rowCount = 0;
        $this->queryStatusRecord = null;
        $this->statusRecords = [];
        $this->ignoreTuples = false;
        $this->columnInfoRecords = [];

        $this->inputStream->LoadNextResponse();
        $this->Parse();
    }

    /**
     * Read through all of the data and discard it.
     * Use this method when you don't want to iterate
     * through a long query, but you would like to
     * start a new one instead.
     */
    public function Discard()
    {
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
     * Returns the names of columns for the table.
     * If you would like to have more information about the
     * columns than just their names, then use the
     * 'GetColumnInfo()' method.
     *
     * @return string[]
     */
    public function GetColumnNames()
    {
        return $this->columnNames;
    }

    /**
     * Parses through the returned status lines,
     * but stops at table rows, which are related
     * to the current query ID.
     */
    private function Parse() {
        while(true) {
            $this->currentLine = $this->inputStream->ReadNextLine();

            if ($this->currentLine == InputStream::MSG_PROMPT) {
                if ($this->queryStatusRecord !== null) {
                    /*
                        If not all rows did fit into the window,
                        request the next batch.
                    */
                    $diff = $this->queryStatusRecord->GetTotalRowCount() - $this->rowCount;
                    $queryID = $this->queryStatusRecord->GetResultID();

                    if ($diff > 0) {
                        $size = min($diff, $this->connection->GetMaxReplySize());

                        $this->connection->Command("export {$queryID} {$this->rowCount} {$size}", true);
                        $this->inputStream->LoadNextResponse();
                        $this->ignoreTuples = false;

                        continue;
                    }
                }

                $this->Discard();
                return;
            }
            
            $first = @$this->currentLine[0];
            $second = @$this->currentLine[1];

            if ($first == InputStream::MSG_TUPLE) {
                if ($this->ignoreTuples) {
                    continue;
                }

                $this->currentRow = $this->ParseRow(substr($this->currentLine, 2, -2));
                $this->rowCount++;
                // Stop at the next/first
                return;
            }
            else if ($first == InputStream::MSG_QUERY) {
                if ($second == InputStream::Q_TABLE) {
                    $status = new StatusRecord($second, $this->currentLine);
                    $this->statusRecords[] = $status;
                    $this->ignoreTuples = false;

                    /*
                        If there was already table data in the response,
                        then all newer data sets will be ignored.
                        There should be only one 'select' statement in
                        an SQL request. If there are more, then those
                        responses just get ignored.
                    */
                    if ($this->queryStatusRecord !== null) {
                        if ($this->queryStatusRecord->GetResultID() != $status->GetResultID()) {
                            $this->ignoreTuples = true;
                        }
                    }

                    $this->queryStatusRecord = $status; // First query
                    
                    /*
                        Process the header
                    */
                    $infoRowTitles = [" # table_name", " # name", " # type", " # length"];
                    $infoRows = [];

                    foreach($infoRowTitles as $infoRowTitle) {
                        $titleLength = strlen($infoRowTitle);
                        $this->currentLine = $this->inputStream->ReadNextLine();
                        if (@$this->currentLine[0] !== InputStream::MSG_SCHEMA_HEADER) {
                            throw new MonetException("Invalid response from MonetDB. Broken schema header in response.");
                        }

                        if (substr($this->currentLine, -$titleLength) !== $infoRowTitle) {
                            throw new MonetException("Invalid response from MonetDB. A header line of a table "
                                ."is supposed to contain the '{$infoRowTitle}' row. Found something "
                                ."else:\n\n{$this->currentLine}\n");
                        }

                        $infoRows[] = $this->ParseRow(
                            substr($this->currentLine, 2, -$titleLength)
                        );
                    }

                    $this->columnNames = $infoRows[1];
                    $this->columnInfoRecords = [];
                    for($i = 0; $i < count($infoRows[0]); $i++) {
                        $this->columnInfoRecords[] = new ColumnInfo(
                            $infoRows[0][$i], $infoRows[1][$i],
                            $infoRows[2][$i], (int)($infoRows[3][$i])
                        );
                    }

                    continue;
                }
                else if ($second == InputStream::Q_BLOCK) {
                    /*
                        Continue query response
                    */
                    $status = new StatusRecord($second, $this->currentLine);
                    $this->ignoreTuples = false;

                    if ($this->queryStatusRecord === null) {
                        $this->ignoreTuples = true;
                        continue;
                    }

                    if ($this->queryStatusRecord->GetResultID() != $status->GetResultID()) {
                        $this->ignoreTuples = true;
                        continue;
                    }

                    continue;
                }
                else if ($second == InputStream::Q_PREPARE) {
                    /*
                        It returns some meaningless dataset when created. Skip that.
                    */
                    $this->statusRecords[] = new StatusRecord($second, $this->currentLine);
                    $this->Discard();
                    return;
                } else {
                    $this->statusRecords[] = new StatusRecord($second, $this->currentLine);
                    continue;
                }
            } else if ($first == InputStream::MSG_INFO) {
                $this->connection->ClearPsCache();
                throw new MonetException("Error from MonetDB: ".substr($this->currentLine, 1));
            } else {
                throw new MonetException("Invalid response from MonetDB:\n\n{$this->currentLine}\n");
            }
        }
    }

    /**
     * Part of the "Iterator" interface.
     * Return the current row as an associative array.
     *
     * @ignore
     * @return string[]
     */
    public function current() : array {
        if ($this->isDiscarded) {
            return [];
        }

        return array_combine(
            $this->columnNames,
            $this->currentRow
        );
    }

    /**
     * Get field values from a raw table row.
     *
     * @param string $row
     * @return array
     */
    private function ParseRow(string $row): array {
        $response = explode(",\t", $row);

        foreach($response as &$field) {
            /*
                * Convert only the NULL to real values.
                * They are not surrounded by double quotes.
                * Converting the other types (like numbers, bool, etc.)
                * could lead to precision problems or to difficulties
                * in displaying the data. So leave that to the user.
                */
            if ($field === "NULL") {
                $field = null;
            }
            /*
                - Don't use trim, because that would remove
                    quotes from the data at the end of the field.
                - Don't use stipcslashes, because that doesn't
                    know utf-8.
            */
            else if (@$field[0] === '"') {
                $field = mb_ereg_replace_callback(
                    "(\\\\\\\\|\\\\[0-7]{3,3}|\\\\r|\\\\n|\\\\t|\\\\0|\\\\'|\\\\\")",
                    function($match) {
                        switch($match[0]) {
                            case "\\'": return "'";
                            case '\\"': return '"';
                            case '\\\\': return '\\';
                            case "\\n": return "\n";
                            case "\\r": return "\r";
                            case "\\t": return "\t";
                            case "\\0": return "\0";
                            default: {
                                return chr(octdec(substr($match[0], 1)));
                            }
                        }
                    },
                    substr($field, 1, -1)
                );
            }
        }

        return $response;
    }

    /**
     * Part of the "Iterator" interface.
     * Returns the numeric index of the
     * current row.
     *
     * @ignore
     * @return integer
     */
    public function key() : int {
        return $this->rowCount;
    }

    /**
     * Part of the "Iterator" interface.
     * Fetch the next record or end the query.
     * 
     * @ignore
     */
    public function next() {
        if ($this->isDiscarded) {
            return;
        }

        $this->Parse();
    }

    /**
     * Part of the "Iterator" interface.
     * Can't rewind a TCP stream
     * But this is also called at the very beginning.
     * 
     * @ignore
     */
    public function rewind() {
        
    }

    /**
     * Part of the "Iterator" interface.
     * Returns false if all rows have been returned,
     * false otherwise.
     *
     * @ignore
     * @return boolean
     */
    public function valid() : bool {
        return !$this->isDiscarded;
    }

    /**
     * Returns the next row as an associative array,
     * or null if the query ended.
     *
     * @return array|null
     */
    public function Fetch(): ?array {
        if ($this->isDiscarded) {
            return null;
        }

        $response = array_combine(
            $this->columnNames,
            $this->currentRow
        );

        $this->Parse();

        return $response;
    }

    /**
     * Returns one or more Status records that tell information about the
     * queries executed through a single request.
     *
     * @return StatusRecord[]
     */
    public function GetStatusRecords(): array {
        return $this->statusRecords;
    }

    /**
     * Returns an array of ColumnInfo objects that contain information
     * about the columns of a table response to a 'select' query.
     *
     * @return ColumnInfo[]
     */
    public function GetColumnInfo(): array {
        return $this->columnInfoRecords;
    }
}
