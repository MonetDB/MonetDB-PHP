<?php
use PHPUnit\Framework\TestCase;
use MonetDB\Connection;

require_once(__DIR__. '../../src/include.php');

final class preparedTest extends TestCase {
    /**
     * @var Connection
     */
    private static $conn;

    /**
     * Next ID for the records
     *
     * @var integer
     */
    private static $id = 0;

    public static function setUpBeforeClass(): void
    {
        self::$conn = new Connection("127.0.0.1", 50000, "monetdb", "monetdb", "myDatabase");
    }

    public function testCreateTable(): void 
    {
        self::$conn->Query("drop table if exists php_prepared");
        $res = self::$conn->Query("CREATE TABLE php_prepared (id int, h HUGEINT, b BIGINT, i int, d DECIMAL(38,19), d2 dec(38,0),
            n numeric(38,2), t timestamp, dat date, r real, f float, dbl double, dbl2 double precision, bo bool, tim time);");
        
        $this->assertCount(0, $res);
    }

    private function InsertValuePrepared(string $field, $value, $cmpValue) {
        self::$id++;

        self::$conn->Query("insert into php_prepared (id, {$field}) values (?, ?)", [self::$id, $value]);
        $response = self::$conn->QueryFirst("select {$field} from php_prepared where id = ?", [self::$id]);

        $this->assertEquals($cmpValue, $response[$field]);
    }

    public function testDec38(): void {
        $this->InsertValuePrepared('d', '1234567890123456789.9876543210987654321', '1234567890123456789.9876543210987654321');
    }

    public function testNull(): void {
        $this->InsertValuePrepared('d', null, null);
    }

    public function testInt32(): void {
        $this->InsertValuePrepared('i', 123456, '123456');
        $this->InsertValuePrepared('i', '123456', '123456');
    }

    public function testInt64(): void {
        $this->InsertValuePrepared('b', 1234567890987654321, '1234567890987654321');
        $this->InsertValuePrepared('b', '1234567890987654321', '1234567890987654321');
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

    public function testDouble(): void {
        $this->InsertValuePrepared('f', 3.14159265, '3.14159265');
        $this->InsertValuePrepared('f', '3.14159265', '3.14159265');

        $this->InsertValuePrepared('dbl', 3.14159265, '3.14159265');
        $this->InsertValuePrepared('dbl', '3.14159265', '3.14159265');

        $this->InsertValuePrepared('dbl2', 3.14159265, '3.14159265');
        $this->InsertValuePrepared('dbl2', '3.14159265', '3.14159265');
    }

    public function testReal(): void {
        $this->InsertValuePrepared('r', 3.141592, '3.141592');
        $this->InsertValuePrepared('r', '3.141592', '3.141592');
    }

    public function testBool(): void {
        $this->InsertValuePrepared('bo', true, 'true');
        $this->InsertValuePrepared('bo', false, 'false');
        $this->InsertValuePrepared('bo', 'true', 'true');
        $this->InsertValuePrepared('bo', 'false', 'false');
        $this->InsertValuePrepared('bo', 'FALSE', 'false');
        $this->InsertValuePrepared('bo', 1, 'true');
        $this->InsertValuePrepared('bo', 0, 'false');
        $this->InsertValuePrepared('bo', 'enabled', 'true');
        $this->InsertValuePrepared('bo', 'disabled', 'false');
        $this->InsertValuePrepared('bo', 't', 'true');
        $this->InsertValuePrepared('bo', 'f', 'false');
    }

    public function testTime(): void {
        $dt = new DateTime();

        $this->InsertValuePrepared('tim', '12:28', '12:28:00');
        $this->InsertValuePrepared('tim', '12:28:34', '12:28:34');
        $this->InsertValuePrepared('tim', $dt, $dt->format("H:i:s"));
    }
}
