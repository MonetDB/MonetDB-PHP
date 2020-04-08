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
 * Class for encapsulating a connection to a MonetDB server.
 */
class Connection {
    /**
     * The maximal size of a packet to be sent over to the server through TCP.
     * 
     * @var integer
     */
    private const MAX_PACKET_SIZE = 8190;

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
     * DB server host name
     *
     * @var string
     */
    private $host;

    /**
     * DB server port
     *
     * @var int
     */
    private $port;

    /**
     * DB user name
     *
     * @var string
     */
    private $user;

    /**
     * DB user password
     *
     * @var string
     */
    private $password;

    /**
     * The name of the database where the user would like
     * to connect.
     *
     * @var string
     */
    private $database;

    /**
     * Lower case name of the hash algorithm, used
     * for hashing the salted password hash.
     *
     * @var string
     */
    private $saltedHashAlgo;

    /**
     * Connection socket
     *
     * @var resource|false
     */
    private $socket;

    /**
     * The classe keeping track of the responses
     * from the server.
     *
     * @var InputStream
     */
    private $inputStream;

    /**
     * Create a new connection to a MonetDB database.
     * 
     * @param string $host The host of the database. Use '127.0.0.1' if the DB is on the same machine.
     * @param integer $port The port of the database. For MonetDB this is usually 50000.
     * @param string $user The user name.
     * @param string $password The password of the user.
     * @param string $database The name of the datebase to connect. Don't forget to release and start it.
     * @param string $saltedHashAlgo Optional. The preferred hash algorithm to be used for exchanging the password.
     * It has to be supported by both the server and PHP. This is only used for the salted hashing.
     * Another stronger algorithm is used first (usually SHA512). Default is "SHA1".
     * @param bool $syncTimeZone If true, then tells the clients time zone offset to the server,
     * which will convert all timestamps is case there's a difference. If false, then the timestamps
     * will end up on the server unmodified. Default is true.
     * @param int $maxReplySize The maximal number of tuples returned in a response. Set it to NULL to
     * avoid configuring the server, but that might have a default for it. Default is 1000000.
     */
    function __construct(string $host, int $port, string $user, string $password, string $database,
            string $saltedHashAlgo = "SHA1", bool $syncTimeZone = true, ?int $maxReplySize = 1000000) {
        
        if (mb_internal_encoding() !== "UTF-8") {
            throw new Exception("For security reasons, this library is only allowed to be used in "
                ."PHP environments in which the multi-byte support is enabled and the default "
                ."character set is 'UTF-8'. See: https://www.php.net/manual/en/function.mb-internal-encoding.php");
        }

        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) {
            throw new MonetException("Unable to create socket. Received error: "
                .socket_strerror(socket_last_error()));
        }

        if (!socket_connect($this->socket, $host, $port)) {
            throw new MonetException("Unable to connect to remote host '{$host}:{$port}'."
                ." Received error: ".socket_strerror(socket_last_error()));
        }

        $this->host = trim($host);
        $this->port = (int)$port;
        $this->user = trim($user);
        $this->password = trim($password);
        $this->database = trim($database);
        $this->saltedHashAlgo = strtolower(trim($saltedHashAlgo));

        $this->inputStream = new InputStream($this->socket);

        /*
            Authenticate
        */
        $this->Authenticate($user, $password);

        /*
            Syncronize time zone
        */
        if ($syncTimeZone) {
            $time_offset = date('P');
            $this->Query("SET TIME ZONE INTERVAL '{$time_offset}' HOUR TO MINUTE");
        }

