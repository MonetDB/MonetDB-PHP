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
 * This class contains information about the columns of
 * a table response to a 'select' query.
 */
class ColumnInfo {
    /**
     * The name of the table the field belongs to, or
     * the name of a temporary resource if the value
     * is the result of an expression.
     *
     * @var string
     */
    private $tableName;

    /**
     * Field name.
     *
     * @var string
     */
    private $columnName;

    /**
     * The SQL data type of the field.
     *
     * @var string
     */
    private $type;

    /**
     * A length value that can be used for deciding
     * the width of the columns when rendering the
     * response.
     *
     * @var int
     */
    private $length;

    /**
     * Constructor
     *
     * @ignore
     * @param string $tableName
     * @param string $columnName
     * @param string $type
     * @param integer $length
     */
    function __construct(string $tableName, string $columnName, string $type, int $length)
    {
        $this->tableName = $tableName;
        $this->columnName = $columnName;
        $this->type = $type;
        $this->length = $length;
    }

    /**
     * The name of the table the field belongs to, or
     * the name of a temporary resource if the value
     * is the result of an expression.
     *
     * @return string
     */
    public function GetTableName(): string
    {
        return $this->tableName;
    }

    /**
     * Column name.
     *
     * @return string
     */
    public function GetColumnName(): string
    {
        return $this->columnName;
    }

    /**
     * The SQL data type of the field.
     *
     * @return string
     */
    public function GetType(): string
    {
        return $this->type;
    }

    /**
     * A length value that can be used for deciding
     * the width of the columns when rendering the
     * response.
     *
     * @return integer
     */
    public function GetLength(): int
    {
        return $this->length;
    }
}