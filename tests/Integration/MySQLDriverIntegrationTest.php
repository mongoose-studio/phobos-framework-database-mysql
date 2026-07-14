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

namespace PhobosFramework\Database\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PhobosFramework\Database\Drivers\MySQL\MySQLDriver;
use PDO;

/**
 * Pruebas de integración para MySQLDriver.
 *
 * NOTA: Estas pruebas requieren una base de datos MySQL/MariaDB real en ejecución.
 * Configurar las variables de entorno en phpunit.xml o saltarlas si no hay conexión disponible.
 *
 * Para ejecutar estas pruebas:
 * 1. Configurar las variables DB_* en phpunit.xml
 * 2. Asegurarse de tener MySQL/MariaDB ejecutándose
 * 3. Ejecutar: ./vendor/bin/phpunit --testsuite=Integration
 */
class MySQLDriverIntegrationTest extends TestCase {
    private MySQLDriver $driver;
    private ?PDO $pdo = null;
    private bool $skipTests = false;

    protected function setUp(): void {
        $this->driver = new MySQLDriver();

        // Intentar conectar a MySQL
        try {
            $config = [
                'host' => getenv('DB_HOST') ?: 'localhost',
                'port' => getenv('DB_PORT') ?: 3306,
                'database' => getenv('DB_DATABASE') ?: 'test',
                'username' => getenv('DB_USERNAME') ?: 'root',
                'password' => getenv('DB_PASSWORD') ?: '',
                'charset' => 'utf8mb4',
            ];

            $dsn = $this->driver->getDSN($config);
            $options = $this->driver->getPDOOptions($config);

            $this->pdo = new PDO($dsn, $config['username'], $config['password'], $options);
            $this->driver->configure($this->pdo, $config);
        } catch (\PDOException $e) {
            $this->skipTests = true;
            $this->markTestSkipped(
                'MySQL connection not available. ' .
                'Configure DB_* environment variables in phpunit.xml. ' .
                'Error: ' . $e->getMessage()
            );
        }
    }

    protected function tearDown(): void {
        if ($this->pdo !== null) {
            // Limpiar tablas de prueba si existen
            try {
                $this->pdo->exec('DROP TABLE IF EXISTS test_table');
                $this->pdo->exec('DROP TABLE IF EXISTS test_optimize');
            } catch (\PDOException $e) {
                // Ignorar errores de limpieza
            }
        }

        $this->pdo = null;
    }

    public function test_connection_is_established(): void {
        if ($this->skipTests) {
            $this->markTestSkipped('MySQL not available');
        }

        $this->assertInstanceOf(PDO::class, $this->pdo);
    }

    public function test_charset_is_set_correctly(): void {
        if ($this->skipTests) {
            $this->markTestSkipped('MySQL not available');
        }

        $result = $this->pdo->query("SHOW VARIABLES LIKE 'character_set_connection'")->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('utf8mb4', $result['Value']);
    }

    public function test_strict_mode_is_enabled(): void {
        if ($this->skipTests) {
            $this->markTestSkipped('MySQL not available');
        }

        $result = $this->pdo->query("SELECT @@sql_mode AS mode")->fetch(PDO::FETCH_ASSOC);

        $this->assertStringContainsString('STRICT_ALL_TABLES', $result['mode']);
    }

    public function test_get_server_version_returns_valid_version(): void {
        if ($this->skipTests) {
            $this->markTestSkipped('MySQL not available');
        }

        $version = $this->driver->getServerVersion($this->pdo);

        $this->assertNotEmpty($version);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', $version);
    }

    public function test_is_mariadb_detection(): void {
        if ($this->skipTests) {
            $this->markTestSkipped('MySQL not available');
        }

        $isMariaDB = $this->driver->isMariaDB($this->pdo);

        // El resultado depende del servidor, solo verificamos que retorne un booleano
        $this->assertIsBool($isMariaDB);

        $version = $this->driver->getServerVersion($this->pdo);
        $expectedMariaDB = stripos($version, 'mariadb') !== false;

        $this->assertEquals($expectedMariaDB, $isMariaDB);
    }

