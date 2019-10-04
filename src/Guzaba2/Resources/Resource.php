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
        if (!StackTraceUtil::validate_caller(Resources::class, '')) {
            throw new BadMethodCallException(sprintf(t::_('%s() can be called only from %s.'), __METHOD__, Resources::class));
        }
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
        if (!StackTraceUtil::validate_caller(ResourceFactoryInterface::class, '')) {
            throw new BadMethodCallException(sprintf(t::_('%s() can be called only from %s.'), __METHOD__, ResourceFactoryInterface::class));
        }
        $this->scope_counter++;
    }

    public function decrement_scope_counter(): void
    {
        if (!StackTraceUtil::validate_caller(ScopeReference::class, '')) {
            throw new BadMethodCallException(sprintf(t::_('%s() can be called only from %s. To explicitly free a resource please unset the corresponding ScopeReference.'), __METHOD__, ScopeReference::class));
        }
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
            Coroutine::getContext()->Resources->assign_resource($this);
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
            Coroutine::getContext()->Resources->unassign_resource($this);
        }
    }
}
