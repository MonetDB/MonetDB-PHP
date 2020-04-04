<?php

namespace MonetDB;

/**
 * Class for encapsulating a connection to a MonetDB server.
 */
class Connection {
    /**
     * The maximal size of a packet to be sent over to the server through TCP.
     * 
     * @var int
     */
    public const MAX_PACKET_SIZE = 8190;

    /**
     * Connection socket
     *
     * @var resource|false
     */
    private $socket;

    /**
     * Create a new connection to a MonetDB database.
     *
     * @param string $host The host of the database. Use '127.0.0.1' if the DB is on the same machine.
     * @param integer $port The port of the database. For MonetDB this is usually 50000.
     * @param string $user The user name.
     * @param string $password The password of the user.
     * @param string $database The name of the datebase to connect. Don't forget to release and start it.
     * @param string $hash Optional. The preferred hash algorithm to be used for exchanging the password.
     *                  It has to be supported by both the server and PHP. The default is 'SHA512'.
     */
    function __construct(string $host, int $port, string $user, string $password, string $database, string $hash = "SHA512") {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) {
            throw new MonetException("Unable to create socket. Received error: ".socket_strerror(socket_last_error()));
        }

        if (!socket_connect($this->socket, $host, $port)) {
            throw new MonetException("Unable to connect to remote host '{$host}:{$port}'."
                ." Received error: ".socket_strerror(socket_last_error()));
        }


    }

    private function readPacket() {
        
    }
}
