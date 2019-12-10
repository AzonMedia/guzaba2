<?php
declare(strict_types=1);


namespace Guzaba2\Database\Sql;


use Guzaba2\Database\Interfaces\TransactionalConnectionInterface;

abstract class TransactionalConnection extends Connection implements TransactionalConnectionInterface
{

}