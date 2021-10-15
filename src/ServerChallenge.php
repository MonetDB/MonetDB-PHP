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
 * For handling the server challenge response from the
 * server, which is used for the authentication.
 */
class ServerChallenge {
    const BACKEND_MEROVINGIAN = 'merovingian';
    const BACKEND_MONETDB = 'monetdb';
    const BACKEND_MSERVER = 'mserver';

    /**
     * The salt to be used for hashing the password for
     * the authentication.
     *
     * @var string
     */
    private $salt;

    /**
     * One of these:
     * - merovingian: This isn't the proper endpoint. To get there, one
     *      or more redirects are required
     * - monetdb: The endpoint is a MonetDB database
     * - mserver: ?
     *
     * @var string
     */
    private $backend;

    /**
     * Server protocol version
     *
     * @var integer
     */
    private $version;

    /**
     * List of lower case hash algo names, which are supported for hashing
     * the "password hash" with the salt.
     *
     * @var string[]
     */
    private $supportedSaltHashes;

    /**
     * The lower case name of the algorithm for the password hash.
     * (No choice here)
     *
     * @var string
     */
    private $passwordHashAlgo;

    /**
     * Constructor
     *
     * @param string $challengeLine
     */
    function __construct(string $challengeLine)
    {
        $parts = explode(':', $challengeLine);
        $count = count($parts) - 1;     	// Last is always empty
        if ($count < 6) {
            throw new MonetException("Received invalid 'server challenge' string from the server. It"
                ." contains {$count} fields, should be at least 6.");
        }

        $this->salt = trim($parts[0]);
        $this->backend = trim($parts[1]);
        $this->version = (int)$parts[2];
        $this->supportedSaltHashes = explode(',', strtolower(trim($parts[3])));
        $this->passwordHashAlgo = strtolower(trim($parts[5]));

        if (strlen($this->salt) < 8) {
            throw new MonetException("Received invalid 'server challenge' string from the server. "
                ."The salt is too short. (less than 8 characters");
        }

        if (!in_array($this->backend, [
                self::BACKEND_MEROVINGIAN, self::BACKEND_MONETDB, self::BACKEND_MSERVER
            ]))
        {
            throw new MonetException("Received invalid 'server challenge' string from the server. "
                ."Invalid backend field: '$this->backend'");
        }

        if ($this->version < 1) {
            throw new MonetException("Received invalid 'server challenge' string from the server. "
                ."Invalid version field.");
        }

        if (count($this->supportedSaltHashes) < 1) {
            throw new MonetException("Received invalid 'server challenge' string from the server. "
                ."Empty array for salt hashes.");
        }

        if ($this->passwordHashAlgo == "") {
            throw new MonetException("Received invalid 'server challenge' string from the server. "
                ."Empty password hash field.");
        }
    }

    /**
     * The salt to be used for hashing the password for
     * the authentication.
     *
     * @return string
     */
    public function GetSalt(): string
    {
        return $this->salt;
    }

    /**
     * One of these:
     * - merovingian: This isn't the proper endpoint. To get there, one
     *      or more redirects are required
     * - monetdb: The endpoint is a MonetDB database
     * - mserver: ?
     *
     * @return string
     */
    public function GetBackend(): string
    {
        return $this->backend;
    }

    /**
     * Server protocol version
     *
     * @return integer
     */
    public function GetVersion(): int
    {
        return $this->version;
    }

    /**
     * List of hash algo names, which are supported for hashing
     * the "password hash" with the salt.
     *
     * @return string[]
     */
    public function GetSupportedSaltHashes(): array {
        return $this->GetSupportedSaltHashes();
    }

    /**
     * The name of the algorithm for the password hash.
     * (No choice here)
     *
     * @return string
     */
    public function GetPasswordHashAlgo(): string
    {
        return $this->passwordHashAlgo;
    }

    /**
     * Hash a password before sending it to the MonetDB
     * server for authentication.
     *
     * @param string $password User password
     * @param string $saltHashAlgo Lower case hash algorithm name
     */
    public function HashPassword(string $password, string $saltHashAlgo)
    {
        $supported = hash_algos();
        if (!in_array($this->passwordHashAlgo, $supported)) {
            throw new MonetException("The password hash algorith '{$this->passwordHashAlgo}' which was requested "
                ."by the server, is not supported by PHP.");
        }

        if (!in_array($saltHashAlgo, $supported)) {
            throw new MonetException("The salt hash algorith '{$this->passwordHashAlgo}', which was specified "
                ."in a constructor parameter of the 'Connection' class, is not supported by PHP. "
                ."The following algorithms are supported by it: ".implode(', ', $supported));
        }

        if (!in_array($saltHashAlgo, $this->supportedSaltHashes)) {
            throw new MonetException("The salt hash algorith '{$this->passwordHashAlgo}', which was specified "
                ."in a constructor parameter of the 'Connection' class, is not supported by the server. "
                ."The following algorithms are supported by it: ".implode(', ', $this->supportedSaltHashes));
        }

        $hash = hash(
            $saltHashAlgo,
            hash($this->passwordHashAlgo, $password).$this->salt
        );

        return $hash;
    }
}
