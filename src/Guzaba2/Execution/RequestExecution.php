<?php
declare(strict_types=1);

namespace Guzaba2\Execution;

use Guzaba2\Patterns\RequestSingleton;
use Guzaba2\Workers\MemoryMonitor;

class RequestExecution extends RequestSingleton
{

    /**
     * @var MemoryMonitor
     */
    //protected $MemoryMonitor;

    protected function __construct()
    {
        parent::__construct();
        //at execution startup clear all instances in case something survived the previous execution
        RequestSingleton::cleanup();
        //MemoryMonitor::get_instance();//needs to be invoked so that when the worker is started the constructor to be invoked and to log the initial data
        //$this->MemoryMonitor = MemoryMonitor::get_instance();
    }

    protected function _before_destruct() : void
    {
        RequestSingleton::cleanup();
        //MemoryMonitor::get_instance()->check_memory();
        //$this->MemoryMonitor->check_memory();
    }

    public function get_memory_usage() : array
    {
        //return $this->MemoryMonitor->get_memory_usage();
        return [];
    }
}