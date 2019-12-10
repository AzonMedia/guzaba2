<?php
declare(strict_types=1);

namespace Guzaba2\Database\Interfaces;

use Guzaba2\Resources\Interfaces\ResourceFactoryInterface;
use Guzaba2\Resources\ScopeReference;

interface ConnectionProviderInterface extends ResourceFactoryInterface
{
    /**
     * @param string $connection_class
     * @todo check if scope reference parameter is required
     * @return ConnectionInterface
     */
    public function get_connection(string $connection_class, ?ScopeReference &$ScopeReference) : ConnectionInterface ;

    public function free_connection(ConnectionInterface $Connection);

    public function stats(string $connection_class = '') : array;

    public function close_all_connections() : void ;
}
