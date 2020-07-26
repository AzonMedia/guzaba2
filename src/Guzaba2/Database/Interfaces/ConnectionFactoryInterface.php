<?php

declare(strict_types=1);

namespace Guzaba2\Database\Interfaces;

use Guzaba2\Resources\Interfaces\ResourceFactoryInterface;
use Guzaba2\Resources\ScopeReference;

interface ConnectionFactoryInterface extends ResourceFactoryInterface
{
    public function get_connection(string $class_name, ?ScopeReference &$ScopeReference): ConnectionInterface;

    public function free_connection(ConnectionInterface $Connection): void;

    public function stats(string $connection_class = ''): array;

    public function ping_connections(string $connection_class = ''): void;

    public function close_all_connections(): void;
}
