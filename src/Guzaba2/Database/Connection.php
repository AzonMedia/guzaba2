<?php

namespace Guzaba2\Database;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Translator\Translator as t;

abstract class Connection extends Base implements ConnectionInterface
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

    protected $scope_counter = 0;

    private $conn_start_time = 0;
    private $conn_end_time = 0;

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

    /**
     * To be called by the Pool when the connection is obtained
     */
    public function increment_scope_counter() : void
    {
        $this->scope_counter++;
    }

    public function decrement_scope_counter() : void
    {
        $this->scope_counter--;
        if ($this->scope_counter === 0) {
            if ($this->is_created_from_factory()) {
                self::ConnectionFactory()->free_connection($this);
            }
        }
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
            $Context = Coroutine::getContext();
            $Context->assignConnection($this);

            // increment the number of used connection in the current coroutine (APM)
            $Context->Apm->increment_value('cnt_used_connections', 1);

            // this is for incrementing the time_used_connections in unassign_from_coroutine()
            $this->conn_start_time = microtime(TRUE);
        }
    }

    public function unassign_from_coroutine() : void
    {

        //if ($this->scope_counter) {
        //    throw new RunTimeException(sprintf(t::_('It seems the connection is still referenced ')));
        //}

        if (!$this->get_coroutine_id()) {
            throw new RunTimeException(sprintf(t::_('The connection is not assigned to a coroutine so it can not be unassigned.')));
        }

        //TODO add a check - if there is running transaction throw an exception

        $this->coroutine_id = 0;
        if (Coroutine::getcid()) { //if we are in coroutine context
            $Context = Coroutine::getContext();
            $this->conn_end_time = microtime(TRUE);
            // increment the used connection time (APM)
            $Context->Apm->increment_value('time_used_connections', ($this->conn_end_time - $this->conn_start_time));

            Coroutine::getContext()->unassignConnection($this);
        }
    }

    /**
     * Frees this connection.
     * To be used if the connection is no longer used.
     * It is also used by the Guzaba\Coroutine\Coroutine at the end of coroutine execution to automatically free all connection that may have not be freed up by then (forgotten/hanging connections).
     * By having scope reference there is no longer need to explicitly release the connection with $Connection->free()
     */
    public function free() : void
    {
        $this->decrement_scope_counter();
    }

    public static function get_tprefix() : string
    {
        return static::CONFIG_RUNTIME['tprefix'] ?? '';
    }

    public static function get_database() : string
    {
        return static::CONFIG_RUNTIME['database'];
    }
}
