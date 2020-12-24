<?php

declare(strict_types=1);

namespace Guzaba2\Database\Sql;

use Guzaba2\Database\Sql\Interfaces\ConnectionInterface;
use Guzaba2\Database\Sql\Traits\ConnectionTrait;

abstract class TransactionalConnection extends \Guzaba2\Database\TransactionalConnection implements ConnectionInterface
{
    use ConnectionTrait;
}
