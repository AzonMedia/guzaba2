<?php

declare(strict_types=1);

namespace Guzaba2\Database\ConnectionProviders;

use Guzaba2\Base\Base;
use Guzaba2\Database\Interfaces\ConnectionProviderInterface;
use Guzaba2\Resources\Interfaces\ResourceInterface;
use Guzaba2\Resources\ScopeReference;

abstract class Provider extends Base implements ConnectionProviderInterface
{
    public function get_resource(string $class_name, ?ScopeReference &$ScopeReference): ResourceInterface
    {
        return $this->get_resource($class_name, $ScopeReference);
    }

    public function free_resource(ResourceInterface $Resource): void
    {
        $this->free_connection($Resource);
    }
}
