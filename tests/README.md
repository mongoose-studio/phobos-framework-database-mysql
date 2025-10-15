# Tests para PhobosFramework MySQL Driver

Esta suite de tests proporciona cobertura exhaustiva para el driver MySQL/MariaDB del Phobos Framework.

## Estructura de Tests

```
tests/
├── Unit/                          # Tests unitarios con mocks (no requieren base de datos)
│   └── Drivers/
│       └── MySQL/
│           └── MySQLDriverTest.php
└── Integration/                   # Tests de integración (requieren MySQL/MariaDB)
    └── MySQLDriverIntegrationTest.php
```

## Configuración

### Requisitos

- PHP 8.3 o superior
- Composer
- PHPUnit 10.5+
- Mockery 1.6+

### Instalación de Dependencias

```bash
composer install
```

## Ejecutar Tests

### Todos los tests

```bash
composer test
# o
./vendor/bin/phpunit
```

### Solo tests unitarios (no requieren base de datos)

```bash
composer test-unit
# o
./vendor/bin/phpunit --testsuite=Unit
```

### Solo tests de integración (requieren MySQL/MariaDB)

```bash
composer test-integration
# o
./vendor/bin/phpunit --testsuite=Integration
```

### Con reporte de cobertura

```bash
composer test-coverage
```

Esto generará un reporte HTML en el directorio `coverage/`.

## Tests Unitarios

Los tests unitarios (`tests/Unit/`) utilizan mocks de PDO y no requieren una base de datos real. Cubren:

### getDSN()
- Generación de DSN con TCP
- Generación de DSN con Unix socket
- Uso de puerto personalizado
- Uso de charset personalizado
- Validación de campos requeridos
- Validación de charset inválido
- Validación de charset no soportado
- Validación de rango de puerto
- Validación de puerto numérico

### getPDOOptions()
- Opciones por defecto de PDO
- Configuración de charset
- Configuración de collation
- Modo estricto habilitado por defecto
- Desactivación de modo estricto
- Validación de collation inválido
- Validación de formato de collation

### configure()
- Configuración de timezone
- Validación de formato de timezone
- Soporte para diferentes formatos de timezone (+00:00, UTC, America/New_York)
- Configuración de atributos PDO adicionales
- Configuración de variables de sesión
- Validación de nombres de variables de sesión
- Sanitización de valores de variables de sesión
- Manejo de variables booleanas

### Métodos de información
- getName() retorna 'mysql'
- supportsSavepoints() retorna true
- quoteIdentifier() usa backticks
- Escape de backticks en identificadores

### getSetIsolationLevelSQL()
- Generación de SQL correcto
- Validación de niveles de aislamiento
- Soporte para todos los niveles válidos

### getServerVersion()
- Retorna versión del servidor
- Manejo de errores en query
- Manejo de errores en fetch

### isMariaDB()
- Detección de MariaDB
- Detección de MySQL

### optimizeTable() y analyzeTable()
- Ejecución de comandos OPTIMIZE/ANALYZE
- Escape de nombres de tabla
- Retorno false en caso de error

**Total: 49 tests unitarios**

## Tests de Integración

Los tests de integración (`tests/Integration/`) requieren una base de datos MySQL o MariaDB real.

### Configuración de MySQL para Tests de Integración

Editar `phpunit.xml` y configurar las variables de entorno:

```xml
<php>
    <env name="DB_HOST" value="localhost"/>
    <env name="DB_PORT" value="3306"/>
    <env name="DB_DATABASE" value="test"/>
    <env name="DB_USERNAME" value="root"/>
    <env name="DB_PASSWORD" value=""/>
</php>
```

O exportar como variables de entorno:

```bash
export DB_HOST=localhost
export DB_PORT=3306
export DB_DATABASE=test
export DB_USERNAME=root
export DB_PASSWORD=
```

### Tests de Integración Cubiertos

- Establecimiento de conexión
- Configuración de charset
- Activación de modo estricto
- Obtención de versión del servidor
- Detección de MariaDB vs MySQL
- Creación de tablas e inserción de datos
- Optimización de tablas
- Análisis de tablas
- Manejo de errores con tablas inexistentes
- Configuración de nivel de aislamiento de transacciones
- Soporte de savepoints
- Configuración de timezone
- Configuración de variables de sesión
- Prepared statements
- Last insert ID

**Total: 15 tests de integración**

### Nota sobre Tests de Integración

Si MySQL/MariaDB no está disponible, los tests de integración se saltarán automáticamente con un mensaje informativo. Esto permite ejecutar la suite completa sin fallos cuando solo se quiere probar el código unitario.

## Cobertura de Código

La suite de tests proporciona cobertura completa del código del MySQLDriver:

- **Todos los métodos públicos** están cubiertos
- **Todos los métodos privados** de validación están cubiertos indirectamente
- **Todos los casos de error** están cubiertos
- **Todos los flujos de validación** están cubiertos

### Generar Reporte de Cobertura

```bash
composer test-coverage
```

Esto requiere que tengas Xdebug instalado. El reporte se genera en `coverage/index.html`.

## Convenciones de Tests

### Nomenclatura

- Los métodos de test siguen el patrón: `test_descripcion_del_comportamiento`
- Usar snake_case para nombres de métodos de test
- Ser descriptivo sobre lo que se está probando

### Estructura de un Test

```php
public function test_metodo_hace_algo_esperado(): void {
    // Arrange (configurar)
    $config = [...];

    // Act (ejecutar)
    $result = $this->driver->metodo($config);

    // Assert (verificar)
    $this->assertEquals($esperado, $result);
}
```

### Uso de Mockery

Para tests unitarios que requieren PDO:

```php
$this->pdo
    ->shouldReceive('exec')
    ->once()
    ->with('SQL esperado')
    ->andReturn(true);
```

## Tests de Seguridad

Los tests incluyen validación exhaustiva de seguridad:

- Prevención de inyección SQL en charset
- Prevención de inyección SQL en collation
- Prevención de inyección SQL en timezone
- Prevención de inyección SQL en variables de sesión
- Validación de niveles de aislamiento
- Sanitización de valores

## Contribuir

Al agregar nuevas funcionalidades al MySQLDriver:

1. **Escribir tests primero** (TDD)
2. **Cubrir casos de éxito y error**
3. **Validar seguridad** (inyección SQL, validación de entrada)
4. **Documentar** el comportamiento esperado
5. **Ejecutar toda la suite** antes de commit

## Troubleshooting

### Error: "MySQL connection not available"

Los tests de integración requieren MySQL/MariaDB. Verifica:

1. MySQL está corriendo: `mysql -u root -p`
2. Variables de entorno correctas en `phpunit.xml`
3. Usuario tiene permisos para crear/eliminar tablas

Puedes saltar estos tests ejecutando solo: `composer test-unit`

### Error: "Class 'Mockery' not found"

Instala las dependencias de desarrollo:

```bash
composer install --dev
```

### Error de cobertura

Para generar reportes de cobertura necesitas Xdebug:

```bash
# Verificar si está instalado
php -m | grep xdebug

# Instalar en macOS con Homebrew
pecl install xdebug
```

## CI/CD

Para integración continua, recomendamos:

```yaml
# Ejemplo GitHub Actions
- name: Run tests
  run: |
    composer install
    composer test-unit  # Solo unitarios en CI por defecto

# Opcionalmente con MySQL en CI:
- name: Setup MySQL
  run: |
    # Configurar MySQL service

- name: Run integration tests
  run: composer test-integration
```
