<?php


namespace Guzaba2\Database;


class Memory implements TargetInterface
{

    /**
     * @return string
     */
    public function getTarget(): string
    {
        return 'memory';
    }
}