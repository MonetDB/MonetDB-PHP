<?php
use PHPUnit\Framework\TestCase;
use MonetDB\Connection;

require_once(__DIR__. '../../src/include.php');

final class int64Test extends TestCase {
    public $conn;

    public function setUp(): void
    {
        $this->conn = new Connection("127.0.0.1", 50000, "monetdb", "monetdb", "temp");
    }

    public function testBigIntTable(): void 
    {
        $res = $this->conn->Query("CREATE TABLE php_int64_dec18 (i BIGINT, d0 DECIMAL(18,0), d9 DECIMAL(18,9));");

        $this->assertCount(0, $res);
    }

    public function testInsertBigInt(): void 
    {
        $res = $this->conn->Query("INSERT INTO php_int64_dec18 VALUES (1234567890987654321, 123456789987654321, 123456789.987654321);");

        $this->assertCount(0, $res);
    }

    public function testSelectBigInt(): void 
    {
        $res = $this->conn->Query("SELECT * FROM php_int64_dec18;");
        $res_arr = iterator_to_array($res);

        $this->assertEquals($res_arr[1]["i"], "1234567890987654321");
        $this->assertEquals($res_arr[1]["d0"], "123456789987654321");
        $this->assertEquals($res_arr[1]["d9"], "123456789.987654321");
    }

}
?>