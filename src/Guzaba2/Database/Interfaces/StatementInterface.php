<?php

declare(strict_types=1);

namespace Guzaba2\Database\Interfaces;

use Guzaba2\Database\Sql\Mysql\Statement;

interface StatementInterface
{
    public function execute(array $parameters = []): Statement;

    public function fetchAll(): array;

    public function fetch_all(): array;

    public function fetch_row(string $column_name = '') /* mixed */;

    public function fetchRow(string $column_name = '') /* mixed */;

    public function get_connection(): \Guzaba2\Database\Sql\Interfaces\ConnectionInterface;

    public function getQuery(): string;

    public function get_query(): string;

    public function is_executed(): bool;
}
