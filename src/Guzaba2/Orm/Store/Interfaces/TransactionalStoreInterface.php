<?php
declare(strict_types=1);

namespace Guzaba2\Orm\Store\Interfaces;


use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Resources\ScopeReference;

interface TransactionalStoreInterface
{
    public function get_connection(?ScopeReference &$ScopeReference) : ConnectionInterface ;
}