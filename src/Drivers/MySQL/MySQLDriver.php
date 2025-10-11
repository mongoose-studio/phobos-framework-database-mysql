<?php

namespace PhobosFramework\Database\Drivers\MySQL;

use PDO;
use PhobosFramework\Database\Drivers\AbstractDriver;
use PhobosFramework\Database\Exceptions\ConfigurationException;

/**
 * Driver para MySQL/MariaDB
 */
class MySQLDriver extends AbstractDriver {
    /**
     * {@inheritdoc}
     */
    public function getDSN(array $config): string {
        $this->validateConfig($config, ['host', 'database']);

        $host = $config['host'];
        $port = $config['port'] ?? 3306;
        $database = $config['database'];
        $charset = $config['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        // Soporte para socket Unix
        if (isset($config['unix_socket'])) {
            $dsn = "mysql:unix_socket={$config['unix_socket']};dbname={$database};charset={$charset}";
        }

        return $dsn;
    }

    /**
     * {@inheritdoc}
     */
    public function getPDOOptions(array $config): array {
        $options = parent::getPDOOptions($config);

        // Opciones específicas de MySQL
        $mysqlOptions = [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '{$config['charset']}' COLLATE '{$config['collation']}'",
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ];

        // Si está definido el collation
        if (isset($config['collation'])) {
            $mysqlOptions[PDO::MYSQL_ATTR_INIT_COMMAND] =
                "SET NAMES '{$config['charset']}' COLLATE '{$config['collation']}'";
        }

        // Strict mode (recomendado para producción)
        if ($config['strict'] ?? true) {
            $mysqlOptions[PDO::MYSQL_ATTR_INIT_COMMAND] .=
                ", sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE'";
        }

        return array_merge($options, $mysqlOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function configure(PDO $pdo, array $config): void {
        parent::configure($pdo, $config);

        // Timezone (si está definido)
        if (isset($config['timezone'])) {
            $pdo->exec("SET time_zone = '{$config['timezone']}'");
        }

        // Session variables adicionales
        if (isset($config['session_variables']) && is_array($config['session_variables'])) {
            foreach ($config['session_variables'] as $key => $value) {
                $pdo->exec("SET SESSION {$key} = '{$value}'");
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string {
        return 'mysql';
    }

    /**
     * {@inheritdoc}
     */
    public function supportsSavepoints(): bool {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function quoteIdentifier(string $identifier): string {
        // MySQL usa backticks para identificadores
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * {@inheritdoc}
     */
    public function getSetIsolationLevelSQL(string $level): string {
        // MySQL requiere SET antes de la transacción
        return "SET SESSION TRANSACTION ISOLATION LEVEL {$level}";
    }

    /**
     * Obtiene la versión del servidor MySQL
     *
     * @param PDO $pdo
     * @return string
     */
    public function getServerVersion(PDO $pdo): string {
        $stmt = $pdo->query('SELECT VERSION()');
        return $stmt->fetchColumn();
    }

    /**
     * Verifica si es MariaDB
     *
     * @param PDO $pdo
     * @return bool
     */
    public function isMariaDB(PDO $pdo): bool {
        $version = $this->getServerVersion($pdo);
        return stripos($version, 'mariadb') !== false;
    }

    /**
     * Optimiza una tabla
     *
     * @param PDO $pdo
     * @param string $table Nombre de la tabla
     * @return bool
     */
    public function optimizeTable(PDO $pdo, string $table): bool {
        $quotedTable = $this->quoteIdentifier($table);
        $pdo->exec("OPTIMIZE TABLE {$quotedTable}");
        return true;
    }

    /**
     * Analiza una tabla (actualiza estadísticas)
     *
     * @param PDO $pdo
     * @param string $table Nombre de la tabla
     * @return bool
     */
    public function analyzeTable(PDO $pdo, string $table): bool {
        $quotedTable = $this->quoteIdentifier($table);
        $pdo->exec("ANALYZE TABLE {$quotedTable}");
        return true;
    }
}
