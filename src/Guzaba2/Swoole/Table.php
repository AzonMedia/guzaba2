<?php

declare(strict_types=1);

namespace Guzaba2\Swoole;

use Guzaba2\Base\Base;

abstract class Table extends Base
{

    /**
     * Contains mapping between PHP and Swoole Table types
     */
    public const TYPES_MAP = [
        'int'       => \Swoole\Table::TYPE_INT,
        'integer'   => \Swoole\Table::TYPE_INT,
        'float'     => \Swoole\Table::TYPE_FLOAT,
        'double'    => \Swoole\Table::TYPE_FLOAT,
        'string'    => \Swoole\Table::TYPE_STRING,
    ];
}
