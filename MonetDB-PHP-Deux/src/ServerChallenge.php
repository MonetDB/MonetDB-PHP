<?php

namespace MonetDB;


class ServerChallenge {
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
     * List of hash algo names, which are supported for hashing
     * the "password hash" with the salt.
     *
     * @var string[]
     */
    private $supportedSaltHashes;

    /**
     * The name of the algorithm for the password hash.
     *
     * @var string
     */
    private $passwordHash;
}
