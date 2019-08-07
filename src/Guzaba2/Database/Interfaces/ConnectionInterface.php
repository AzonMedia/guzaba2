<?php


namespace Guzaba2\Database\Interfaces;

interface ConnectionInterface
{
    public function close() : void;

    public function free() : void;
}
