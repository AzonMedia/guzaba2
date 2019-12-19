<?php
declare(strict_types=1);


namespace Guzaba2\Database\Interfaces;

use Guzaba2\Resources\Interfaces\ResourceInterface;

interface ConnectionInterface extends ResourceInterface
{
    public function close() : void;

    /**
     * Returns an associative array with the used connection options.
     * @return array
     */
    public function get_options() : array ;

    public static function get_supported_options() : array ;

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
