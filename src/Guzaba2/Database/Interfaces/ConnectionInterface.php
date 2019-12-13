<?php
declare(strict_types=1);


namespace Guzaba2\Database\Interfaces;

use Guzaba2\Resources\Interfaces\ResourceInterface;

interface ConnectionInterface extends ResourceInterface
{
    public function close() : void;

    //public function free() : void;

    /*
    public function begin_transaction();

    public function commit(\Guzaba2\Database\scopeReference &$scope_reference);

    public function rollback(\Guzaba2\Database\scopeReference &$scope_reference);

    public function create_savepoint(string $savepoint);

    public function rollback_to_savepoint(string $savepoint);

    public function release_savepoint(string $savepoint);
    */
}
