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
 * Class that helps reading and parsing the response of the server.
 */
class Response {
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
     * When reading a apcket, it's possible to read the
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
     * Start to read data on the socket.
     *
     * @param resource $socket
     */
    function __construct($socket)
    {
        $this->socket = $socket;
        $this->packets = [];
        $this->remainder = null;
        $this->responseEnded = false;
    }

    /**
     * A response is composed of one or more packets, where
     * the last packet has a special bit set.
     * Read until that's reached.
     *
     * @return void
     */
    private function ReadNextPacket() {
        if ($this->responseEnded) {
            return;
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

            echo $packet;
            
            /*
                Check for the ending condition
            */
            $characters_read += strlen($packet);
            $this->packets[] = $packet;
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
            $this->packets[count($this->packets)] = substr($packet, 0, $last_packet_length);
        } else {
            $this->remainder = null;
        }
    }

    private function ReadUntilBoundary($boundaryDetectorCallback = null): string
    {
        do {
            $this->ReadNextPacket();

            if ($boundaryDetectorCallback !== null) {
                $position = $boundaryDetectorCallback();
                if ($position >= 0) {

                }
            }

            if ($this->responseEnded) {

            }

        } while(!$this->responseEnded);

        return "";
    }

    public function GetServerChallenge() {
        $raw = $this->ReadUntilBoundary(null);

        echo "\n\n{$raw}\n\n";
    }
}
