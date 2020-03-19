<?php
declare(strict_types=1);


namespace Guzaba2\Database;

use Guzaba2\Base\Base;
use Guzaba2\Database\Interfaces\ConnectionFactoryInterface;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Database\Interfaces\ConnectionProviderInterface;
use Guzaba2\Resources\Interfaces\ResourceFactoryInterface;
use Guzaba2\Resources\Interfaces\ResourceInterface;
use Guzaba2\Resources\ScopeReference;

//use Guzaba2\Patterns\WorkerSingleton;

class ConnectionFactory extends Base implements ConnectionFactoryInterface
{
    /**
     * @var ConnectionProviderInterface
     */
    protected ConnectionProviderInterface $ConnectionProvider;

    public function __construct(ConnectionProviderInterface $ConnectionProvider)
    {
        parent::__construct();
        $this->ConnectionProvider = $ConnectionProvider;
    }

    /**
     * @param string $class_name
     * @param ScopeReference|null $ScopeReference
     * @return ConnectionInterface
     * @param-out ScopeReference|null $ScopeReference
     */
    public function get_connection(string $class_name, ?ScopeReference &$ScopeReference) : ConnectionInterface
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

    public function close_all_connections() : void
    {
        $this->ConnectionProvider->close_all_connections();
    }


    public function get_resource(string $class_name, &$ScopeReference = '') : ResourceInterface
    {
        return $this->get_connection($class_name, $ScopeReference);
    }

    public function free_resource(ResourceInterface $Resource) : void
    {
        $this->free_connection($Resource);
    }


}
