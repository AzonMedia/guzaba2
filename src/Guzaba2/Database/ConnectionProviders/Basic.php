<?php


namespace Guzaba2\Database\ConnectionProviders;

use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Database\Interfaces\ConnectionProviderInterface;
use Guzaba2\Patterns\WorkerSingleton;

/**
 * Class Basic
 * This class establishes a new a connection on get_connection() and closes the connection on free_connection()
 * @package Guzaba2\Database\ConnectionProviders
 */
class Basic extends WorkerSingleton implements ConnectionProviderInterface
{
    public function get_connection(string $class_name) : ConnectionInterface
    {
        $Connection = new $class_name();
        $Connection->set_created_from_factory(TRUE);
        return $Connection;
    }

    public function free_connection(ConnectionInterface $Connection) : void
    {
        $Connection->close();
        $Connection = NULL;
    }

    public function stats(string $connection_class = '') : array
    {
        return [];
    }
}
