<?php


namespace Guzaba2\Database;

use Guzaba2\Base\Base;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Database\Interfaces\ConnectionProviderInterface;

//use Guzaba2\Patterns\WorkerSingleton;

class ConnectionFactory extends Base
{
    /**
     * @var ConnectionProviderInterface
     */
    protected $ConnectionProvider;

    public function __construct(ConnectionProviderInterface $ConnectionProvider)
    {
        parent::__construct();
        $this->ConnectionProvider = $ConnectionProvider;
    }

    //TODO - add as a second argument a scope reference which when destroyed will free the connection
    /**
     * @param string $class_name
     * @param-out string $ScopeReference
     * @return ConnectionInterface
     */
    //public function get_connection(string $class_name, ?ScopeReference &$ScopeReference = NULL) : ConnectionInterface
    /**
     * @param string $class_name
     * @param $ScopeReference
     * @param-out $ScopeReference
     */
    public function get_connection(string $class_name, &$ScopeReference = '') : ConnectionInterface
    {
        return $this->ConnectionProvider->get_connection($class_name, $ScopeReference);
    }

    public function free_connection(ConnectionInterface $Connection) : void
    {
        $this->ConnectionProvider->free_connection($Connection);
    }

    public function stats(string $connection_class = '') : array
    {
        return $this->ConnectionProvider->stats($connection_class);
    }

    public function ping_connections(string $connection_class = '') : void
    {
        $this->ConnectionProvider->ping_connections($connection_class);
    }
}
