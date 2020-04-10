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
    const Q_PREPARE = "5";  	// (?) Probably relates to prepared statements
    const Q_BLOCK = "6";        // Continuation of a table, without a header

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
     * An array of the packets. They get immediately removed after their
     * contents got parsed.
     * This array is always naturally indexed: [0, 1, 2 ... n]
     *
     * @var string[]
     */
    private $packets;

    /**
     * When reading a packet, it's possible to read the
     * beginning of the next one. In case that happens
     * the raw data gets into this propery, including
     * the header. Otherwise the value of this property
     * is null.
     *
     * @var string|null
     */
    private $remainder;

    /**
     * A response is composed of one or more packets, where
     * the last packet has a special bit set.
     * When that last bit is detected, this field is set to true.
     *
     * @var bool
     */
    private $responseEnded;

    /**
     * This tells in which packet the cursor
     * is located. The cursor tells what to
     * respond next time to the user.
     *
     * @var int
     */
    private $cursor_packet_index;

    /**
     * This tells the character position of
     * the cursor, inside that packet that
     * is selected by "cursor_packet_index".
     *
     * @var int
     */
    private $cursor_position;

    /**
     * The index of the first packet in the buffer.
     * This will be removed fist.
     *
     * @var int
     */
    private $first_packet_index;

    /**
     * The index of the last packet in the buffer.
     * Used for data manipulation and checking
     * for iteration end condition.
     *
     * @var int
     */
    private $last_packet_index;

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
        $this->packets = [];
        $this->response = null;
        $this->remainder = null;
        $this->responseEnded = false;
        $this->cursor_packet_index = 0;
        $this->cursor_position = 0;
        $this->first_packet_index = 0;
        $this->last_packet_index = -1;
    }

    /**
     * A response is composed of one or more packets, where
     * the last packet has a special bit set.
     * Read one for each call to this method until that's
     * reached.
     * This method can add multiple packets to the buffer.
     *
     * @param bool $discard If true, then don't store the pack in
     * the buffer, just discard them.
     * @return void
     */
    private function ReadNextPacket(bool $discard = false) {
        if ($this->responseEnded) {
            throw new MonetException("Internal error. Tried to call ReadNextPacket() on a response that has ended already.");
        }

        /*
            The size in the packet header means the UTF-8 character count and
            not the byte count. See the 'utf8strlen(...)' function usage here:
                - https://github.com/MonetDB/MonetDB/blob/master/clients/mapiclient/mclient.c
        */
        $characters_read = 0;
        $size = null;
        $packet = "";

        /*
            This is done for the very rare case when a
            single byte remained from a previous package
            reading operation. At least the first 2 bytes
            are required to determine the data length.
        */
        $fragment = null;
        if ($this->remainder !== null) {
            if (mb_strlen($this->remainder, '8bit') < 2) {
                $fragment = $this->remainder;
                $this->remainder = null;
            }
        }

        do {
            if ($this->remainder === null) {
                /*
                    Do not use the character count in the max length parameter of "socket_read"
                    when polling for the data, because that parameter expects byte count,
                    and not UTF-8 character count. Use instead the maximal theoretical
                    size of a packet: 32768 + 2
                */
                $rawData = socket_read($this->socket, 32770, PHP_BINARY_READ);
                if ($rawData === false) {
                    throw new MonetException("Connection to server lost. Received error: "
                        .socket_strerror(socket_last_error()));
                }

                /*
                    Add the single byte (see above)
                */
                if ($fragment != null) {
                    $rawData = $fragment.$rawData;
                    $fragment = null;
                }
            } else {
                $rawData = $this->remainder;
                $this->remainder = null;                
            }

            /*
                If the size hasn't been parsed out of the header
                yet, then do it.
            */
            if ($size == null) {
                $unpacked = unpack("vshort", $rawData);
                if ($unpacked === false) {
                    throw new MonetException("Connection to server lost. (invalid header)");
                }

                $short = $unpacked["short"];
                if ($short & 1) {
                    $this->responseEnded = true;
                }

                $size = $short >> 1;
                $packet = mb_substr($rawData, 2, null, '8bit');
            } else {
                $packet = $rawData;
            }

            /*
                Check for the ending condition
            */
            $characters_read += strlen($packet);
            if (!$discard) {
                $this->packets[++$this->last_packet_index] = $packet;
            }
            
            if (defined("MonetDB-PHP-Deux-DEBUG")) {
                echo "IN:\n".addcslashes($packet, "\"'\\\r\n\t")."\n";
            }
        } while ($characters_read < $size);

        /*
            In case the beginning of the next packet is read, put that into
            the remainder field.
        */
        if ($characters_read > $size) {
            $remainder_length = $characters_read - $size;
            $last_packet_length = strlen($packet) - $remainder_length;
            $this->remainder = substr($packet, $last_packet_length);

            // Remove it from last packet
            if (!$discard) {
                $this->packets[$this->last_packet_index] = substr($packet, 0, $last_packet_length);
            }
        } else {
            $this->remainder = null;
        }
    }

    /**
     * Read the challenge line from the server.
     *
     * @return ServerChallenge
     */
    public function GetServerChallenge(): ServerChallenge {
        return new ServerChallenge(
            $this->ReadFullResponse()
        );
    }

    /**
     * Reads a complete string response from the server.
     * Use this only when you expect a smaller message.
     *
     * @return string
     */
    public function ReadFullResponse(): string
    {
        while(!$this->responseEnded) {
            $this->ReadNextPacket();
        }

        $response = implode($this->packets);
        $this->Discard();

        return $response;
    }

    /**
     * Make sure that the user won't mess up the one and only TCP stream.
     */
    private function CheckIfOldResponseDiscarded() {
        if ($this->response !== null) {
            if (!$this->response->IsDiscarded()) {
                throw new MonetException("You tried to start a new query before reading through "
                    ."the previous one. If you wish to avoid this problem in the future, then there "
                    ."are three solutions.\nFirst, you can call the 'Discard()' method on the "
                    ."'Response' object, which reads through the data and discards all.\nSecond, "
                    ."if you wish to execute multiple queries in parallel, then you have to "
                    ."create multiple instances of the 'Connection' class for them.\nThird, if you "
                    ."would not like to use multiple parallel connections and neither want to read "
                    ."through a voluminous response, then you can close the current connection and "
                    ."create a new one.\n");
            }
        }
    }

    /**
     * Read the minimal amount of data required, and
     * return a "Response" object.
     *
     * @return Response
     */
    public function ReceiveResponse(): Response {
        $this->CheckIfOldResponseDiscarded();

        $this->response = new Response($this->connection, $this);
        return $this->response;
    }

    /**
     * Reads the data either until the first occurance of the
     * given string, or until the end of the message is reached.
     * Removes the returned data from the packet buffer.
     *
     * @param string $until
     * @return string
     */
    public function ReadUntilString(string $until): string {
        $pos = false;
        $cursor = $this->cursor_packet_index - 1;
        $start_pos = $this->cursor_position;

        do {
            $cursor++;
            if (!isset($this->packets[$cursor])) {
                /*
                    This can add multiple packets (But at least one)
                    It won't add packets from a next message. Those are put into
                    the "remainder" field.
                    If responseEnded==true, then it ended at the last packet.
                */
                $this->ReadNextPacket();
            }

            $pos = strpos($this->packets[$cursor], $until, $start_pos);
            $start_pos = 0;
        } while (
            $pos === false
            && (!$this->responseEnded || $cursor < $this->last_packet_index)
        );

        if ($pos === false) {
            /*
                Not found.
                The cursor has to be on the last packet.
                Move the position to the end of it, to include
                everything.
            */
            $pos = max(strlen($this->packets[$cursor]) - 1, 0);
        }

        /*
            The terminating string has been found in a packet.
        */
        if ($this->cursor_packet_index == $cursor) {
            /*
                We are still in the same packet
            */
            $response = substr(
                $this->packets[$cursor],
                $this->cursor_position,
                $pos - $this->cursor_position + 1
            );

            $this->cursor_position = $pos + 1;
        } else if ($this->cursor_packet_index + 1 == $cursor) {
            /*
                We moved 1 packet forward. They are neighboring.
            */
            $response = substr(
                $this->packets[$this->cursor_packet_index],
                $this->cursor_position
            ).substr(
                $this->packets[$cursor],
                0,
                $pos + 1
            );

            $this->cursor_position = $pos + 1;
            unset($this->packets[$this->cursor_packet_index]);
            $this->cursor_packet_index++;
        } else {
            /*
                There are intermediate packets between the
                start and the endpoint.
            */
            $parts = [];

            $parts[] = substr(
                $this->packets[$this->cursor_packet_index],
                $this->cursor_position
            );
            unset($this->packets[$this->cursor_packet_index]);

            for($i = $this->cursor_packet_index + 1; $i < $cursor; $i++) {
                $parts[] = $this->packets[$i];
                unset($this->packets[$i]);
            }

            $parts[] = substr(
                $this->packets[$cursor],
                0,
                $pos + 1
            );

            $response = implode($parts);
            $this->cursor_position = $pos + 1;
            $this->cursor_packet_index = $cursor;
        }

        return $response;
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

        return substr($response, 0, strlen($msg)) === $msg;
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

    /**
     * Read and discard all data from the current message.
     * Reset the state of the stream. (Except remainder.)
     */
    public function Discard() {
        while (!$this->responseEnded) {
            $this->ReadNextPacket(true);
        }

        if ($this->response !== null) {
            $currentResponse = $this->response;
            $this->response = null; // To avoid the callback
            $currentResponse->Discard();
        }
        
        $this->packets = [];
        $this->response = null;
        $this->responseEnded = false;
        $this->cursor_packet_index = 0;
        $this->cursor_position = 0;
        $this->first_packet_index = 0;
        $this->last_packet_index = -1;
    }

    /**
     * Returns true if the response has ended, and there's
     * no more unprocessed data in the buffer, false otherwise.
     *
     * @return boolean
     */
    public function EOF(): bool {
        if (!$this->responseEnded) {
            return false;
        }

        if (count($this->packets) < 1) {
            return true;
        }

        return $this->cursor_packet_index >= $this->last_packet_index
            && $this->cursor_position >= strlen($this->packets[$this->last_packet_index]);
    }
}
