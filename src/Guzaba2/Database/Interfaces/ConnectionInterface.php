<?php


namespace Guzaba2\Database\Interfaces;

interface ConnectionInterface
{
    public function close() : void;

    public function free() : void;

    public function beginTransaction();

    public function commit();

    public function rollback();

    public function createSavepoint($savepoint);

    public function rollbackToSavepoint($savepoint);

    public function releaseSavepoint($savepoint);
}
