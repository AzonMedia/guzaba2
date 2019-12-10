<?php
declare(strict_types=1);


namespace Guzaba2\Kernel\Interfaces;


interface ClassInitializationInterface
{
    /**
     * Must return an array of the initialization methods (method names or description) that were run.
     * @return array
     */
    public static function run_all_initializations() : array ;
}