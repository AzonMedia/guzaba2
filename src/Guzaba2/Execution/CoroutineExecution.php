<?php


namespace Guzaba2\Execution;

use Guzaba2\Patterns\CoroutineSingleton;

/**
 * Class CoroutineExecution
 * The only purpose of this class is to do cleanup of the CouroutineSingletons. If these are not used then this class can not be used as well.
 * @package Guzaba2\Execution
 */
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