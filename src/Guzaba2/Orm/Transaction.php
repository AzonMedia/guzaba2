<?php
declare(strict_types=1);

namespace Guzaba2\Orm;


use Guzaba2\Transaction\DistributedTransaction;
use Guzaba2\Transaction\Interfaces\TransactionalResourceInterface;

class Transaction extends DistributedTransaction
{


    public function get_resource(): TransactionalResourceInterface
    {
        // TODO: Implement get_resource() method.
    }

    protected function execute_begin(): void
    {
        // TODO: Implement execute_begin() method.
    }

    protected function execute_commit(): void
    {
        // TODO: Implement execute_commit() method.
    }

    protected function execute_save(): void
    {
        // TODO: Implement execute_save() method.
    }

    protected function execute_rollback(): void
    {
        // TODO: Implement execute_rollback() method.
    }

    protected function execute_create_savepoint(string $savepoint): void
    {
        // TODO: Implement execute_create_savepoint() method.
    }

    protected function execute_rollback_to_savepoint(string $savepoint): void
    {
        // TODO: Implement execute_rollback_to_savepoint() method.
    }

    protected function execute_release_savepoint(string $savepoint): void
    {
        // TODO: Implement execute_release_savepoint() method.
    }
}