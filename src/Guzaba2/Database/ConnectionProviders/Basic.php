<?php

declare(strict_types=1);

namespace Guzaba2\Database\ConnectionProviders;

use Guzaba2\Base\Base;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Database\Interfaces\ConnectionProviderInterface;
use Guzaba2\Resources\ScopeReference;

/**
 * Class Basic
 * This class establishes a new a connection on get_connection() and closes the connection on free_connection()
 * @package Guzaba2\Database\ConnectionProviders
 */
class Basic extends Provider
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get_connection(string $class_name, ?ScopeReference &$ScopeReference): ConnectionInterface
    {
        $ScopeReference = null;//not used
        $Connection = new $class_name();
        //$Connection->set_created_from_factory(TRUE);
        $Connection->assign_to_coroutine(Coroutine::getCid());
        return $Connection;
    }

    public function free_connection(ConnectionInterface $Connection): void
    {
        $Connection->close();
        $Connection->unassign_from_coroutine();
        $Connection = null;
    }

    public function close_all_connections(): void
    {
        //does nothing - there is nothing to close
    }

    public function stats(string $connection_class = ''): array
    {
        return [];
    }
}
