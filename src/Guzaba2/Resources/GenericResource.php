<?php
declare(strict_types=1);


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
 * While "resource" as of PHP 7.2. is not a reserved word lets avoid using it...
 * @package Guzaba2\Resources
 */
class GenericResource extends Base
{

    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'Apm',
        ],
    ];

    protected const CONFIG_RUNTIME = [];

    protected $scope_counter = 0;

    /**
     * @var int
     */
    protected $coroutine_id = 0;

    /**
     * @var ResourceFactoryInterface
     */
    protected $ResourceFactory;

    private $resource_obtained_time = 0;
    private $resource_released_time = 0;

    public function __construct(?ResourceFactoryInterface $ResourceFactory)
    {
        parent::__construct();
        $this->ResourceFactory = $ResourceFactory;
    }

    public function __destruct()
    {
        //$this->free();
        //$this->decrement_scope_counter();//no need
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
            $Context->{Resources::class}->assign_resource($this);
            // increment the number of used connection in the current coroutine (APM)
            if (self::has_service('Apm')) { //the service may not be defined by the Di
                $Apm = self::get_service('Apm');
                $Apm->increment_value('cnt_used_connections', 1);
            }

            // this is for incrementing the time_used_connections in unassign_from_coroutine()
            $this->resource_obtained_time = microtime(TRUE);
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
            $this->resource_released_time = microtime(TRUE);
            $Context = Coroutine::getContext();
            // increment the used connection time (APM)
            if (self::has_service('Apm')) { //the service may not be defined by the Di
                $Apm = self::get_service('Apm');
                $Apm->increment_value('time_used_connections', ($this->resource_released_time - $this->resource_obtained_time) );
            }

            $Context->{Resources::class}->unassign_resource($this);
        }
    }
}
