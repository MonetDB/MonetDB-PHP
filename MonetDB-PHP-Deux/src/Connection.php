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
 * Class for encapsulating a connection to a MonetDB server.
 */
class Connection {
    /**
     * The maximal size of a packet to be sent over to the server through TCP.
     * 
     * @var integer
     */
    private const MAX_PACKET_SIZE = 8190;

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
     * @var Response
     */
    private $response;

    /**
     * Create a new connection to a MonetDB database.
     *
     * @param string $host The host of the database. Use '127.0.0.1' if the DB is on the same machine.
     * @param integer $port The port of the database. For MonetDB this is usually 50000.
     * @param string $user The user name.
     * @param string $password The password of the user.
     * @param string $database The name of the datebase to connect. Don't forget to release and start it.
     * @param string $hash Optional. The preferred hash algorithm to be used for exchanging the password.
     * It has to be supported by both the server and PHP. The default is 'SHA256'.
     */
    function __construct(string $host, int $port, string $user, string $password, string $database,
            string $hash = "SHA256") {

        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) {
            throw new MonetException("Unable to create socket. Received error: "
                .socket_strerror(socket_last_error()));
        }

        if (!socket_connect($this->socket, $host, $port)) {
            throw new MonetException("Unable to connect to remote host '{$host}:{$port}'."
                ." Received error: ".socket_strerror(socket_last_error()));
        }

        $this->response = new Response($this->socket);
        $challenge = $this->response->GetServerChallenge();
    }

    /**
     * Call this at the beginning of all public methods.
     */
    private function CheckIfClosed()
    {
        if ($this->socket == false) {
            throw new MonetException("Tried to use a 'Connection' object that has been closed already.");
        }
    }

    /**
     * Close the connection
     */
    public function Close()
    {
        if ($this->socket == false) {
            return;
        }

        @socket_close($this->socket);
        $this->socket = false;
    }
}
