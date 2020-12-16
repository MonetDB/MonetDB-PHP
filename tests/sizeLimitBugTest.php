<?php
    use PHPUnit\Framework\TestCase;
    use MonetDB\Connection;

    require_once(__DIR__. '../../src/include.php');

    final class sizeLimitBugTest extends TestCase {

        public $packet_size = 20000; 
        public $conn;

        public function setUp(): void
        {
            $this->conn = new Connection("127.0.0.1", 50000, "monetdb", "monetdb", "temp");
        }

        public function testWeCanConnectToDatabase(): void 
        {
            $this->assertInstanceOf(
                Connection::class,
                $this->conn
            );
        }

        public function testWeCanFetchRows(): void 
        {
            $sql = 'select 1';
            $sql = str_pad($sql, $this->packet_size , ' ');

            $res = $this->conn->Query($sql);
            $this->assertCount(1, $res);
        }
    }
?>
