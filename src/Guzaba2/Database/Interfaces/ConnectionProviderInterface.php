<?php

namespace Guzaba2\Database\Interfaces;

interface ConnectionProviderInterface
{
    public function get_connection(string $connection_class) : ConnectionInterface ;

    public function free_connection(ConnectionInterface $Connection);
}
