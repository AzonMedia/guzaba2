<?php

declare(strict_types=1);

namespace Guzaba2\Orm\Interfaces;

use Guzaba2\Orm\ObjectAlias;

/**
 * Interface ActiveRecordAliasInterface
 * @package Guzaba2\Orm\Interfaces
 */
interface ActiveRecordAliasInterface
{
    public function add_alias(string $alias): ObjectAlias;

    public function delete_alias(string $alias): void;

    public function delete_all_aliases(): void;

    public function get_alias(): ?string;

    public function get_all_aliases(): array;

    public static function get_by_alias(string $alias): self;
}