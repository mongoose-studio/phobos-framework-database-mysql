# Phobos Framework - MySQL Driver

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.4-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.txt)
[![Phobos Framework](https://img.shields.io/badge/Phobos-Framework-orange)](https://github.com/mongoose-studio/phobos-framework)

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/mongoose-studio/phobos-framework/main/phobos-banner-dark.png">
  <source media="(prefers-color-scheme: light)" srcset="https://raw.githubusercontent.com/mongoose-studio/phobos-framework/main/phobos-banner.png">
  <img alt="Phobos Framework" height="64px" src="https://raw.githubusercontent.com/mongoose-studio/phobos-framework/main/phobos-banner-dark.png">
</picture>

Driver MySQL/MariaDB para **Phobos Framework Database Layer**. Proporciona conectividad completa con características específicas de MySQL incluyendo strict mode, Unix sockets, configuración de sesión y operaciones de mantenimiento y algunas otras cosillas.

## Características

- 🔌 **Conectividad MySQL/MariaDB** - Soporte completo para ambos motores
- 🔒 **Strict Mode** - SQL estricto habilitado por defecto para seguridad
- ⚡ **Unix Sockets** - Conexiones locales de alto rendimiento
- 🌍 **Timezone Configuration** - Configuración de zona horaria por sesión
- 🔧 **Session Variables** - Configuración personalizada de variables MySQL
- 📊 **Database Maintenance** - OPTIMIZE TABLE y ANALYZE TABLE integrados
- 🔀 **Transaction Isolation** - Configuración de niveles de aislamiento
- 🎯 **Savepoints Support** - Transacciones anidadas completas
- 🏷️ **MariaDB Detection** - Detección automática de MariaDB vs MySQL

## Instalación

```bash
composer require mongoose-studio/phobos-framework-database-mysql
```

### Dependencias

Este driver requiere:
- `mongoose-studio/phobos-framework` ^3.1
- `mongoose-studio/phobos-framework-database` ^3.2
- Extensión `ext-pdo` habilitada
- Extensión `ext-pdo_mysql` habilitada

## Configuración

### 1. Registrar el Driver

En tu `config/database.php`:

```php
<?php

return [
    'default' => 'mysql',
    
    'drivers' => [
        'mysql' => PhobosFramework\Database\Drivers\MySQL\MySQLDriver::class,
    ],
    
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'myapp'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict' => true,
            'timezone' => '+00:00',
        ],
    ],
];
```

### 2. Variables de Entorno

En tu archivo `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myapp
DB_USERNAME=root
DB_PASSWORD=secret
```

## Opciones de Configuración

### Configuración Básica

```php
'mysql' => [
    'driver' => 'mysql',
    'host' => 'localhost',      // Hostname o IP del servidor
    'port' => 3306,              // Puerto (opcional, default: 3306)
    'database' => 'myapp',       // Nombre de la base de datos
    'username' => 'root',        // Usuario
    'password' => 'secret',      // Contraseña
]
```

### Charset y Collation

```php
'mysql' => [
    // ...
    'charset' => 'utf8mb4',                  // Default: utf8mb4
    'collation' => 'utf8mb4_unicode_ci',     // Default: utf8mb4_unicode_ci
]
```

**Charsets comunes:**
- `utf8mb4` - Full Unicode (recomendado, soporta emojis)
- `utf8` - Unicode básico (3 bytes, legacy)
- `latin1` - ISO-8859-1 (legacy)

**Collations comunes:**
- `utf8mb4_unicode_ci` - Case-insensitive, correcto para la mayoría
- `utf8mb4_general_ci` - Más rápido pero menos preciso
- `utf8mb4_bin` - Case-sensitive, comparación binaria

### Strict Mode

```php
'mysql' => [
    // ...
    'strict' => true,  // Default: true (recomendado para producción)
]
```

**Strict Mode habilitado** (`true`): (Recomendado)
- Rechaza datos inválidos en INSERT/UPDATE
- Previene fechas cero (0000-00-00)
- Division por cero genera error
- Más seguro y predecible

**Strict Mode deshabilitado** (`false`):
- Datos inválidos se truncan silenciosamente
- Permite fechas cero
- División por cero retorna NULL
- Útil solo para compatibilidad con sistemas legacy

### Timezone

```php
'mysql' => [
    // ...
    'timezone' => '+00:00',  // UTC (recomendado)
    // 'timezone' => '-05:00',  // EST
    // 'timezone' => 'America/Santiago',  // Timezone name
]
```

Establece la zona horaria de la sesión MySQL. Recomendado usar UTC (`+00:00`) en la BD y manejar timezones en la aplicación.

### Unix Sockets (Conexiones Locales)

Para conexiones locales, los Unix sockets ofrecen mejor rendimiento que TCP:

```php
'mysql' => [
    'driver' => 'mysql',
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'secret',
    'unix_socket' => '/var/run/mysqld/mysqld.sock',  // Path al socket
    'charset' => 'utf8mb4',
    // 'host' y 'port' son ignorados cuando se usa unix_socket
]
```

**Rutas comunes de sockets:**
- Ubuntu/Debian: `/var/run/mysqld/mysqld.sock`
- RedHat/CentOS: `/var/lib/mysql/mysql.sock`
- macOS (Homebrew): `/tmp/mysql.sock`
- XAMPP: `/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`

### Variables de Sesión MySQL

Configura variables MySQL específicas para cada conexión:

```php
'mysql' => [
    // ...
    'session_variables' => [
        'sql_mode' => 'TRADITIONAL',
        'wait_timeout' => 28800,
        'interactive_timeout' => 28800,
        'max_execution_time' => 30000,
        'group_concat_max_len' => 1000000,
    ],
]
```

### Opciones PDO Adicionales

```php
'mysql' => [
    // ...
    'options' => [
        PDO::ATTR_TIMEOUT => 5,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        PDO::MYSQL_ATTR_FOUND_ROWS => true,
    ],
]
```

## Características Específicas de MySQL

### Identificadores con Backticks

El driver usa backticks para identificadores (tablas, columnas):

```php
// Internamente el driver genera:
SELECT `users`.`id`, `users`.`name` FROM `users` WHERE `status` = 'active'

// Escapa backticks dentro de identificadores:
// `column`with`backticks` -> `column``with``backticks`
```

### Niveles de Aislamiento de Transacciones

MySQL soporta 4 niveles de aislamiento:

```php
use PhobosFramework\Database\Connection\TransactionManager;

// READ UNCOMMITTED - Permite dirty reads
$tm->setIsolationLevel('READ UNCOMMITTED');

// READ COMMITTED - Previene dirty reads
$tm->setIsolationLevel('READ COMMITTED');

// REPEATABLE READ - Default de MySQL, previene non-repeatable reads
$tm->setIsolationLevel('REPEATABLE READ');

// SERIALIZABLE - Máximo aislamiento, previene phantom reads
$tm->setIsolationLevel('SERIALIZABLE');
```

**Nota:** MySQL requiere establecer el nivel de aislamiento **antes** de iniciar la transacción.

### Transacciones Anidadas (Savepoints)

MySQL soporta savepoints para transacciones anidadas:

```php
beginTransaction();  // Transacción principal

try {
    query()->insert('users')->values(['name' => 'John'])->execute();
    
    beginTransaction();  // Savepoint sp_1
    
    try {
        query()->insert('posts')->values(['title' => 'Post'])->execute();
        commit('sp_1');  // Commit savepoint
    } catch (Exception $e) {
        rollback('sp_1');  // Rollback solo el savepoint
    }
    
    commit();  // Commit transacción principal
} catch (Exception $e) {
    rollback();  // Rollback completo
}
```

### Operaciones de Mantenimiento

El driver proporciona métodos para mantenimiento de base de datos:

#### OPTIMIZE TABLE

Desfragmenta tablas, reclama espacio no utilizado y actualiza estadísticas:

```php
use PhobosFramework\Database\Drivers\MySQL\MySQLDriver;

$driver = new MySQLDriver();
$pdo = db()->getPdo();

// Optimizar tabla específica
$driver->optimizeTable($pdo, 'users');

// Útil después de:
// - Muchas operaciones DELETE
// - Muchas operaciones UPDATE que cambian tamaño de registros
// - Carga masiva de datos
```

#### ANALYZE TABLE

Actualiza estadísticas de la tabla para el optimizador de consultas:

```php
$driver->analyzeTable($pdo, 'products');

// Útil después de:
// - Cambios significativos en los datos
// - Inserciones masivas
// - Para mejorar planes de ejecución de queries
```

### Detección de MariaDB

El driver puede detectar si estás usando MariaDB:

```php
$driver = new MySQLDriver();
$pdo = db()->getPdo();

if ($driver->isMariaDB($pdo)) {
    echo "Usando MariaDB";
    // Habilitar características específicas de MariaDB
} else {
    echo "Usando MySQL";
}

// Obtener versión
$version = $driver->getServerVersion($pdo);
echo "Versión: $version";
```

## Ejemplos de Uso

### Configuración para Desarrollo Local

```php
'mysql_dev' => [
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'myapp_dev',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'strict' => false,  // Modo permisivo para desarrollo
    'unix_socket' => '/var/run/mysqld/mysqld.sock',
]
```

### Configuración para Producción

```php
'mysql_prod' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST'),
    'port' => env('DB_PORT', 3306),
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'strict' => true,  // Modo estricto para producción
    'timezone' => '+00:00',  // UTC
    'options' => [
        PDO::ATTR_TIMEOUT => 5,
        PDO::ATTR_PERSISTENT => false,
    ],
    'session_variables' => [
        'wait_timeout' => 28800,
        'interactive_timeout' => 28800,
    ],
]
```

### Configuración para Testing

```php
'mysql_test' => [
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'myapp_test',
    'username' => 'test_user',
    'password' => 'test_pass',
    'charset' => 'utf8mb4',
    'strict' => true,
    'unix_socket' => '/var/run/mysqld/mysqld.sock',
]
```

### Múltiples Conexiones MySQL

```php
'connections' => [
    'mysql_primary' => [
        'driver' => 'mysql',
        'host' => 'primary.db.server',
        'database' => 'myapp',
        'username' => 'app_user',
        'password' => 'secret',
    ],
    
    'mysql_replica' => [
        'driver' => 'mysql',
        'host' => 'replica.db.server',
        'database' => 'myapp',
        'username' => 'readonly_user',
        'password' => 'secret',
    ],
    
    'mysql_analytics' => [
        'driver' => 'mysql',
        'host' => 'analytics.db.server',
        'database' => 'analytics',
        'username' => 'analytics_user',
        'password' => 'secret',
    ],
]
```

Uso:

```php
// Escritura en primary
query('mysql_primary')
    ->insert('users')
    ->values(['name' => 'John'])
    ->execute();

// Lectura desde replica
$users = query('mysql_replica')
    ->select('*')
    ->from('users')
    ->fetch();

// Analytics
$stats = query('mysql_analytics')
    ->select('COUNT(*) as total')
    ->from('events')
    ->fetchOne();
```

## Arquitectura

### Estructura del Driver

```
MySQLDriver
    ├── getDSN()              - Construye DSN (tcp o unix_socket)
    ├── getPDOOptions()       - Opciones PDO específicas de MySQL
    ├── configure()           - Configuración post-conexión
    ├── getName()             - Retorna 'mysql'
    ├── supportsSavepoints()  - Retorna true
    ├── quoteIdentifier()     - Envuelve en backticks
    ├── getSetIsolationLevelSQL()  - SQL para isolation level
    ├── getServerVersion()    - Obtiene versión del servidor
    ├── isMariaDB()           - Detecta MariaDB
    ├── optimizeTable()       - OPTIMIZE TABLE
    └── analyzeTable()        - ANALYZE TABLE
```

### Proceso de Conexión

1. **DSN Generation**: `getDSN()` construye la cadena DSN
    - TCP: `mysql:host=localhost;port=3306;dbname=myapp`
    - Unix Socket: `mysql:unix_socket=/path/to/socket;dbname=myapp`

2. **PDO Creation**: Se crea instancia PDO con opciones
    - `MYSQL_ATTR_INIT_COMMAND` para charset/collation
    - Opciones adicionales del usuario

3. **Post-Connection Config**: `configure()` ejecuta
    - Establece timezone si está configurado
    - Configura strict mode
    - Aplica session variables personalizadas

## Compatibilidad

### Versiones de MySQL

- MySQL 5.7+
- MySQL 8.0+
- MySQL 8.1+
- MySQL 8.2+

### Versiones de MariaDB

- MariaDB 10.3+
- MariaDB 10.4+
- MariaDB 10.5+
- MariaDB 10.6+
- MariaDB 10.11+ (LTS)
- MariaDB 11.0+

## 🐛 Troubleshooting

### Error: "Access denied for user"

```
SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost'
```

**Solución:**
- Verifica usuario y contraseña en configuración
- Verifica que el usuario tenga permisos: `GRANT ALL PRIVILEGES ON mydb.* TO 'user'@'localhost';`
- Ejecuta `FLUSH PRIVILEGES;` después de cambiar permisos

### Error: "Can't connect to MySQL server"

```
SQLSTATE[HY000] [2002] Can't connect to MySQL server on 'localhost'
```

**Solución:**
- Verifica que MySQL esté corriendo: `systemctl status mysql`
- Verifica host y puerto en configuración
- Si usas `localhost`, prueba con `127.0.0.1`
- Verifica firewall: `sudo ufw allow 3306/tcp`

### Error: "Unknown database"

```
SQLSTATE[HY000] [1049] Unknown database 'myapp'
```

**Solución:**
- Crea la base de datos: `CREATE DATABASE myapp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
- Verifica el nombre en la configuración

### Error con Unix Socket

```
SQLSTATE[HY000] [2002] No such file or directory
```

**Solución:**
- Verifica la ruta del socket: `mysqladmin variables | grep socket`
- Verifica permisos del archivo socket
- Prueba con conexión TCP en su lugar

### Strict Mode Issues

Si tienes problemas con strict mode en aplicaciones legacy:

```php
'strict' => false,  // Desactiva strict mode temporalmente
```

O configura solo algunos modos:

```php
'session_variables' => [
    'sql_mode' => 'NO_ZERO_DATE,NO_ZERO_IN_DATE',
]
```

## Rendimiento

### Tips de Optimización

1. **Usa Unix Sockets para conexiones locales**
   ```php
   'unix_socket' => '/var/run/mysqld/mysqld.sock',
   ```

2. **Configura timeouts apropiados**
   ```php
   'options' => [
       PDO::ATTR_TIMEOUT => 5,
   ],
   'session_variables' => [
       'wait_timeout' => 28800,
   ],
   ```

3. **Usa conexiones persistentes con cuidado**
   ```php
   'options' => [
       PDO::ATTR_PERSISTENT => true,  // Solo en ambientes controlados
   ],
   ```

4. **Optimiza tablas regularmente**
   ```php
   // En un comando/cron
   $driver->optimizeTable($pdo, 'high_traffic_table');
   $driver->analyzeTable($pdo, 'high_traffic_table');
   ```

## Licencia

Este proyecto está licenciado bajo la Licencia MIT - ver el archivo [LICENSE](LICENSE.txt) para más detalles.

## Autor

**Marcel Rojas**  
[marcelrojas16@gmail.com](mailto:marcelrojas16@gmail.com)  
__Mongoose Studio__

## Contribuciones

Las contribuciones son bienvenidas. Por favor:

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/amazing-feature`)
3. Commit tus cambios (`git commit -m 'Add amazing feature'`)
4. Push a la rama (`git push origin feature/amazing-feature`)
5. Abre un Pull Request

---

**Phobos Framework** by Mongoose Studio
