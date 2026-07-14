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

use PhobosFramework\Database\QueryBuilder\Grammar\Grammar;

/**
 * Gramática SQL para MySQL/MariaDB.
 *
 * MySQL cita identificadores con backticks (`` `col` ``) en lugar de las comillas dobles
 * ANSI. Soporta `DELETE ... LIMIT` (comportamiento por defecto) y no expone `RETURNING`
 * en MySQL, por lo que el id autogenerado se lee con `lastInsertId()`.
 */
class MySQLGrammar extends Grammar {

    /**
     * {@inheritdoc}
     *
     * Cita con backticks, escapando los backticks internos duplicándolos.
     */
    protected function wrapSegment(string $segment): string {
        return '`' . str_replace('`', '``', $segment) . '`';
    }
}
