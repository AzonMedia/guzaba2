<?php

namespace Guzaba2\Database;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Translator\Translator as t;

abstract class Connection extends Base
implements ConnectionInterface
{

    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'ConnectionFactory'
        ]
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * @var int
     */
    protected $coroutine_id = 0;

    protected $is_created_from_factory_flag = FALSE;

    public function __construct()
    {
        parent::__construct();

    }

    public function __destruct()
    {

        //print 'CONNECTION DESTRUCTION'.PHP_EOL;
        //ConnectionFactory::get_instance()->free_connection($this);
        //self::ConnectionFactory()->free_connection($this);
        $this->free();
        parent::__destruct();
    }

//    public function set_created_from_pool(bool $is_created_from_pool_flag) : void
//    {
//        $this->is_created_from_pool_flag = $is_created_from_pool_flag;
//    }
//
//    public function is_created_from_pool() : bool
//    {
//        return $this->is_created_from_pool_flag;
//    }

    public function set_created_from_factory(bool $is_created_from_factory) : void
    {
        $this->is_created_from_factory_flag = $is_created_from_factory;
    }

    public function is_created_from_factory() : bool
    {
        return $this->is_created_from_factory_flag;
    }

    /**
     * Returns the coroutine ID to which this connection is currently assigned.
     * If not assigned returns 0.
     * @return int
     */
    public function get_coroutine_id() : int
    {
        return $this->coroutine_id;
    }

    /**
     * To be invoked when a connection is obtained.
     * @param int $cid
     */
    public function assign_to_coroutine(int $cid) : void
    {

        if ($this->get_coroutine_id()) {
            throw new RunTimeException(sprintf(t::_('The connection is already assigned to another coroutine.')));
        }
        $this->coroutine_id = $cid;
        if (Coroutine::getcid()) { //if we are in coroutine context
            Coroutine::getContext()->assignConnection($this);
        }

    }

    public function unassign_from_coroutine() : void
    {

        if (!$this->get_coroutine_id()) {
            throw new RunTimeException(sprintf(t::_('The connection is not assigned to a coroutine so it can not be unassigned.')));
        }
        $this->coroutine_id = 0;
        if (Coroutine::getcid()) { //if we are in coroutine context
            Coroutine::getContext()->unassignConnection($this);
        }
    }

    /**
     * Frees this connection.
     * To be used if the connection is no longer used.
     * It is also used by the Guzaba\Coroutine\Coroutine at the end of coroutine execution to automatically free all connection that may have not be freed up by then (forgotten/hanging connections).
     */
    public function free() : void
    {
        if ($this->is_created_from_factory()) {
            self::ConnectionFactory()->free_connection($this);
        }
    }

    /**
     *
     * @param array $queries Twodimensional indexed array containing 'query' and 'params' keys in the second dimension
     * @return array
     */
    public function execute_multiple_queries(array $queries) : array
    {

    }
}