<?php

namespace Guzaba2\Database\Interfaces;

interface ConnectionProviderInterface
{
    /**
     * @param string $connection_class
     * @todo check if scope reference parameter is required
     * @return ConnectionInterface
     */
    public function get_connection(string $connection_class) : ConnectionInterface ;

    public function free_connection(ConnectionInterface $Connection);

    public function stats($connection_class);
}
