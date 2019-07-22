<?php


namespace Guzaba2\Database;


use Guzaba2\Database\Interfaces\ConnectionInterface;

class ScopeReference extends \Guzaba2\Patterns\ScopeReference
{

    protected $Connection;

    public function __construct(ConnectionInterface $Connection)
    {

        $this->Connection = $Connection;
        $Function = static function () use ($Connection) { //if it is not declared as a satic function one more reference to $this is created and this defeats the whole purpose of the scopereference - to have a single reference to it. The destructor will not get called.
            $Connection->free();
        };
        parent::__construct($Function);
    }

    public function get_connection() : ConnectionInterface
    {
        return $this->Connection;
    }

    public function __destruct()
    {
        parent::__destruct();
    }
}