    public function test_create_table_and_insert_data(): void {
        if ($this->skipTests) {
            $this->markTestSkipped('MySQL not available');
        }

        // Crear tabla
        $this->pdo->exec("
            CREATE TABLE test_table (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Insertar datos
        $stmt = $this->pdo->prepare("INSERT INTO test_table (name) VALUES (?)");
        $stmt->execute(['Test User']);

        // Verificar
        $result = $this->pdo->query("SELECT * FROM test_table")->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('Test User', $result['name']);
        $this->assertEquals(1, $result['id']);
    }

    public function test_optimize_table_works_with_real_table(): void {
        if ($this->skipTests) {
            $this->markTestSkipped('MySQL not available');
        }

        // Crear tabla (IF NOT EXISTS para tolerar estado residual de una corrida abortada)
        $this->pdo->exec('DROP TABLE IF EXISTS test_optimize');
        $this->pdo->exec("
            CREATE TABLE test_optimize (
                id INT AUTO_INCREMENT PRIMARY KEY,
                data VARCHAR(255)
            ) ENGINE=InnoDB
        ");

        // Insertar algunos datos
        for ($i = 0; $i < 10; $i++) {
            $this->pdo->exec("INSERT INTO test_optimize (data) VALUES ('row $i')");
        }

        // Optimizar tabla
        $result = $this->driver->optimizeTable($this->pdo, 'test_optimize');

        $this->assertTrue($result);
    }

    public function test_analyze_table_works_with_real_table(): void {
        if ($this->skipTests) {
            $this->markTestSkipped('MySQL not available');
        }

        // Crear tabla (IF NOT EXISTS para tolerar estado residual de una corrida abortada)
        $this->pdo->exec('DROP TABLE IF EXISTS test_optimize');
        $this->pdo->exec("
            CREATE TABLE test_optimize (
                id INT AUTO_INCREMENT PRIMARY KEY,
                data VARCHAR(255),
                INDEX idx_data (data)
            ) ENGINE=InnoDB
        ");

        // Insertar algunos datos
        for ($i = 0; $i < 10; $i++) {
            $this->pdo->exec("INSERT INTO test_optimize (data) VALUES ('row $i')");
        }

        // Analizar tabla
        $result = $this->driver->analyzeTable($this->pdo, 'test_optimize');

        $this->assertTrue($result);
    }

    public function test_optimize_nonexistent_table_returns_false(): void {
        if ($this->skipTests) {
            $this->markTestSkipped('MySQL not available');
        }

        $result = $this->driver->optimizeTable($this->pdo, 'nonexistent_table_xyz');

        $this->assertFalse($result);
    }

    public function test_analyze_nonexistent_table_returns_false(): void {
        if ($this->skipTests) {
            $this->markTestSkipped('MySQL not available');
        }

        $result = $this->driver->analyzeTable($this->pdo, 'nonexistent_table_xyz');

        $this->assertFalse($result);
    }

    public function test_transaction_isolation_level_can_be_set(): void {
        if ($this->skipTests) {
            $this->markTestSkipped('MySQL not available');
        }

        // Establecer nivel de aislamiento
        $sql = $this->driver->getSetIsolationLevelSQL('READ COMMITTED');
        $this->pdo->exec($sql);

        // Verificar
        $result = $this->pdo->query("SELECT @@transaction_isolation AS isolation")->fetch(PDO::FETCH_ASSOC);

        // MySQL devuelve el nivel en diferentes formatos según la versión
        $this->assertStringContainsString('READ-COMMITTED', strtoupper(str_replace(' ', '-', $result['isolation'])));
    }

    public function test_savepoints_are_supported(): void {
        if ($this->skipTests) {
            $this->markTestSkipped('MySQL not available');
        }

        // Crear tabla
        $this->pdo->exec("
            CREATE TABLE test_table (
                id INT AUTO_INCREMENT PRIMARY KEY,
                value VARCHAR(50)
            ) ENGINE=InnoDB
        ");

        // Iniciar transacción
        $this->pdo->beginTransaction();

        // Insertar primer valor
        $this->pdo->exec("INSERT INTO test_table (value) VALUES ('value1')");

        // Crear savepoint
        $this->pdo->exec($this->driver->getSavepointSQL('sp1'));

        // Insertar segundo valor
        $this->pdo->exec("INSERT INTO test_table (value) VALUES ('value2')");

        // Rollback al savepoint
        $this->pdo->exec($this->driver->getRollbackSavepointSQL('sp1'));

        // Commit
        $this->pdo->commit();

        // Verificar que solo existe el primer valor
        $count = $this->pdo->query("SELECT COUNT(*) FROM test_table")->fetchColumn();

        $this->assertEquals(1, $count);

        $value = $this->pdo->query("SELECT value FROM test_table")->fetchColumn();
        $this->assertEquals('value1', $value);
    }

    public function test_timezone_configuration_works(): void {
        if ($this->skipTests) {
            $this->markTestSkipped('MySQL not available');
        }

        // Configurar timezone
        $config = ['timezone' => '+05:00'];
        $this->driver->configure($this->pdo, $config);

        // Verificar
        $result = $this->pdo->query("SELECT @@session.time_zone AS tz")->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('+05:00', $result['tz']);
    }

    public function test_session_variables_are_set(): void {
        if ($this->skipTests) {
            $this->markTestSkipped('MySQL not available');
        }

        // Configurar variable de sesión
        $config = [
            'session_variables' => [
                'wait_timeout' => 3600,
            ],
        ];

        $this->driver->configure($this->pdo, $config);

        // Verificar
        $result = $this->pdo->query("SELECT @@session.wait_timeout AS timeout")->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals(3600, $result['timeout']);
    }

    public function test_prepared_statements_work_correctly(): void {
        if ($this->skipTests) {
            $this->markTestSkipped('MySQL not available');
        }

        // Crear tabla
        $this->pdo->exec("
            CREATE TABLE test_table (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(100),
                age INT
            ) ENGINE=InnoDB
        ");

        // Preparar statement
        $stmt = $this->pdo->prepare("INSERT INTO test_table (email, age) VALUES (:email, :age)");

        // Ejecutar con diferentes valores
        $stmt->execute(['email' => 'user1@example.com', 'age' => 25]);
        $stmt->execute(['email' => 'user2@example.com', 'age' => 30]);

        // Verificar
        $count = $this->pdo->query("SELECT COUNT(*) FROM test_table")->fetchColumn();
        $this->assertEquals(2, $count);

        // Buscar con prepared statement
        $stmt = $this->pdo->prepare("SELECT * FROM test_table WHERE email = ?");
        $stmt->execute(['user1@example.com']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('user1@example.com', $result['email']);
        $this->assertEquals(25, $result['age']);
    }

    public function test_last_insert_id_works(): void {
        if ($this->skipTests) {
            $this->markTestSkipped('MySQL not available');
        }

        // Crear tabla
        $this->pdo->exec("
            CREATE TABLE test_table (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50)
            ) ENGINE=InnoDB
        ");

        // Insertar
        $this->pdo->exec("INSERT INTO test_table (name) VALUES ('Test')");

        // Obtener último ID
        $lastId = $this->driver->getLastInsertId($this->pdo);

        $this->assertEquals('1', $lastId);

        // Insertar otro
        $this->pdo->exec("INSERT INTO test_table (name) VALUES ('Test 2')");
        $lastId = $this->driver->getLastInsertId($this->pdo);

        $this->assertEquals('2', $lastId);
    }
}
