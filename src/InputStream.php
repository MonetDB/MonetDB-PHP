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
 * Class that helps reading and parsing the response of the server.
 */
class InputStream {
    /*
        In case the message type is MSG_QUERY, then as a next character
        this tells the response format.
    */
    const Q_TABLE = "1";        // Table with a header and rows (For a select query)
    const Q_UPDATE = "2";       // INSERT/UPDATE operations (Tells the affected row count)
    const Q_CREATE = "3";       // CREATE/DROP TABLE operations (or without response data)
    const Q_TRANSACTION = "4";  // TRANSACTION
    const Q_PREPARE = "5";  	// Creating a prepared statement
    const Q_BLOCK = "6";        // Continuation of a table, without a header

    /*
        Constraints, received at the beginning of the
        messages of the server. (Or as a complete messsage: prompt)
    */
    const MSG_REDIRECT = '^';
    const MSG_QUERY = '&';
    const MSG_SCHEMA_HEADER = '%';
    const MSG_INFO = '!';
    const MSG_TUPLE = '[';
    const MSG_PROMPT = '';
    
    /**
     * The related connection
     *
     * @var Connection
     */
    private $connection;

    /**
     * Connection socket
     *
     * @var resource
     */
    private $socket;

    /**
     * An array containing the lines in the current
     * response.
     *
     * @var string[]
     */
    private $response_lines;

    /**
     * The index of the current line.
     *
     * @var int
     */
    private $line_cursor;

    /**
     * This tracks the response object, which was last returned to
     * the user. In case the user starts a new query, we have
     * to disable the old Response first, so it won't touch
     * the TCP stream anymore. (This involves reading through
     * and discarding its input data.)
     * If the user wants multiple concurrent queries, then they
     * have to create multiple connections.
     *
     * @var Response|null
     */
    private $response;

    /**
     * Start to read data on the socket.
     *
     * @param resource $socket
     */
    function __construct(Connection $connection, $socket)
    {
        $this->connection = $connection;
        $this->socket = $socket;
        $this->response = null;
        $this->response_lines = null;
        $this->line_cursor = 0;
    }

    /**
     * A response is composed of one or more packets, where
     * the last packet has a special bit set.
     * Read one for each call to this method until that's
     * reached.
     * This method adds exactly one packet to the packet
     * buffer, or waits.
     *
     * @param bool $discard If true, then don't store the pack in
     * the buffer, just discard them.
     * @return void
     */
    public function LoadNextResponse(bool $discard = false) {
        $packets = [];

        do {
            $header = $this->SocketReadExact(2);
            $unpacked = unpack("vshort", $header);
            if ($unpacked === false) {
                throw new MonetException("Connection to server lost. (invalid header)");
            }

            $short = $unpacked["short"];
            $size = $short >> 1;
            if ($size > 0) {
                $packet = $this->SocketReadExact($short >> 1);
                $packets[] = $packet;
            }
        } while(!($short & 1));
        
        $this->response_lines = \mb_split("\n", implode($packets));
        $this->line_cursor = 0;
    }

    /**
     * Reads the exact amount of the data from the
     * socket as requested.
     *
     * @param integer $length
     * @return string
     */
    private function SocketReadExact(int $length): string {
        $read = 0;
        $parts = [];

        do {
            $rawData = socket_read($this->socket, $length - $read, PHP_BINARY_READ);
            if ($rawData === false) {
                throw new MonetException("Connection to server lost. Received error: "
                    .socket_strerror(socket_last_error()));
            }

            $parts[] = $rawData;
            $read += strlen($rawData);
        } while ($read < $length);

        return implode($parts);
    }

    /**
     * Read the challenge line from the server.
     *
     * @return ServerChallenge
     */
    public function GetServerChallenge(): ServerChallenge {
        $this->LoadNextResponse();

        return new ServerChallenge(
            $this->ReadNextLine()
        );
    }

    /**
     * Reads a line from the server.
     * Returns MSG_PROMPT if the message ended.
     *
     * @return string
     */
    public function ReadNextLine(): string
    {
        if (!isset($this->response_lines[$this->line_cursor])) {
            return self::MSG_PROMPT;
        }
        
        $line = $this->response_lines[$this->line_cursor];
        $this->line_cursor++;

        if (defined("MonetDB-PHP-DEBUG")) {
            echo "IN LINE:\n".$this->connection->Escape($line)."\n";
        }
        
        return $line;
    }

    /**
     * Read the minimal amount of data required, and
     * return a "Response" object.
     *
     * @return Response
     */
    public function ReceiveResponse(): Response {
        $this->response = new Response($this->connection, $this);
        return $this->response;
    }

    /**
     * Tests the response from a server if it matches
     * a certain type.
     *
     * @param string $response
     * @param string $type
     * @param string $beginning
     * @return boolean
     */
    public static function IsResponse(string $response, string $type, string $beginning = ""): bool {
        $msg = $type.$beginning;

        return mb_substr($response, 0, mb_strlen($msg)) === $msg;
    }

    /**
     * This tracks the response object, which was last returned to
     * the user. In case the user starts a new query, we have
     * to disable the old Response first, so it won't touch
     * the TCP stream anymore. (This involves reading through
     * and discarding its input data.)
     * If the user wants multiple concurrent queries, then they
     * have to create multiple connections.
     *
     * @return Response|null
     */
    public function GetCurrentResponse(): ?Response {
        return $this->response;
    }
}
