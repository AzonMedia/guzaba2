<?php


namespace Guzaba2\Database\Interfaces;

interface ConnectionInterface
{
    public function close() : void;

    public function free() : void;

    /*
    public function begin_transaction();

    public function commit();

    public function rollback();

    public function create_savepoint(string $savepoint);

    public function rollback_to_savepoint(string $savepoint);

    public function release_savepoint(string $savepoint);
    */
}
