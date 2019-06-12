<?php


namespace Guzaba2\Database;


use Guzaba2\Base\Base;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Database\Interfaces\ConnectionProviderInterface;
use Guzaba2\Patterns\WorkerSingleton;

class ConnectionFactory extends WorkerSingleton
{
    /**
     * @var ConnectionProviderInterface
     */
    protected $ConnectionProvider;

    public function set_connection_provider(ConnectionProviderInterface $ConnectionProvier) : void
    {
        $this->ConnectionProvider = $ConnectionProvier;
    }

    public function get_connection(string $class_name) : ConnectionInterface
    {
        if (!$this->ConnectionProvider) {

        }
        return $this->ConnectionProvider->get_connection($class_name);
    }

    public function free_connection(ConnectionInterface $Connection) : void
    {
        if(!$this->ConnectionProvider) {

        }
        $this->ConnectionProvider->free_connection($Connection);
    }

    public function stats(string $connection_class = '') : array
    {
        return $this->ConnectionProvider->stats($connection_class);
    }

}