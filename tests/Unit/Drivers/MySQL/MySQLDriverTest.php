<?php

/**
 * # Phobos Framework
 *
 * Para la información completa acerca del copyright y la licencia,
 * por favor vea el archivo LICENSE que va distribuido con el código fuente.
 *
 * @author      Marcel Rojas <marcelrojas16@gmail.com>
 * @copyright   Copyright (c) 2012-2025, Marcel Rojas <marcelrojas16@gmail.com>
 */

namespace PhobosFramework\Database\Tests\Unit\Drivers\MySQL;

use PHPUnit\Framework\TestCase;
use PhobosFramework\Database\Drivers\MySQL\MySQLDriver;
use PhobosFramework\Database\Exceptions\ConfigurationException;
use InvalidArgumentException;
use Mockery;
use PDO;
use PDOStatement;
use PDOException;

/**
 * Pruebas unitarias para la clase MySQLDriver.
 *
 * Esta clase de prueba verifica el comportamiento y la funcionalidad del driver MySQL,
 * incluyendo la generación de DSN, configuración de PDO, validación de parámetros,
 * y operaciones específicas de MySQL.
 */
class MySQLDriverTest extends TestCase {
    private MySQLDriver $driver;
    private PDO $pdo;

    protected function setUp(): void {
        $this->driver = new MySQLDriver();
        $this->pdo = Mockery::mock(PDO::class);
    }

    protected function tearDown(): void {
        Mockery::close();
    }

    // ========== Tests de getDSN() ==========

    public function test_get_dsn_generates_correct_tcp_connection_string(): void {
        $config = [
            'host' => 'localhost',
            'database' => 'testdb',
        ];

        $dsn = $this->driver->getDSN($config);

        $this->assertEquals('mysql:host=localhost;port=3306;dbname=testdb;charset=utf8mb4', $dsn);
    }

    public function test_get_dsn_uses_custom_port(): void {
        $config = [
            'host' => 'localhost',
            'database' => 'testdb',
            'port' => 3307,
        ];

        $dsn = $this->driver->getDSN($config);

        $this->assertStringContainsString('port=3307', $dsn);
    }

    public function test_get_dsn_uses_custom_charset(): void {
        $config = [
            'host' => 'localhost',
            'database' => 'testdb',
            'charset' => 'utf8',
        ];

        $dsn = $this->driver->getDSN($config);

        $this->assertStringContainsString('charset=utf8', $dsn);
    }

    public function test_get_dsn_generates_unix_socket_connection(): void {
        $config = [
            'unix_socket' => '/var/run/mysqld/mysqld.sock',
            'database' => 'testdb',
        ];

        $dsn = $this->driver->getDSN($config);

        $this->assertStringContainsString('unix_socket=/var/run/mysqld/mysqld.sock', $dsn);
        $this->assertStringContainsString('dbname=testdb', $dsn);
        $this->assertStringNotContainsString('host=', $dsn);
    }

    public function test_get_dsn_throws_exception_when_host_missing(): void {
        $config = ['database' => 'testdb'];

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Missing required configuration field: host');

        $this->driver->getDSN($config);
    }

    public function test_get_dsn_throws_exception_when_database_missing(): void {
        $config = ['host' => 'localhost'];

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Missing required configuration field: database');

        $this->driver->getDSN($config);
    }

    public function test_get_dsn_validates_invalid_charset(): void {
        $config = [
            'host' => 'localhost',
            'database' => 'testdb',
            'charset' => 'utf8; DROP TABLE users',
        ];

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid charset');

        $this->driver->getDSN($config);
    }

    public function test_get_dsn_validates_unsupported_charset(): void {
        $config = [
            'host' => 'localhost',
            'database' => 'testdb',
            'charset' => 'invalid_charset',
        ];

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Unsupported charset');

        $this->driver->getDSN($config);
    }

    public function test_get_dsn_validates_port_range(): void {
        $config = [
            'host' => 'localhost',
            'database' => 'testdb',
            'port' => 99999,
        ];

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Port must be between 1 and 65535');

        $this->driver->getDSN($config);
    }

    public function test_get_dsn_validates_port_is_numeric(): void {
        $config = [
            'host' => 'localhost',
            'database' => 'testdb',
            'port' => 'not_a_number',
        ];

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Port must be a numeric value');

        $this->driver->getDSN($config);
    }

    // ========== Tests de getPDOOptions() ==========

