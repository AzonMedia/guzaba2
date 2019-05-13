<?php
declare(strict_types=1);

namespace Guzaba2\Execution;

use Guzaba2\Patterns\ExecutionSingleton;

class Execution extends ExecutionSingleton
{

    public function __construct()
    {
        parent::__construct();
        //at execution startup clear all instances in case something survived the previous execution
        ExecutionSingleton::cleanup();
    }

    protected function _before_destruct() : void
    {
        ExecutionSingleton::cleanup();
    }
}