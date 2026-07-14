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

namespace PhobosFramework\Database\Drivers\MySQL;

use PDO;
use PDOException;
use PhobosFramework\Database\Drivers\AbstractDriver;
use PhobosFramework\Database\Exceptions\ConfigurationException;
use PhobosFramework\Database\QueryBuilder\Grammar\Grammar;
use InvalidArgumentException;
use RuntimeException;

/**
 * Driver para MySQL/MariaDB
 *
 * Esta clase proporciona la implementación específica para conectar y gestionar
 * bases de datos MySQL/MariaDB. Incluye configuraciones específicas del motor,
 * gestión de conexiones y operaciones especializadas.
 */
class MySQLDriver extends AbstractDriver {
    /**
     * Conjuntos de caracteres (charsets) válidos para MySQL/MariaDB
     *
     * Lista de charsets soportados oficialmente por el driver
     */
    private const array VALID_CHARSETS = [
        'utf8mb4', 'utf8', 'latin1', 'ascii', 'utf16', 'utf32',
        'binary', 'utf8mb3', 'big5', 'gbk', 'sjis', 'euckr',
    ];

    /**
     * Colaciones (collations) comunes válidas para MySQL/MariaDB
     *
     * Esta no es una lista exhaustiva, pero incluye las colaciones
     * más utilizadas en implementaciones estándar
     */
    private const array VALID_COLLATIONS = [
        'utf8mb4_unicode_ci', 'utf8mb4_general_ci', 'utf8mb4_bin',
        'utf8mb4_0900_ai_ci', 'utf8_general_ci', 'utf8_unicode_ci',
        'latin1_swedish_ci', 'latin1_general_ci', 'ascii_general_ci',
    ];

    /**
     * Niveles de aislamiento válidos para transacciones
     *
     * Define los niveles de aislamiento soportados por MySQL/MariaDB
     * para el control de concurrencia en transacciones
     */
    private const array VALID_ISOLATION_LEVELS = [
        self::ISOLATION_READ_UNCOMMITTED,
        self::ISOLATION_READ_COMMITTED,
        self::ISOLATION_REPEATABLE_READ,
        self::ISOLATION_SERIALIZABLE,
    ];

    /**
     * Genera la cadena DSN para la conexión MySQL
     *
     * @param array $config Configuración de la conexión que debe incluir:
     *                      - host: Servidor MySQL
     *                      - database: Nombre de la base de datos
     *                      - port: Puerto (opcional, por defecto 3306)
     *                      - charset: Conjunto de caracteres (opcional, por defecto utf8mb4)
     *                      - unix_socket: Ruta al socket Unix (opcional)
     * @return string Cadena DSN formateada para PDO
     * @throws ConfigurationException Si faltan parámetros requeridos
     */
    public function getDSN(array $config): string {
        // Validar campos requeridos según el tipo de conexión
        if (isset($config['unix_socket'])) {
            // Para socket Unix solo se requiere el socket y la base de datos
            $this->validateConfig($config, ['unix_socket', 'database']);

            $database = $config['database'];
            $charset = $this->validateCharset($config['charset'] ?? 'utf8mb4');

            return "mysql:unix_socket={$config['unix_socket']};dbname=$database;charset=$charset";
        }

        // Para conexión TCP se requiere host y database
        $this->validateConfig($config, ['host', 'database']);

        $host = $config['host'];
        $port = $this->validatePort($config['port'] ?? 3306);
        $database = $config['database'];
        $charset = $this->validateCharset($config['charset'] ?? 'utf8mb4');

        return "mysql:host=$host;port=$port;dbname=$database;charset=$charset";
    }

