<?php


namespace Guzaba2\Database;

//use Guzaba2\Transaction\Interfaces\TransactionTargetInterface;
use Azonmedia\Transaction\Interfaces\SupportsTransactionInterface;

abstract class ConnectionTransactional extends Connection implements SupportsTransactionInterface
{
}
