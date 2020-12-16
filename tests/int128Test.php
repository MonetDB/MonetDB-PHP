<?php

use PHPUnit\Framework\TestCase;
use MonetDB\Connection;

require_once(__DIR__. '../../src/include.php');

final class int128Test extends TestCase {
    public $conn;

    public function setUp(): void
    {
        $this->conn = new Connection("127.0.0.1", 50000, "monetdb", "monetdb", "temp");
    }

    public function testStartTransaction(): void 
    {
        $res = $this->conn->Query("START TRANSACTION");

        $this->assertCount(0, $res);
    }

    public function testBigIntTable(): void 
    {
        $res = $this->conn->Query("CREATE TABLE php_int128 (i HUGEINT);");

        $this->assertCount(0, $res);
    }

    public function testInsertBigInt(): void 
    {
        $res = $this->conn->Query("INSERT INTO php_int128 VALUES (123456789098765432101234567890987654321);");

        $this->assertCount(0, $res);
    }

    public function testSelectBigInt(): void 
    {
        $res = $this->conn->Query("SELECT * FROM php_int128");
        $res_arr = iterator_to_array($res);

        $this->assertEquals($res_arr[1]["i"], "123456789098765432101234567890987654321");
    }

}
?>