        /*
            Configure the maximal number of tuples returned
            in a response.
        */
        if ($maxReplySize !== null) {
            $this->Command("reply_size {$maxReplySize}");
        }
    }

    /**
     * Authenticate with the server.
     * Throws exception in case of a failure.
     */
    private function Authenticate() {
        for ($i = 0; $i < 10; $i++) {
            $challenge = $this->inputStream->GetServerChallenge();
            $pwHash = $challenge->HashPassword($this->password, $this->saltedHashAlgo);
            $upperSaltHash = strtoupper($this->saltedHashAlgo);

            $this->Write("BIG:{$this->user}:{{$upperSaltHash}}{$pwHash}:sql:{$this->database}:");
            
            $inputStream = $this->inputStream->ReadFullResponse();
            if (InputStream::IsResponse($inputStream, self::MSG_REDIRECT, "mapi:merovingian:")) {
                /*
                    A "Merovingian" redirect.
                    This probably happens because inactive servers are stopped after a while
                    by the main process. When you successfully authenticate with the main
                    process, and your target database is currently stopped, then it starts
                    up the new server on a new process. But then you have to repeat the
                    authentication with the newly started process. (In this case the main
                    process acts like a proxy, and it forwards the requests and the
                    responses.)
                */
                continue;
            }

            if ($inputStream == self::MSG_PROMPT) {
                // Successful authentication (received an empty string as a prompt)
                return;
            }

            if (InputStream::IsResponse($inputStream, self::MSG_INFO, "InvalidCredentialsException:")) {
                throw(new MonetException("Authentication Failed. Invalid credentials."));
            }

            throw(new MonetException("Authentication Failed. Unexpected inputStream from server:\n{$inputStream}\n"));
        }

        throw(new MonetException("Authentication Failed. Too many Merovingian redirects."));
    }

    /**
     * Call this at the beginning of all public methods.
     */
    private function CheckIfClosed()
    {
        if ($this->socket === false) {
            throw new MonetException("Tried to use a 'Connection' object that has been closed already.");
        }
    }

    /**
     * Close the connection
     */
    public function Close()
    {
        if ($this->socket === false) {
            return;
        }

        @socket_close($this->socket);
        $this->socket = false;
    }

    private function Write(string $msg)
    {
        $this->CheckIfClosed();

        $chunks = str_split($msg, self::MAX_PACKET_SIZE);

        foreach($chunks as $index => $chunk) {
            $header = strlen($chunk) << 1;

            if ($index == count($chunks) - 1) {
                // Last chunk
                $header |= 1;
            }

            $chunk = pack("v", $header).$chunk;

            do {
                $inputStream = socket_write($this->socket, $chunk);
                if ($inputStream === false) {
                    throw new MonetException("Unable to send data to server. Connection lost. Received error: "
                        .socket_strerror(socket_last_error()));
                }

                /*
                    It's not guaranteed that 'socket_write' pushes
                    out all of the data. It returns the number of
                    bytes it has actually transmitted.
                */
                if ($inputStream === 0) {
                    /*
                        No bytes have been written, which probably means that the
                        server has a high load. Sleep 100 ms before continue.
                    */
                    usleep(100000);
                    continue;
                }

                $chunk = mb_substr($chunk, $inputStream, null, '8bit');
            } while(strlen($chunk) > 0);
        }

        if (defined("MonetDB-PHP-Deux-DEBUG")) {
            echo "OUT:\n".str_replace("\n", " ", trim($msg))."\n";
        }
    }

    /**
     * Execute an SQL query and return its response.
     * For 'select' queries the response can be iterated
     * using a 'foreach' statement.
     * 
     * @param string $sql
     * @return Response
     */
    public function Query(string $sql): Response
    {
        $this->Write("s{$sql}\n;");
    
        return $this->inputStream->ReceiveResponse();
    }

    /**
     * Execute an SQL query and return only the first
     * row as an associative array. If there is more
     * data on the stream, then discard all.
     * Returns null if the query has empty result.
     * 
     * @param string $sql
     * @return string[]|null
     */
    public function QueryFirst(string $sql): ?array
    {
        $this->Write("s{$sql}\n;");
    
        $response = $this->inputStream->ReceiveResponse();
        $row = $response->Fetch();
        if (!$response->IsDiscarded()) {
            $response->Discard();
        }
        
        return $row;
    }

    /**
     * Send a 'command' to MonetDB. Commands are used for
     * configuring the database, for example setting the
     * maximal response size.
     *
     * @param string $command
     * @return Response
     */
    public function Command(string $command): Response
    {
        $this->Write("X{$command}");
    
        return $this->inputStream->ReceiveResponse();
    }
}
