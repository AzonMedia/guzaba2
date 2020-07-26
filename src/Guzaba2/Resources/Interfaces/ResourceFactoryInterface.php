<?php

declare(strict_types=1);

namespace Guzaba2\Resources\Interfaces;

use Guzaba2\Resources\ScopeReference;

interface ResourceFactoryInterface
{
    public function get_resource(string $class_name, ?ScopeReference &$ScopeReference): ResourceInterface;

    public function free_resource(ResourceInterface $Connection): void;
}
