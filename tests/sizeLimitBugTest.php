<?php
use PHPUnit\Framework\TestCase;
use MonetDB\Connection;

require_once(__DIR__. '../../src/include.php');

final class sizeLimitBugTest extends TestCase {
    public $packet_size = 20000;

    /**
     * @var Connection
     */
    public static $conn;

    public static function setUpBeforeClass(): void
    {
        self::$conn = new Connection("127.0.0.1", 50000, "monetdb", "monetdb", "myDatabase");
    }

    public function testWeCanConnectToDatabase(): void 
    {
        $this->assertInstanceOf(
            Connection::class,
            self::$conn
        );
    }

    public function testWeCanFetchRows(): void 
    {
        $sql = 'select 1';
        $sql = str_pad($sql, $this->packet_size , ' ');

        $res = self::$conn->Query($sql);
        $this->assertCount(1, $res);
    }
}
