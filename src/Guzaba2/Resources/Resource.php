<?php


namespace Guzaba2\Resources;

use Azonmedia\Utilities\StackTraceUtil;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Coroutine\Resources;
use Guzaba2\Resources\Interfaces\ResourceFactoryInterface;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Base\Exceptions\BadMethodCallException;

/**
 * Class Resource
 * Represents a resource (for example a DB connection) that can be passed between coroutines.
 * @package Guzaba2\Resources
 */
class Resource extends Base
{
    protected $scope_counter = 0;

    /**
     * @var int
     */
    protected $coroutine_id = 0;

    /**
     * @var ResourceFactoryInterface
     */
    protected $ResourceFactory;

    private $conn_start_time = 0;
    private $conn_end_time = 0;

    public function __construct(?ResourceFactoryInterface $ResourceFactory)
    {
        parent::__construct();
        $this->ResourceFactory = $ResourceFactory;
    }

    public function __destruct()
    {
        //$this->free();
        $this->decrement_scope_counter();
        parent::__destruct();
    }

    /**
     * No matter what is the scope_counter release the resources.
     */
    public function force_release(): void
    {
        StackTraceUtil::validate_caller(Resources::class, '');
        $this->scope_counter = 0;
        $this->release();
    }

    protected function release(): void
    {
        if ($this->ResourceFactory) {
            $this->ResourceFactory->free_resource($this);
        }
    }

    /**
     * To be called by the Pool when the connection is obtained
     */
    public function increment_scope_counter(): void
    {
        StackTraceUtil::validate_caller(ResourceFactoryInterface::class, '');
        $this->scope_counter++;
    }

    public function decrement_scope_counter(): void
    {
        StackTraceUtil::validate_caller(ScopeReference::class, '');
        $this->scope_counter--;
        if ($this->scope_counter === 0) {
            $this->release();
        }
    }

    /**
     * Returns the coroutine ID to which this connection is currently assigned.
     * If not assigned returns 0.
     * @return int
     */
    public function get_coroutine_id(): int
    {
        return $this->coroutine_id;
    }

    /**
     * To be invoked when a connection is obtained.
     * @param int $cid
     */
    public function assign_to_coroutine(int $cid): void
    {
        if ($this->get_coroutine_id()) {
            throw new RunTimeException(sprintf(t::_('The connection is already assigned to another coroutine.')));
        }
        $this->coroutine_id = $cid;
        if (Coroutine::getcid()) { //if we are in coroutine context
            $Context = Coroutine::getContext();
            $Context->Resources->assign_resource($this);
            // increment the number of used connection in the current coroutine (APM)
            $Context->Apm->increment_value('cnt_used_connections', 1);
            // this is for incrementing the time_used_connections in unassign_from_coroutine()
            $this->conn_start_time = microtime(TRUE);
        }
    }

    /**
     * @throws RunTimeException
     */
    public function unassign_from_coroutine(): void
    {
        if (!$this->get_coroutine_id()) {
            throw new RunTimeException(sprintf(t::_('The connection is not assigned to a coroutine so it can not be unassigned.')));
        }

        //TODO add a check - if there is running transaction throw an exception

        $this->coroutine_id = 0;
        if (Coroutine::getcid()) { //if we are in coroutine context
            $this->conn_end_time = microtime(TRUE);
            $Context = Coroutine::getContext();
            // increment the used connection time (APM)
            $Context->Apm->increment_value('time_used_connections', ($this->conn_end_time - $this->conn_start_time));
            $Context->Resources->unassign_resource($this);
        }
    }
}
