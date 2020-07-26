<?php

declare(strict_types=1);

namespace Guzaba2\Orm\Store\Interfaces;

interface StructuredStoreInterface
{
    public function get_class_id(string $class_name): ?int;
}
