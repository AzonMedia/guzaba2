<?php
declare(strict_types=1);

namespace Guzaba2\Execution;

use Guzaba2\Patterns\ExecutionSingleton;
use Guzaba2\Workers\MemoryMonitor;

class Execution extends ExecutionSingleton
{

    /**
     * @var MemoryMonitor
     */
    protected $MemoryMonitor;

    public function __construct()
    {
        parent::__construct();
        //at execution startup clear all instances in case something survived the previous execution
        ExecutionSingleton::cleanup();
        //MemoryMonitor::get_instance();//needs to be invoked so that when the worker is started the constructor to be invoked and to log the initial data
        $this->MemoryMonitor = MemoryMonitor::get_instance();
    }

    protected function _before_destruct() : void
    {
        ExecutionSingleton::cleanup();
        //MemoryMonitor::get_instance()->check_memory();
        $this->MemoryMonitor->check_memory();
    }

    public function get_memory_usage() : array
    {
        return $this->MemoryMonitor->get_memory_usage();
    }
}