    /**
     * Obtiene las opciones específicas de PDO para MySQL
     *
     * @param array $config Configuración que puede incluir:
     *                      - charset: Conjunto de caracteres para la conexión
     *                      - collation: Colación específica para ordenamiento y comparación
     *                      - strict: Modo estricto SQL (opcional, por defecto true)
     * @return array Arreglo asociativo con las opciones PDO configuradas
     * @throws ConfigurationException Si hay errores en la configuración proporcionada
     */
    public function getPDOOptions(array $config): array {
        $options = parent::getPDOOptions($config);

        // Validar charset
        $charset = $this->validateCharset($config['charset'] ?? 'utf8mb4');

        // Iniciar comando SET NAMES
        $initCommand = "SET NAMES '$charset'";

        // Si está definido el collation, agregarlo
        if (isset($config['collation'])) {
            $collation = $this->validateCollation($config['collation']);
            $initCommand .= " COLLATE '$collation'";
        }

        // Opciones específicas de MySQL
        $mysqlOptions = [
            PDO::MYSQL_ATTR_INIT_COMMAND => $initCommand,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ];

        // Strict mode (recomendado para producción)
        if ($config['strict'] ?? true) {
            $mysqlOptions[PDO::MYSQL_ATTR_INIT_COMMAND] .=
                ", sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE'";
        }

        // Usar + operator para preservar keys numéricas de PDO
        return $mysqlOptions + $options;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(PDO $pdo, array $config): void {
        parent::configure($pdo, $config);

        // Timezone (si está definido)
        if (isset($config['timezone'])) {
            $timezone = $this->validateTimezone($config['timezone']);
            $pdo->exec("SET time_zone = '$timezone'");
        }

        // PDO atributos adicionales
        if (isset($config['options']) && is_array($config['options'])) {
            foreach ($config['options'] as $key => $value) {
                $pdo->setAttribute($key, $value);
            }
        }

        // Session variables adicionales
        if (isset($config['session_variables']) && is_array($config['session_variables'])) {
            foreach ($config['session_variables'] as $key => $value) {
                $this->setSessionVariable($pdo, $key, $value);
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
     *
     * MySQL/MariaDB cita identificadores con backticks.
     */
    public function getGrammar(): Grammar {
        return $this->grammar ??= new MySQLGrammar();
    }

    /**
     * {@inheritdoc}
     */
    public function getSetIsolationLevelSQL(string $level): string {
        // Validar que el nivel de aislamiento sea válido
        if (!in_array($level, self::VALID_ISOLATION_LEVELS, true)) {
            throw new InvalidArgumentException(
                "Invalid isolation level '$level'. Valid levels are: " .
                implode(', ', self::VALID_ISOLATION_LEVELS)
            );
        }

        // MySQL requiere SET antes de la transacción
        return "SET SESSION TRANSACTION ISOLATION LEVEL $level";
    }

    /**
     * Obtiene la versión del servidor MySQL
     *
     * @param PDO $pdo Instancia de conexión PDO
     * @return string Versión del servidor MySQL/MariaDB
     */
    public function getServerVersion(PDO $pdo): string {
        $stmt = $pdo->query('SELECT VERSION()');

        if ($stmt === false) {
            throw new RuntimeException('Failed to query server version');
        }

        $version = $stmt->fetchColumn();

        if ($version === false) {
            throw new RuntimeException('Failed to fetch server version');
        }

        return $version;
    }

    /**
     * Verifica si el servidor es MariaDB
     *
     * @param PDO $pdo Instancia de conexión PDO
     * @return bool Verdadero si es MariaDB, falso si es MySQL
     */
    public function isMariaDB(PDO $pdo): bool {
        $version = $this->getServerVersion($pdo);
        return stripos($version, 'mariadb') !== false;
    }

    /**
     * Optimiza una tabla MySQL/MariaDB
     *
     * @param PDO $pdo Instancia de conexión PDO
     * @param string $table Nombre de la tabla a optimizar
     * @return bool Verdadero si la operación fue exitosa
     */
    public function optimizeTable(PDO $pdo, string $table): bool {
        return $this->runMaintenanceStatement($pdo, 'OPTIMIZE TABLE', $table);
    }

    /**
     * Analiza una tabla y actualiza sus estadísticas
     *
     * @param PDO $pdo Instancia de conexión PDO
     * @param string $table Nombre de la tabla a analizar
     * @return bool Verdadero si la operación fue exitosa
     */
    public function analyzeTable(PDO $pdo, string $table): bool {
        return $this->runMaintenanceStatement($pdo, 'ANALYZE TABLE', $table);
    }

    /**
     * Ejecuta una sentencia de mantenimiento (OPTIMIZE/ANALYZE/CHECK/REPAIR TABLE)
     * y determina si fue exitosa.
     *
     * MySQL no lanza una excepción cuando la tabla no existe: devuelve un conjunto
     * de resultados con una fila cuyo `Msg_type` es `Error`. Por eso hay que ejecutar
     * la sentencia como consulta, leer las filas de estado y detectar cualquier fila
     * de error, en lugar de asumir éxito porque `exec()` no arrojó.
     *
     * @param PDO $pdo Instancia de conexión PDO
     * @param string $statement Sentencia de mantenimiento (ej: 'OPTIMIZE TABLE')
     * @param string $table Nombre de la tabla objetivo
     * @return bool Verdadero si ninguna fila reportó un error
     */
    private function runMaintenanceStatement(PDO $pdo, string $statement, string $table): bool {
        $quotedTable = $this->quoteIdentifier($table);

        try {
            $stmt = $pdo->query("$statement $quotedTable");

            if ($stmt === false) {
                return false;
            }

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                if (isset($row['Msg_type']) && strcasecmp((string)$row['Msg_type'], 'error') === 0) {
                    return false;
                }
            }

            return true;
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * Valida que el charset sea válido
     *
     * @param string $charset Charset a validar
     * @return string Charset validado
     * @throws ConfigurationException Si el charset no es válido
     */
    private function validateCharset(string $charset): string {
        // Sanitizar charset para prevenir inyección SQL
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', $charset);

        if ($sanitized !== $charset) {
            throw new ConfigurationException(
                "Invalid charset '$charset'. Only alphanumeric, dash and underscore characters are allowed."
            );
        }

        // Verificar que esté en la lista de charsets válidos
        if (!in_array($charset, self::VALID_CHARSETS, true)) {
            throw new ConfigurationException(
                "Unsupported charset '$charset'. Supported charsets are: " .
                implode(', ', self::VALID_CHARSETS)
            );
        }

        return $charset;
    }

    /**
     * Valida que el collation sea válido
     *
     * @param string $collation Collation a validar
     * @return string Collation validado
     * @throws ConfigurationException Si el collation no es válido
     */
    private function validateCollation(string $collation): string {
        // Sanitizar collation para prevenir inyección SQL
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $collation);

        if ($sanitized !== $collation) {
            throw new ConfigurationException(
                "Invalid collation '$collation'. Only alphanumeric and underscore characters are allowed."
            );
        }

        // Verificar que esté en la lista de collations válidos (o permitir formato válido)
        // Las collations siguen el patrón: charset_language_ci/cs/bin
        if (!preg_match('/^[a-z0-9]+_[a-z0-9_]+_(ci|cs|bin)$/', $collation)) {
            throw new ConfigurationException(
                "Invalid collation format '$collation'. Expected format: charset_language_ci/cs/bin"
            );
        }

        if (!in_array($collation, self::VALID_COLLATIONS, true)) {
            throw new ConfigurationException(
                "Unsupported collation '$collation'. Supported collations are: " .
                implode(', ', self::VALID_COLLATIONS)
            );
        }

        return $collation;
    }

    /**
     * Valida que el puerto sea válido
     *
     * @param mixed $port Puerto a validar
     * @return int Puerto validado
     * @throws ConfigurationException Si el puerto no es válido
     */
    private function validatePort(mixed $port): int {
        if (!is_numeric($port)) {
            throw new ConfigurationException(
                "Invalid port '$port'. Port must be a numeric value."
            );
        }

        $port = (int)$port;

        if ($port < 1 || $port > 65535) {
            throw new ConfigurationException(
                "Invalid port '$port'. Port must be between 1 and 65535."
            );
        }

        return $port;
    }

    /**
     * Valida que el timezone sea válido
     *
     * @param string $timezone Timezone a validar
     * @return string Timezone validado
     * @throws ConfigurationException Si el timezone no es válido
     */
    private function validateTimezone(string $timezone): string {
        // Permitir formatos: +00:00, -05:00, UTC, SYSTEM, etc.
        $validFormats = [
            '/^[+-]\d{2}:\d{2}$/',           // +00:00, -05:00
            '/^SYSTEM$/i',                    // SYSTEM
            '/^UTC$/i',                       // UTC
            '/^[A-Za-z]+\/[A-Za-z_]+$/',     // America/New_York
        ];

        foreach ($validFormats as $pattern) {
            if (preg_match($pattern, $timezone)) {
                return $timezone;
            }
        }

        throw new ConfigurationException(
            "Invalid timezone '$timezone'. Use formats like: +00:00, UTC, SYSTEM, or America/New_York"
        );
    }

    /**
     * Establece una variable de sesión de MySQL de forma segura
     *
     * @param PDO $pdo Instancia de conexión PDO
     * @param string $key Nombre de la variable
     * @param mixed $value Valor de la variable
     * @return void
     * @throws ConfigurationException Si el nombre de variable no es válido
     */
    private function setSessionVariable(PDO $pdo, string $key, mixed $value): void {
        // Validar que el nombre de la variable sea seguro
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
            throw new ConfigurationException(
                "Invalid session variable name '$key'. Must start with letter or underscore, " .
                "followed by alphanumeric characters or underscores."
            );
        }

        // Para valores numéricos, no usar comillas
        if (is_numeric($value)) {
            $pdo->exec("SET SESSION $key = $value");
        } elseif (is_bool($value)) {
            $boolValue = $value ? 'ON' : 'OFF';
            $pdo->exec("SET SESSION $key = $boolValue");
        } else {
            // Para strings, validar y escapar
            $sanitized = $this->sanitizeSessionValue((string)$value);
            $pdo->exec("SET SESSION $key = '$sanitized'");
        }
    }

    /**
     * Sanitiza un valor de variable de sesión
     *
     * @param string $value Valor a sanitizar
     * @return string Valor sanitizado
     * @throws ConfigurationException Si el valor contiene caracteres peligrosos
     */
    private function sanitizeSessionValue(string $value): string {
        // Escapar comillas simples
        $sanitized = str_replace("'", "''", $value);

        // Rechazar valores que contengan caracteres potencialmente peligrosos
        if (preg_match('/[;\x00\n\r\x1a]/', $value)) {
            throw new ConfigurationException(
                "Invalid session variable value. Contains prohibited characters."
            );
        }

        return $sanitized;
    }
}
