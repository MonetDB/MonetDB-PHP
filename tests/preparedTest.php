<?php
use PHPUnit\Framework\TestCase;
use MonetDB\Connection;

require_once(__DIR__. '../../src/include.php');

final class preparedTest extends TestCase {
    /**
     * @var Connection
     */
    private $conn;

    /**
     * Next ID for the records
     *
     * @var integer
     */
    private static $id = 0;

    public function setUp(): void
    {
        $this->conn = new Connection("127.0.0.1", 50000, "monetdb", "monetdb", "myDatabase");
    }

    public function testCreateTable(): void 
    {
        $this->conn->Query("drop table if exists php_prepared");
        $res = $this->conn->Query("CREATE TABLE php_prepared (id int, h HUGEINT, b BIGINT, i int, d DECIMAL(38,19), d2 dec(38,0),
            n numeric(38,2), t timestamp, dat date, r real, f float, dbl double, dbl2 double precision);");
        
        $this->assertCount(0, $res);
    }

    private function InsertValuePrepared(string $field, $value, string $strValue) {
        self::$id++;

        $this->conn->Query("insert into php_prepared (id, {$field}) values (?, ?)", [self::$id, $value]);
        $response = $this->conn->QueryFirst("select {$field} from php_prepared where id = ?", [self::$id]);

        $this->assertEquals($strValue, $response[$field]);
    }

    public function testDec38(): void {
        $this->InsertValuePrepared('d', '1234567890123456789.9876543210987654321', '1234567890123456789.9876543210987654321');
    }

    public function testInt32(): void {
        $this->InsertValuePrepared('i', 123456, '123456');
    }

    public function testInt64(): void {
        $this->InsertValuePrepared('b', 1234567890987654321, '1234567890987654321');
    }

    public function testTimestamp(): void {
        $ts = new DateTime();

        $this->InsertValuePrepared('t', $ts, $ts->format('Y-m-d H:i:s.u'));
        $this->InsertValuePrepared('t', $ts->format('Y-m-d H:i:s.u'), $ts->format('Y-m-d H:i:s.u'));
    }

    public function testInt128(): void {
        $this->InsertValuePrepared('h', '123456789098765432101234567890987654321', '123456789098765432101234567890987654321');
    }

    public function testDate(): void {
        $this->InsertValuePrepared('dat', '2020-12-19', '2020-12-19');

        $ts = new DateTime();
        $this->InsertValuePrepared('dat', $ts, $ts->format('Y-m-d'));
    }
}
