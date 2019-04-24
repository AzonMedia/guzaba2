<?php

namespace Guzaba2\Execution;

use Guzaba2\Patterns\ExecutionSingleton;

class Execution extends ExecutionSingleton
{

    public function __construct()
    {
        parent::__construct();
    }

    protected function _before_destruct() : void
    {
        ExecutionSingleton::cleanup();
    }
}