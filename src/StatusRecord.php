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

/**
 * This class shares the information returned by MonetDB
 * about the executed queries. Like execution time,
 * number of rows affected, etc.
 * Note that only specific fields are populated for specific
 * queries, the others remain NULL.
 */
class StatusRecord {
    /**
     * This tells which kind of query triggered
     * this response.
     *
     * @var string
     */
    private $queryType = null;

    /**
     * This is the human-readable description for the
     * query type.
     *
     * @var string
     */
    private $queryTypeDescription = null;

    /**
     * The time the server spent on executing
     * the query. In milliseconds.
     *
     * @var float
     */
    private $executionTime = null;

    /**
     * The time it took to parse and optimize
     * the query.
     *
     * @var float
     */
    private $queryParsingTime = null;

    /**
     * The number of rows updated or inserted.
     *
     * @var int
     */
    private $affectedRows = null;

    /**
     * The number of rows in the response.
     *
     * @var int
     */
    private $rowCount = null;

    /**
     * If the query created a prepared statement, then
     * this contains its ID, which can be used in an
     * 'EXECUTE' statement.
     *
     * @var int
     */
    private $preparedStatementID = null;

    /**
     * The server always responds with a status line to a query,
     * which tells data like the time spent on it, or the number
     * of records affected, etc.
     *
     * @ignore
     * @param integer $queryType
     * @param string $line
     */
    function __construct(int $queryType, string $line)
    {
        if ($queryType == InputStream::Q_TABLE) {
            $this->queryType = "table";
            $this->queryTypeDescription = "Select query";
            $fields = $this->ParseFields($line, 8);
            $this->rowCount = (int)$fields[1];
            $this->executionTime = $fields[4] / 1000;
            $this->queryParsingTime = $fields[5] / 1000;
        } else if ($queryType == InputStream::Q_CREATE) {
            $this->queryType = "schema";
            $this->queryTypeDescription = "Modify schema";
            $fields = $this->ParseFields($line, 2);
            $this->executionTime = $fields[0] / 1000;
            $this->queryParsingTime = $fields[1] / 1000;
        } else if ($queryType == InputStream::Q_UPDATE) {
            $this->queryType = "update";
            $this->queryTypeDescription = "Update or insert rows";
            $fields = $this->ParseFields($line, 6);
            $this->affectedRows = (int)$fields[0];
            $this->executionTime = $fields[2] / 1000;
            $this->queryParsingTime = $fields[3] / 1000;
        } else if ($queryType == InputStream::Q_TRANSACTION) {
            $this->queryType = "transaction";
            $fields = $this->ParseFields($line, 1);
            if ($fields[0] === 'f') {
                $this->queryTypeDescription = "Transaction started";
            } else {
                $this->queryTypeDescription = "Transaction ended";
            }
        } else if ($queryType == InputStream::Q_PREPARE) {
            $this->queryType = "prepared_statement";
            $this->queryTypeDescription = "A prepared statement has been created.";
            $fields = $this->ParseFields($line, 4);
            $this->preparedStatementID = (int)$fields[0];
        } else {
            throw new MonetException("Unknown reply form MonetDB:\n{$line}\n");
        }

        if (defined("MonetDB-PHP-Deux-DEBUG")) {
            echo "\n".$this->GetAsText()."\n";
        }
    }

    /**
     * Helper function to parse out the fields in string format
     * and do basic validation.
     *
     * @param string $line
     * @param integer $count
     * @return string[]
     */
    private function ParseFields(string $line, int $count): array {
        $parts = explode(" ", substr($line, 3, -1));
        if (count($parts) != $count) {
            throw new MonetException("Invalid response from MonetDB. Status response has invalid number of "
                ."fields. '{$count}' is expected:\n{$line}\n");
        }

        return $parts;
    }

    /**
     * Returns a short string which identifies
     * the type of the query.
     *
     * @return string
     */
    public function GetQueryType(): string
    {
        return $this->queryType;
    }

    /**
     * Returns a user-friendly text which describes
     * the effect of the query.
     *
     * @return string
     */
    public function GetDescription(): string
    {
        return $this->queryTypeDescription;
    }

    /**
     * The time the server spent on executing
     * the query. In milliseconds.
     *
     * @return float|null
     */
    public function GetExecutionTime(): ?float
    {
        return $this->executionTime;
    }

    /**
     * The time it took to parse and optimize
     * the query.
     *
     * @return float|null
     */
    public function GetQueryParsingTime(): ?float
    {
        return $this->queryParsingTime;
    }

    /**
     * The number of rows updated or inserted.
     *
     * @return integer|null
     */
    public function GetAffectedRows(): ?int
    {
        return $this->affectedRows;
    }

    /**
     * The number of rows in the response.
     *
     * @return integer|null
     */
    public function GetRowCount(): ?int
    {
        return $this->rowCount;
    }

    /**
     * Get a description of the status response in
     * a human-readable format.
     *
     * @return string
     */
    public function GetAsText(): string {
        $response = [];

        if ($this->queryTypeDescription !== null) {
            $response[] = "Action: {$this->queryTypeDescription}";
        }

        if ($this->executionTime !== null) {
            $response[] = "Execution time: {$this->executionTime} ms";
        }

        if ($this->queryParsingTime !== null) {
            $response[] = "Query parsing time: {$this->queryParsingTime} ms";
        }

        if ($this->affectedRows !== null) {
            $response[] = "Affected rows: {$this->affectedRows}";
        }

        if ($this->rowCount !== null) {
            $response[] = "Rows: {$this->rowCount}";
        }

        if ($this->preparedStatementID !== null) {
            $response[] = "Prepared statement ID: {$this->preparedStatementID}";
        }

        return implode("\n", $response);
    }

    /**
     * Get a description of the status response in
     * a human-readable format.
     *
     * @ignore
     * @return string
     */
    public function __toString()
    {
        return $this->GetAsText();
    }

    /**
     * Get the ID of a created prepared statement.
     * This ID can be used in an 'EXECUTE' statement,
     * but only in the same session.
     *
     * @return integer|null
     */
    public function GetPreparedStatementID(): ?int {
        return $this->preparedStatementID;
    }
}
