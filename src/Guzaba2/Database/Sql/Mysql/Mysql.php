<?php

declare(strict_types=1);

namespace Guzaba2\Database\Sql\Mysql;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Translator\Translator as t;

abstract class Mysql extends Base
{

    /**
     * A map between Mysql types and PHP types
     */
    public const TYPES_MAP = [
        'null'                      => 'null',
        'int'                       => 'int',
        'varchar'                   => 'string',
        'timestamp'                 => 'string',
        'enum'                      => 'string',
        'char'                      => 'string',
        'date'                      => 'string',
        'float'                     => 'double',
        'bool'                      => 'bool',
        'text'                      => 'string',
        'bigint'                    => 'int',
        'decimal'                   => 'double',
        'tinytext'                  => 'string',
        'mediumtext'                => 'string',
        'longtext'                  => 'string',
        'tinyint'                   => 'int',
        'smallint'                  => 'int',
        'mediumint'                 => 'int',
        'binary'                    => 'string',
        'varbinary'                 => 'string',
        'time'                      => 'int',
        'datetime'                  => 'string',
        'double unsigned'           => 'double',//??
        'double'                    => 'double',
        'blob'                      => 'string',
        'mediumblob'                => 'string',
        'longblob'                  => 'string',
    ];

    public static function get_column_size(array $row): int
    {
        //TODO - test all types
        if (!empty($row['CHARACTER_MAXIMUM_LENGTH'])) {
            return $row['CHARACTER_MAXIMUM_LENGTH'];
        } elseif (!empty($row['CHARACTER_OCTET_LENGTH'])) {
            return $row['CHARACTER_OCTET_LENGTH'];
        } elseif (!empty($row['NUMERIC_PRECISION'])) {
            return $row['NUMERIC_PRECISION'];
        } else {
            throw new InvalidArgumentException(sprintf(t::_('Unable to determine the column size based on the provided data.')));
        }
    }
}
