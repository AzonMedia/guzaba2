<?php


namespace Guzaba2\Database;

use Guzaba2\Transaction\Interfaces\TransactionTargetInterface;

abstract class ConnectionTransactional extends Connection implements TransactionTargetInterface
{
}