    public function test_get_pdo_options_returns_default_options(): void {
        $options = $this->driver->getPDOOptions([]);

        $this->assertIsArray($options);
        $this->assertEquals(PDO::ERRMODE_EXCEPTION, $options[PDO::ATTR_ERRMODE]);
        $this->assertEquals(PDO::FETCH_ASSOC, $options[PDO::ATTR_DEFAULT_FETCH_MODE]);
        $this->assertFalse($options[PDO::ATTR_EMULATE_PREPARES]);
        $this->assertFalse($options[PDO::ATTR_STRINGIFY_FETCHES]);
        $this->assertArrayHasKey(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $options);
        $this->assertTrue($options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY]);
    }

    public function test_get_pdo_options_sets_charset(): void {
        $config = ['charset' => 'utf8mb4'];
        $options = $this->driver->getPDOOptions($config);

        $this->assertArrayHasKey(PDO::MYSQL_ATTR_INIT_COMMAND, $options);
        $this->assertStringContainsString("SET NAMES 'utf8mb4'", $options[PDO::MYSQL_ATTR_INIT_COMMAND]);
    }

    public function test_get_pdo_options_sets_collation(): void {
        $config = [
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ];

        $options = $this->driver->getPDOOptions($config);

        $this->assertArrayHasKey(PDO::MYSQL_ATTR_INIT_COMMAND, $options);
        $this->assertStringContainsString("SET NAMES 'utf8mb4'", $options[PDO::MYSQL_ATTR_INIT_COMMAND]);
        $this->assertStringContainsString("COLLATE 'utf8mb4_unicode_ci'", $options[PDO::MYSQL_ATTR_INIT_COMMAND]);
    }

    public function test_get_pdo_options_enables_strict_mode_by_default(): void {
        $options = $this->driver->getPDOOptions([]);

        $this->assertArrayHasKey(PDO::MYSQL_ATTR_INIT_COMMAND, $options);
        $this->assertStringContainsString('STRICT_ALL_TABLES', $options[PDO::MYSQL_ATTR_INIT_COMMAND]);
        $this->assertStringContainsString('NO_ZERO_DATE', $options[PDO::MYSQL_ATTR_INIT_COMMAND]);
    }

    public function test_get_pdo_options_can_disable_strict_mode(): void {
        $config = ['strict' => false];
        $options = $this->driver->getPDOOptions($config);

        $this->assertArrayHasKey(PDO::MYSQL_ATTR_INIT_COMMAND, $options);
        $this->assertStringNotContainsString('STRICT_ALL_TABLES', $options[PDO::MYSQL_ATTR_INIT_COMMAND]);
    }

    public function test_get_pdo_options_validates_invalid_collation_format(): void {
        $config = [
            'charset' => 'utf8mb4',
            'collation' => 'invalid; DROP TABLE',
        ];

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid collation');

        $this->driver->getPDOOptions($config);
    }

    public function test_get_pdo_options_validates_collation_format(): void {
        $config = [
            'charset' => 'utf8mb4',
            'collation' => 'invalid_format',
        ];

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid collation format');

        $this->driver->getPDOOptions($config);
    }

    // ========== Tests de configure() ==========

    public function test_configure_sets_timezone(): void {
        $config = ['timezone' => '+00:00'];

        $this->pdo
            ->shouldReceive('exec')
            ->once()
            ->with("SET time_zone = '+00:00'")
            ->andReturn(true);

        $this->driver->configure($this->pdo, $config);

        $this->addToAssertionCount(1); // Para evitar "risky test"
    }

    public function test_configure_validates_timezone_format(): void {
        $config = ['timezone' => 'invalid; DROP TABLE'];

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid timezone');

        $this->driver->configure($this->pdo, $config);
    }

    public function test_configure_accepts_utc_timezone(): void {
        $config = ['timezone' => 'UTC'];

        $this->pdo
            ->shouldReceive('exec')
            ->once()
            ->with("SET time_zone = 'UTC'")
            ->andReturn(true);

        $this->driver->configure($this->pdo, $config);

        $this->addToAssertionCount(1);
    }

    public function test_configure_accepts_named_timezone(): void {
        $config = ['timezone' => 'America/New_York'];

        $this->pdo
            ->shouldReceive('exec')
            ->once()
            ->with("SET time_zone = 'America/New_York'")
            ->andReturn(true);

        $this->driver->configure($this->pdo, $config);

        $this->addToAssertionCount(1);
    }

    public function test_configure_sets_pdo_attributes(): void {
        $config = [
            'options' => [
                PDO::ATTR_TIMEOUT => 5,
            ],
        ];

        $this->pdo
            ->shouldReceive('setAttribute')
            ->once()
            ->with(PDO::ATTR_TIMEOUT, 5)
            ->andReturn(true);

        $this->driver->configure($this->pdo, $config);

        $this->addToAssertionCount(1);
    }

    public function test_configure_sets_session_variables(): void {
        $config = [
            'session_variables' => [
                'wait_timeout' => 28800,
                'sql_mode' => 'TRADITIONAL',
            ],
        ];

        $this->pdo
            ->shouldReceive('exec')
            ->once()
            ->with('SET SESSION wait_timeout = 28800')
            ->andReturn(true);

        $this->pdo
            ->shouldReceive('exec')
            ->once()
            ->with("SET SESSION sql_mode = 'TRADITIONAL'")
            ->andReturn(true);

        $this->driver->configure($this->pdo, $config);

        $this->addToAssertionCount(1);
    }

    public function test_configure_validates_session_variable_names(): void {
        $config = [
            'session_variables' => [
                'invalid-name; DROP TABLE' => 'value',
            ],
        ];

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid session variable name');

        $this->driver->configure($this->pdo, $config);
    }

    public function test_configure_sanitizes_session_variable_values(): void {
        $config = [
            'session_variables' => [
                'test_var' => "value'; DROP TABLE users; --",
            ],
        ];

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('prohibited characters');

        $this->driver->configure($this->pdo, $config);
    }

    public function test_configure_handles_boolean_session_variables(): void {
        $config = [
            'session_variables' => [
                'autocommit' => true,
            ],
        ];

        $this->pdo
            ->shouldReceive('exec')
            ->once()
            ->with('SET SESSION autocommit = ON')
            ->andReturn(true);

        $this->driver->configure($this->pdo, $config);

        $this->addToAssertionCount(1);
    }

    // ========== Tests de métodos de información ==========

    public function test_get_name_returns_mysql(): void {
        $this->assertEquals('mysql', $this->driver->getName());
    }

    public function test_supports_savepoints_returns_true(): void {
        $this->assertTrue($this->driver->supportsSavepoints());
    }

    public function test_quote_identifier_wraps_in_backticks(): void {
        $result = $this->driver->quoteIdentifier('table_name');

        $this->assertEquals('`table_name`', $result);
    }

    public function test_quote_identifier_escapes_backticks(): void {
        $result = $this->driver->quoteIdentifier('table`with`backticks');

        $this->assertEquals('`table``with``backticks`', $result);
    }

    // ========== Tests de getSetIsolationLevelSQL() ==========

    public function test_get_set_isolation_level_sql_returns_correct_syntax(): void {
        $sql = $this->driver->getSetIsolationLevelSQL('READ COMMITTED');

        $this->assertEquals('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED', $sql);
    }

    public function test_get_set_isolation_level_sql_validates_level(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid isolation level');

        $this->driver->getSetIsolationLevelSQL('INVALID LEVEL');
    }

    public function test_get_set_isolation_level_sql_accepts_all_valid_levels(): void {
        $validLevels = [
            'READ UNCOMMITTED',
            'READ COMMITTED',
            'REPEATABLE READ',
            'SERIALIZABLE',
        ];

        foreach ($validLevels as $level) {
            $sql = $this->driver->getSetIsolationLevelSQL($level);
            $this->assertStringContainsString($level, $sql);
        }
    }

    // ========== Tests de getServerVersion() ==========

    public function test_get_server_version_returns_version_string(): void {
        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('fetchColumn')
            ->once()
            ->andReturn('8.0.30');

        $this->pdo
            ->shouldReceive('query')
            ->once()
            ->with('SELECT VERSION()')
            ->andReturn($stmt);

        $version = $this->driver->getServerVersion($this->pdo);

        $this->assertEquals('8.0.30', $version);
    }

    public function test_get_server_version_throws_exception_on_query_failure(): void {
        $this->pdo
            ->shouldReceive('query')
            ->once()
            ->with('SELECT VERSION()')
            ->andReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to query server version');

        $this->driver->getServerVersion($this->pdo);
    }

    public function test_get_server_version_throws_exception_on_fetch_failure(): void {
        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('fetchColumn')
            ->once()
            ->andReturn(false);

        $this->pdo
            ->shouldReceive('query')
            ->once()
            ->with('SELECT VERSION()')
            ->andReturn($stmt);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch server version');

        $this->driver->getServerVersion($this->pdo);
    }

    // ========== Tests de isMariaDB() ==========

    public function test_is_mariadb_returns_true_for_mariadb(): void {
        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('fetchColumn')
            ->once()
            ->andReturn('10.5.8-MariaDB');

        $this->pdo
            ->shouldReceive('query')
            ->once()
            ->with('SELECT VERSION()')
            ->andReturn($stmt);

        $this->assertTrue($this->driver->isMariaDB($this->pdo));
    }

    public function test_is_mariadb_returns_false_for_mysql(): void {
        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('fetchColumn')
            ->once()
            ->andReturn('8.0.30');

        $this->pdo
            ->shouldReceive('query')
            ->once()
            ->with('SELECT VERSION()')
            ->andReturn($stmt);

        $this->assertFalse($this->driver->isMariaDB($this->pdo));
    }

    // ========== Tests de optimizeTable() ==========

    public function test_optimize_table_executes_optimize_command(): void {
        $stmt = $this->statusRowsStatement([
            ['Table' => 'db.users', 'Op' => 'optimize', 'Msg_type' => 'status', 'Msg_text' => 'OK'],
        ]);

        $this->pdo
            ->shouldReceive('query')
            ->once()
            ->with('OPTIMIZE TABLE `users`')
            ->andReturn($stmt);

        $result = $this->driver->optimizeTable($this->pdo, 'users');

        $this->assertTrue($result);
    }

    public function test_optimize_table_quotes_table_name(): void {
        $stmt = $this->statusRowsStatement([
            ['Msg_type' => 'status', 'Msg_text' => 'OK'],
        ]);

        $this->pdo
            ->shouldReceive('query')
            ->once()
            ->with('OPTIMIZE TABLE `table``with``backticks`')
            ->andReturn($stmt);

        $result = $this->driver->optimizeTable($this->pdo, 'table`with`backticks');

        $this->assertTrue($result);
    }

    /**
     * Regresión: MySQL no lanza excepción cuando la tabla no existe, devuelve una fila
     * con Msg_type = Error. El driver usaba exec() (que descarta esas filas) y siempre
     * reportaba true. Ahora debe leer la fila de estado y devolver false.
     */
    public function test_optimize_table_returns_false_when_status_row_reports_error(): void {
        $stmt = $this->statusRowsStatement([
            ['Msg_type' => 'Error', 'Msg_text' => "Table 'db.users' doesn't exist"],
            ['Msg_type' => 'status', 'Msg_text' => 'Operation failed'],
        ]);

        $this->pdo
            ->shouldReceive('query')
            ->once()
            ->with('OPTIMIZE TABLE `users`')
            ->andReturn($stmt);

        $result = $this->driver->optimizeTable($this->pdo, 'users');

        $this->assertFalse($result);
    }

    public function test_optimize_table_returns_false_on_exception(): void {
        $this->pdo
            ->shouldReceive('query')
            ->once()
            ->with('OPTIMIZE TABLE `users`')
            ->andThrow(new PDOException('Connection lost'));

        $result = $this->driver->optimizeTable($this->pdo, 'users');

        $this->assertFalse($result);
    }

    // ========== Tests de analyzeTable() ==========

    public function test_analyze_table_executes_analyze_command(): void {
        $stmt = $this->statusRowsStatement([
            ['Table' => 'db.products', 'Op' => 'analyze', 'Msg_type' => 'status', 'Msg_text' => 'OK'],
        ]);

        $this->pdo
            ->shouldReceive('query')
            ->once()
            ->with('ANALYZE TABLE `products`')
            ->andReturn($stmt);

        $result = $this->driver->analyzeTable($this->pdo, 'products');

        $this->assertTrue($result);
    }

    public function test_analyze_table_quotes_table_name(): void {
        $stmt = $this->statusRowsStatement([
            ['Msg_type' => 'status', 'Msg_text' => 'OK'],
        ]);

        $this->pdo
            ->shouldReceive('query')
            ->once()
            ->with('ANALYZE TABLE `table``name`')
            ->andReturn($stmt);

        $result = $this->driver->analyzeTable($this->pdo, 'table`name');

        $this->assertTrue($result);
    }

    public function test_analyze_table_returns_false_when_status_row_reports_error(): void {
        $stmt = $this->statusRowsStatement([
            ['Msg_type' => 'Error', 'Msg_text' => "Table 'db.products' doesn't exist"],
            ['Msg_type' => 'status', 'Msg_text' => 'Operation failed'],
        ]);

        $this->pdo
            ->shouldReceive('query')
            ->once()
            ->with('ANALYZE TABLE `products`')
            ->andReturn($stmt);

        $result = $this->driver->analyzeTable($this->pdo, 'products');

        $this->assertFalse($result);
    }

    public function test_analyze_table_returns_false_on_exception(): void {
        $this->pdo
            ->shouldReceive('query')
            ->once()
            ->with('ANALYZE TABLE `products`')
            ->andThrow(new PDOException('Connection lost'));

        $result = $this->driver->analyzeTable($this->pdo, 'products');

        $this->assertFalse($result);
    }

    /**
     * Crea un PDOStatement mock que devuelve las filas de estado dadas al hacer fetchAll,
     * imitando lo que MySQL retorna para OPTIMIZE/ANALYZE TABLE.
     *
     * @param array $rows Filas de estado a devolver
     */
    private function statusRowsStatement(array $rows): PDOStatement {
        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('fetchAll')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn($rows);

        return $stmt;
    }
}
