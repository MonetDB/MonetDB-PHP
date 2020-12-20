<?php
use PHPUnit\Framework\TestCase;
use MonetDB\Connection;

require_once(__DIR__. '../../src/include.php');

final class dec38Test extends TestCase {
    /**
     * @var Connection
     */
    public static $conn;

    public static function setUpBeforeClass(): void
    {
        self::$conn = new Connection("127.0.0.1", 50000, "monetdb", "monetdb", "myDatabase");
    }

    public function testBigIntTable(): void 
    {
        self::$conn->Query("drop table if exists php_dec38");
        $res = self::$conn->Query("CREATE TABLE php_dec38 (d38_0 DECIMAL(38,0), d38_19 DECIMAL(38,19), d38_38 DECIMAL(38,38));");

        $this->assertCount(0, $res);
    }

    public function testInsertBigInt(): void 
    {
        $res = self::$conn->Query("INSERT INTO php_dec38 VALUES (12345678901234567899876543210987654321, 1234567890123456789.9876543210987654321, .12345678901234567899876543210987654321);");

        $this->assertCount(0, $res);
    }

    public function testSelectBigInt(): void 
    {
        $res = self::$conn->QueryFirst("SELECT * FROM php_dec38");

        $this->assertEquals($res["d38_0"], "12345678901234567899876543210987654321");
        $this->assertEquals($res["d38_19"], "1234567890123456789.9876543210987654321");
        $this->assertEquals($res["d38_38"], "0.12345678901234567899876543210987654321");
    }

}
