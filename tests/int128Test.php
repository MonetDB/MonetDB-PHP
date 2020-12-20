<?php

use PHPUnit\Framework\TestCase;
use MonetDB\Connection;

require_once(__DIR__. '../../src/include.php');

final class int128Test extends TestCase {
    /**
     * @var Connection
     */
    public static $conn;

    public static function setUpBeforeClass(): void
    {
        self::$conn = new Connection("127.0.0.1", 50000, "monetdb", "monetdb", "myDatabase");
    }

    public function testStartTransaction(): void 
    {
        $res = self::$conn->Query("START TRANSACTION");

        $this->assertCount(0, $res);
    }

    public function testBigIntTable(): void 
    {
        self::$conn->Query("drop table if exists php_int128");
        $res = self::$conn->Query("CREATE TABLE php_int128 (i HUGEINT);");

        $this->assertCount(0, $res);
    }

    public function testInsertBigInt(): void 
    {
        $res = self::$conn->Query("INSERT INTO php_int128 VALUES (123456789098765432101234567890987654321);");

        $this->assertCount(0, $res);
    }

    public function testSelectBigInt(): void 
    {
        $res = self::$conn->QueryFirst("SELECT * FROM php_int128");

        $this->assertEquals($res["i"], "123456789098765432101234567890987654321");
    }
}
