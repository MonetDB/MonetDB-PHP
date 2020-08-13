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
     * The ID of the result set, which can be used for
     * resuming it, using the "export" command.
     * 
     * @var int
     */
    private $resultID = null;

    /**
     * Query ID. A global ID which is also used in
     * functions such as sys.querylog_catalog().
     *
     * @var int
     */
    private $queryID = null;

    /**
     * The last automatically generated ID by
     * an insert statement. (Usually auto_increment)
     * NULL if none.
     *
     * @var int
     */
    private $lastInsertID = null;

    /**
     * The time the server spent on executing
     * the query. In milliseconds.
     *
     * @var float
     */
    private $queryTime = null;

    /**
     * SQL optimizer time in milliseconds.
     *
     * @var float
     */
    private $sqlOptimizerTime = null;

    /**
     * MAL optimizer time in milliseconds.
     *
     * @var float
     */
    private $malOptimizerTime = null;

    /**
     * The number of rows updated or inserted.
     *
     * @var int
     */
    private $affectedRows = null;

    /**
     * The total number of rows in the result set.
     * This includes those rows too, which are not
     * in the current response.
     *
     * @var int
     */
    private $totalRowCount = null;

    /**
     * The number of rows (tuples) in the current
     * response only.
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
     * Populated after "start transaction", "commit" or "rollback".
     * Tells whether the current session is in auto-commit mode
     * or not.
     *
     * @var bool
     */
    private $autoCommitState = null;

    /**
     * Column count. If the response contains tabular data,
     * then this tells the number of columns.
     *
     * @var int
     */
    private $columnCount = null;

    /**
     * The index (offset) of the first row in
     * a block response. (For an "export" command.)
     *
     * @var int
     */
    private $exportOffset = null;

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
            $this->queryTypeDescription = "Data response";
            $fields = $this->ParseFields($line, 8);
            $this->resultID = (int)$fields[0];
            $this->totalRowCount = (int)$fields[1];
            $this->columnCount = (int)$fields[2];
            $this->rowCount = (int)$fields[3];
            $this->queryID  = (int)$fields[4];
            $this->queryTime = $fields[5] / 1000;
            $this->malOptimizerTime = $fields[6] / 1000;
            $this->sqlOptimizerTime = $fields[7] / 1000;
        } else if ($queryType == InputStream::Q_BLOCK) {
            $this->queryType = "block";
            $this->queryTypeDescription = "Continue a data response";
            $fields = $this->ParseFields($line, 4);
            $this->resultID = (int)$fields[0];
            $this->columnCount = (int)$fields[1];
            $this->rowCount = (int)$fields[2];
            $this->exportOffset = (int)$fields[3];
        } else if ($queryType == InputStream::Q_CREATE) {
            // "SET TIME ZONE INTERVAL ..." returns this as well.
            $this->queryType = "schema";
            $this->queryTypeDescription = "Stats only (schema)";
            $fields = $this->ParseFields($line, 2);
            $this->queryTime = $fields[0] / 1000;
            $this->malOptimizerTime = $fields[1] / 1000;
        } else if ($queryType == InputStream::Q_UPDATE) {
            $this->queryType = "update";
            $this->queryTypeDescription = "Update or insert rows";
            $fields = $this->ParseFields($line, 6);
            $this->affectedRows = (int)$fields[0];
            $this->lastInsertID = (int)$fields[1];
            if ($this->lastInsertID < 0) {
                $this->lastInsertID = null;
            }
            $this->queryID = (int)$fields[2];
            $this->queryTime = $fields[3] / 1000;
            $this->malOptimizerTime = $fields[4] / 1000;
            $this->sqlOptimizerTime = $fields[5] / 1000;
        } else if ($queryType == InputStream::Q_TRANSACTION) {
            $this->queryType = "transaction";
            $fields = $this->ParseFields($line, 1);
            if ($fields[0] === 'f') {
                $this->queryTypeDescription = "Transaction started";
                $this->autoCommitState = false;
            } else {
                $this->queryTypeDescription = "Transaction ended";
                $this->autoCommitState = true;
            }
        } else if ($queryType == InputStream::Q_PREPARE) {
            $this->queryType = "prepared_statement";
            $this->queryTypeDescription = "A prepared statement has been created.";
            $fields = $this->ParseFields($line, 4);
            $this->preparedStatementID = (int)$fields[0];
            $this->totalRowCount = (int)$fields[1];
            $this->columnCount = (int)$fields[2];
            $this->rowCount = (int)$fields[3];
        } else {
            throw new MonetException("Unknown reply form MonetDB:\n{$line}\n");
        }

        if (defined("MonetDB-PHP-DEBUG")) {
            echo "\n".$this->GetAsText()."\n\n";
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
        $parts = explode(" ", substr(trim($line), 3));

        // More fields is not a problem. Later protocol versions can add new ones.
        if (count($parts) < $count) {
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
    public function GetQueryTime(): ?float
    {
        return $this->queryTime;
    }

    /**
     * SQL optimizer time in milliseconds.
     *
     * @return float|null
     */
    public function GetSqlOptimizerTime(): ?float
    {
        return $this->sqlOptimizerTime;
    }

    /**
     * MAL optimizer time in milliseconds.
     *
     * @return float|null
     */
    public function GetMalOptimizerTime(): ?float
    {
        return $this->malOptimizerTime;
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
     * The total number of rows in the result set.
     * This includes those rows too, which are not
     * in the current response.
     *
     * @return integer|null
     */
    public function GetTotalRowCount(): ?int
    {
        return $this->totalRowCount;
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

        if ($this->queryID !== null) {
            $response[] = "Query ID (global): {$this->queryID}";
        }

        if ($this->resultID !== null) {
            $response[] = "Result ID: {$this->resultID}";
        }

        if ($this->lastInsertID !== null) {
            $response[] = "Last insert ID: {$this->lastInsertID}";
        }

        if ($this->queryTime !== null) {
            $response[] = "Query time: {$this->queryTime} ms";
        }

        if ($this->sqlOptimizerTime !== null) {
            $response[] = "SQL optimizer time: {$this->sqlOptimizerTime} ms";
        }

        if ($this->malOptimizerTime !== null) {
            $response[] = "Mal optimizer time: {$this->malOptimizerTime} ms";
        }

        if ($this->affectedRows !== null) {
            $response[] = "Affected rows: {$this->affectedRows}";
        }

        if ($this->totalRowCount !== null) {
            $response[] = "Total rows: {$this->totalRowCount}";
        }

        if ($this->rowCount !== null) {
            $response[] = "Rows in current response: {$this->rowCount}";
        }

        if ($this->columnCount !== null) {
            $response[] = "Column count: {$this->columnCount}";
        }

        if ($this->autoCommitState !== null) {
            $response[] = "Auto-commit state: {$this->autoCommitState}";
        }

        if ($this->preparedStatementID !== null) {
            $response[] = "Prepared statement ID: {$this->preparedStatementID}";
        }

        if ($this->exportOffset !== null) {
            $response[] = "Export offset: {$this->exportOffset}";
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

    /**
     * Returns the ID of the result set that
     * is returned for a query. It is stored on
     * the server for this session, and parts
     * of it can be queried using the "export"
     * command.
     *
     * @return integer|null
     */
    public function GetResultID(): ?int {
        return $this->resultID;
    }

    /**
     * Available after "start transaction", "commit" or "rollback".
     * Tells whether the current session is in auto-commit mode
     * or not.
     *
     * @return boolean|null
     */
    public function GetAutoCommitState(): ?bool
    {
        return $this->autoCommitState;
    }

    /**
     * The number of rows (tuples) in the current
     * response only.
     *
     * @return integer|null
     */
    public function GetRowCount(): ?int
    {
        return $this->rowCount;
    }

    /**
     * Column count. If the response contains tabular data,
     * then this tells the number of columns.
     *
     * @return integer|null
     */
    public function GetColumnCount(): ?int
    {
        return $this->columnCount;
    }

    /**
     * Query ID. A global ID which is also used in
     * functions such as sys.querylog_catalog().
     *
     * @return integer|null
     */
    public function GetQueryID(): ?int
    {
        return $this->queryID;
    }

    /**
     * The last automatically generated ID by
     * an insert statement. (Usually auto_increment)
     * NULL if none.
     *
     * @return integer|null
     */
    public function GetLastInsertID(): ?int
    {
        return $this->lastInsertID;
    }

    /**
     * The index (offset) of the first row in
     * a block response. (For an "export" command.)
     *
     * @return integer|null
     */
    public function GetExportOffset(): ?int
    {
        return $this->exportOffset;
    }
}
