<?php


namespace Guzaba2\Database\ConnectionProviders;


use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Database\Interfaces\ConnectionProviderInterface;
use Guzaba2\Database\ScopeReference;
use Guzaba2\Translator\Translator as t;

/**
 * Class Pool
 * Provides a pool of connections (coroutine based connections) to be used with coroutines
 * @package Guzaba2\Database
 */
class Pool extends Base
implements ConnectionProviderInterface
{

    protected const CONFIG_DEFAULTS = [
        'max_connections'   => 20,
        //'connections'       => [],
    ];

    protected const CONFIG_RUNTIME = [];

    protected $busy_connections = [];
    protected $available_connections = [];
    protected $suspended_coroutines = [];

    public function __construct()
    {
        parent::__construct();

        //cant create the connections here - these need to be created inside the coroutine
//        foreach (self::CONFIG_RUNTIME['connections'] as $connection_class) {
//            for ($aa = 0; $aa < self::CONFIG_RUNTIME['max_connections']; $aa++) {
//                $this->available_connections[$connection_class][] = new $connection_class();
//            }
//        }

    }

    public function get_new_connection(string $connection_class) : ConnectionInterface
    {
        if (!Coroutine::inCoroutine()) {
            throw new RunTimeException(sprintf(t::_('Connections can be obtained from the Pool only in Coroutine context.')));
        }

        //this is a blocking function so that it always return a connection
        //it either blocks or throws an exception at the end if it cant return a connection
        if (!isset($this->available_connections[$connection_class])) {
            $this->available_connections[$connection_class] = [];
        }
        if (!isset($this->busy_connections[$connection_class])) {
            $this->busy_connections[$connection_class] = [];
        }

        //$r = microtime(true);

        //print 'CONN STATS A '.$r.' '.count($this->busy_connections[$connection_class]).' '.count($this->available_connections[$connection_class]).PHP_EOL;
        if (count($this->available_connections[$connection_class])) {

            //there are available connections
            //print 'AVAILABLE '.count($this->available_connections[$connection_class]).PHP_EOL;
            $Connection = array_pop($this->available_connections[$connection_class]);

            if ($Connection->is_connected()) {
                //print 'PUSH BUSY EXISTING '.$Connection->get_object_internal_id().PHP_EOL;
                array_push($this->busy_connections[$connection_class], $Connection);
                //add the connection reference to the coroutine context
                //this is needed because if the connection is not freed it will just hang
                //so we automate the connection freeing at the end of the coroutine execution
                //$Context = Coroutine::getContext();
                //a coroutine may obtain multiple connections
                //$Context->connections[] = $Connection;

                $Connection->assign_to_coroutine(Coroutine::getcid());


                return $Connection;
            } else {
                //print 'CLOSED'.PHP_EOL;
                $Connection->close();
                unset($Connection);
                return $this->get_connection($connection_class);//this should go to if (count(busy_connections)<max_connections)
            }
        } else {
            //there are no available connections

            if (count($this->busy_connections[$connection_class]) < self::CONFIG_RUNTIME['max_connections'] ) {
                //the total number of busy connections is below the max number of connections
                //so a new one can be created
                //print 'NEW CONNECTION '.$this->get_object_internal_id().PHP_EOL;
                $Connection = new $connection_class();
                $Connection->set_created_from_factory(TRUE);
                //print 'NEW CONNECTION '.$Connection->get_object_internal_id().PHP_EOL;
                //print 'PUSH BUSY NEW '.$Connection->get_object_internal_id().PHP_EOL;
                array_push($this->busy_connections[$connection_class], $Connection);
                //print 'BUSY CON '.count($this->busy_connections[$connection_class]).' '.self::CONFIG_RUNTIME['max_connections'].PHP_EOL;
                //print 'CONN STATS B '.$r.' '.count($this->busy_connections[$connection_class]).' '.count($this->available_connections[$connection_class]).PHP_EOL;

                $Connection->assign_to_coroutine(Coroutine::getcid());

                return $Connection;
            } else {
                //all connections are busy and no new ones can be created
                //suspend the current coroutine until some connections are freed
                $current_cid = Coroutine::getcid();
                $this->suspended_coroutines[] = $current_cid;
                //print 'SUSPEND'.PHP_EOL;
                Coroutine::suspend();

                //the connection will be resumed here
                //if it is resumed it is assumed that there are connections active
                return $this->get_connection($connection_class);
            }
        }
    }

    /**
     * Obtains a connection (and marks it as busy).
     * It will reuse a connection from this coroutine if such is found.
     * If a new connection (second, third) for this coroutine is needed self::get_new_connection() is to be used
     * @param string $connection_class
     * @return ConnectionInterface
     */
    //public function get_connection(string $connection_class, ?ScopeReference &$ScopeReference = NULL) : ConnectionInterface
    public function get_connection(string $connection_class, &$ScopeReference = '') : ConnectionInterface
    {

        if (is_string($ScopeReference)) {
            throw new InvalidArgumentException(sprintf(t::_('There is no provided ScopeReference variable to %s.'), __METHOD__));
        }

        if (!isset($this->available_connections[$connection_class])) {
            $this->available_connections[$connection_class] = [];
        }
        if (!isset($this->busy_connections[$connection_class])) {
            $this->busy_connections[$connection_class] = [];
        }

        $current_cid = Coroutine::getCid();
        if (count($this->busy_connections[$connection_class])) {
            foreach ($this->busy_connections[$connection_class] as $BusyConnection) {
                if ($BusyConnection->get_coroutine_id() === $current_cid) {
                    $Connection = $BusyConnection;
                    break;
                }
            }
        }

        //no connection assigned to the current coroutine was found - assign a new one
        if (empty($Connection)) {
            $Connection = $this->get_new_connection($connection_class);
        }

        $Connection->increment_scope_counter();
        if (!$ScopeReference) {
            $ScopeReference = new ScopeReference($Connection);
        }
        return $Connection;

    }

    /**
     * Frees the provided connection.
     * This is to be used only by Connection->free() / Connection->decrement_scope_counter()
     * @param ConnectionInterface $Connection
     * @throws RunTimeException
     */
    public function free_connection(ConnectionInterface $Connection) : void
    {
        if (!Coroutine::inCoroutine()) {
            throw new RunTimeException(sprintf(t::_('Connections can be freed in the Pool only in Coroutine context.')));
        }

        $connection_class = get_class($Connection);
        if (!isset($this->busy_connections[$connection_class])) {
            throw new RunTimeException(sprintf(t::_('The provided connection is of class %s and the Pool has no knowledge of such class. It seems the provided connection was not created through this Pool.'), get_class($Connection) ));
        }
        $connection_found = FALSE;
        foreach ($this->busy_connections[$connection_class] as $key => $BusyConnection) {
            if ($Connection === $BusyConnection) {
                $connection_found = TRUE;

                $Connection->unassign_from_coroutine();

                //$Connection = array_pop($this->busy_connections[$connection_class]);
                unset($this->busy_connections[$connection_class][$key]);
                $this->busy_connections[$connection_class] = array_values($this->busy_connections[$connection_class]);
                array_push($this->available_connections[$connection_class], $Connection);
                //check for any suspended coroutines that can be resumed
                if (count($this->suspended_coroutines)) {
                    $suspended_cid = array_pop($this->suspended_coroutines);
                    //print 'RESUME'.PHP_EOL;
                    Coroutine::resume($suspended_cid);
                }
            }
        }
        if (!$connection_found) {
            //lets see will it be found in the available connections
            foreach ($this->available_connections[$connection_class] as $AvailableConnection) {
                if ($Connection === $AvailableConnection) {
                    throw new RunTimeException(sprintf(t::_('The provided connection of class %s with ID %s to be freed was found in the available connection pool which is wrong.'), get_class($Connection), $Connection->get_object_internal_id() ));
                }
            }

            throw new RunTimeException(sprintf(t::_('The provided connection of class %s with ID %s does not seem to have been created from this Pool.'), get_class($Connection), $Connection->get_object_internal_id() ));
        }
    }

    public function stats(string $connection_class = '') : array
    {
        $ret = [];
        if ($connection_class) {
            $ret['available_connections'] = [];
            foreach ($this->available_connections[$connection_class] as $AvaiableConnection) {
                $ret['available_connections'][] = $AvaiableConnection->get_object_internal_id();
            }
            $ret['busy_connections'] = [];
            foreach ($this->busy_connections[$connection_class] as $BusyConnection) {
                $ret['busy_connections'][] = $BusyConnection->get_object_internal_id();
            }
        }
        return $ret;
    }
}