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

    /**
     * Status records that tell information about the one
     * or more queries passed to the server and executed.
     *
     * @var StatusRecord[]
     */
    private $statusRecords;

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
        $this->columnNames = null;
        $this->inputStream = $inputStream;
        $this->isDiscarded = false;
        $this->rawCurrentRow = null;
        $this->rowIndex = 0;
        $this->statusRecords = [];
        
        try {
            while(true) {
                $headLine = $this->inputStream->ReadUntilString("\n");

                if ($headLine == Connection::MSG_PROMPT) {
                    if (!$this->inputStream->EOF()) {
                        throw new MonetException("Invalid response from MonetDB. PROMPT packet without closing bit set in header.");
                    }

                    $this->inputStream->Discard();
                    $this->isDiscarded = true;
                    $this->inputStream = null;

                    return;
                }
                elseif (strlen($headLine) > 1) {
                    $first = $headLine[0];
                    $second = $headLine[1];

                    if ($first == Connection::MSG_QUERY) {
                        if ($second == InputStream::Q_TABLE) {
                            $this->statusRecords[] = new StatusRecord($second, $headLine);
                            
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
                                        substr($line, 2, -8)
                                    );
                                }
                            }

                            return;
                        }
                        else if ($second == InputStream::Q_BLOCK) {
                            throw new MonetException("Q_BLOCK not yet implemented");
                        }
                        else if ($second == InputStream::Q_PREPARE) {
                            $this->statusRecords[] = new StatusRecord($second, $headLine);
                            return;
                        }
                        else {
                            $this->statusRecords[] = new StatusRecord($second, $headLine);
                            continue;
                        }
                    } else if ($first == Connection::MSG_INFO) {
                        throw new MonetException("Error from MonetDB: ".substr($headLine, 1));
                    } else {
                        throw new MonetException("Invalid response from MonetDB:\n\n{$headLine}\n");
                    }
                }
            }
        } catch (\Exception $ex) {
            /*
                In case of any error, MonetDB purges its
                session, including the prepared statements.
            */
            $this->connection->ClearPsCache();
            throw $ex;
        }
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

        if ($this->inputStream != null) {
            if ($this === $this->inputStream->GetCurrentResponse()) {
                $this->inputStream->Discard();
            }
        }
        
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
                Can't use trim, because that would remove
                quotes from the data at the end of the field.
            */
            else if (@$field[0] === '"') {
                $field = @stripcslashes(substr($field, 1, -1));
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

        if (@$this->rawCurrentRow[0] !== "[") {
            /*
                In case of any error, MonetDB purges its
                session, including the prepared statements.
            */
            $this->connection->ClearPsCache();

            throw new MonetException("Invalid response from MonetDB. Expected a table row, "
                ."received something else:\n{$this->rawCurrentRow}\n");
        }

        return array_combine(
            $this->columnNames,
            $this->ParseRow(
                substr($this->rawCurrentRow, 2, -3)
            )
        );
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
        return $this->rowIndex;
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

        try {
            if ($this->inputStream->EOF()) {
                $this->Discard();
            } else {
                $this->rawCurrentRow = $this->inputStream->ReadUntilString("\n");
            }
        } catch (\Exception $ex) {
            /*
                In case of any error, MonetDB purges its
                session, including the prepared statements.
            */
            $this->connection->ClearPsCache();
            throw $ex;
        }
        
        $this->rowIndex++;
    }

    /**
     * Part of the "Iterator" interface.
     * Can't rewind a TCP stream
     * But this is also called at the very beginning.
     * 
     * @ignore
     */
    public function rewind() {
        if ($this->isDiscarded) {
            return;
        }

        try {
            if ($this->inputStream->EOF()) {
                $this->Discard();
            } else {
                $this->rawCurrentRow = $this->inputStream->ReadUntilString("\n");
            }
        } catch (\Exception $ex) {
            /*
                In case of any error, MonetDB purges its
                session, including the prepared statements.
            */
            $this->connection->ClearPsCache();
            throw $ex;
        }
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

        try {
            if ($this->inputStream->EOF()) {
                $this->Discard();
                return null;
            } else {
                $this->rawCurrentRow = $this->inputStream->ReadUntilString("\n");
            }

            if (@$this->rawCurrentRow[0] !== "[") {
                throw new MonetException("Invalid response from MonetDB. Expected a table row, "
                    ."received something else:\n{$this->rawCurrentRow}\n");
            }
        } catch (\Exception $ex) {
            /*
                In case of any error, MonetDB purges its
                session, including the prepared statements.
            */
            $this->connection->ClearPsCache();
            throw $ex;
        }

        return array_combine(
            $this->columnNames,
            $this->ParseRow(
                substr($this->rawCurrentRow, 2, -3)
            )
        );
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
}
