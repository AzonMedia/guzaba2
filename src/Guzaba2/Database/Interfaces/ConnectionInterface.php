<?php


namespace Guzaba2\Database\Interfaces;

interface ConnectionInterface
{
    public function close() : void;

    public function free() : void;

    /*
    public function begin_transaction();

    public function commit(\Guzaba2\Database\scopeReference &$scope_reference);

    public function rollback(\Guzaba2\Database\scopeReference &$scope_reference);

    public function create_savepoint(string $savepoint);

    public function rollback_to_savepoint(string $savepoint);

    public function release_savepoint(string $savepoint);
    */
}
