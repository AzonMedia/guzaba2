<?php


namespace Guzaba2\Execution;

use Guzaba2\Patterns\CoroutineSingleton;

//NOT USED
class CoroutineExecution extends CoroutineSingleton
{

    protected function __construct()
    {
        parent::__construct();
    }

    protected function _before_destruct() : void
    {
        CoroutineSingleton::cleanup();
    }

}