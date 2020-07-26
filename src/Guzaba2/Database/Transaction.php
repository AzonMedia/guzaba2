<?php

declare(strict_types=1);

namespace Guzaba2\Database;

use Guzaba2\Database\Interfaces\TransactionalConnectionInterface;
use Guzaba2\Resources\ScopeReference;
use Guzaba2\Transaction\Interfaces\TransactionalResourceInterface;

class Transaction extends \Guzaba2\Transaction\Transaction
{

    private TransactionalConnectionInterface $Connection;

    public function __construct(TransactionalConnectionInterface $Connection, array $options = [])
    {
        $this->Connection = $Connection;
        parent::__construct($options);
    }

    public function get_resource(): TransactionalResourceInterface
    {
        return $this->Connection;
    }

    protected function execute_begin(): void
    {
        $this->Connection->begin_transaction();
    }

    protected function execute_commit(): void
    {
        $this->Connection->commit_transaction();
    }

    protected function execute_save(): void
    {
        //does nothing
    }

    protected function execute_rollback(): void
    {
        $this->Connection->rollback_transaction();
    }

    protected function execute_create_savepoint(string $savepoint): void
    {
        $this->Connection->create_savepoint($savepoint);
    }

    protected function execute_rollback_to_savepoint(string $savepoint): void
    {
        $this->Connection->rollback_to_savepoint($savepoint);
    }

    protected function execute_release_savepoint(string $savepoint): void
    {
        $this->Connection->release_savepoint($savepoint);
    }

//    public function get_type() : string
//    {
//        return $this->Connection->get_resource_id();
//    }
}
