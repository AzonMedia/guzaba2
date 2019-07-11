<?php


namespace Guzaba2\Database\ConnectionProviders;


use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Database\Interfaces\ConnectionProviderInterface;
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
        'max_connections'   => 1,
        //'connections'       => [],
    ];

    protected static $CONFIG_RUNTIME = [];

    protected $busy_connections = [];
    protected $available_connections = [];
    protected $suspended_coroutines = [];

    protected $is_initialized_flag = FALSE;

    public function __construct(array $options = [])
    {
        parent::update_runtime_configuration($options);

        //cant create the connections here - these need to be created inside the coroutine
//        foreach (self::$CONFIG_RUNTIME['connections'] as $connection_class) {
//            for ($aa = 0; $aa < self::$CONFIG_RUNTIME['max_connections']; $aa++) {
//                $this->available_connections[$connection_class][] = new $connection_class();
//            }
//        }
        $this->is_initialized_flag = TRUE;
    }

//    public function initialize(array $options) : void
//    {
//        //create the initial set of connections
//        parent::update_runtime_configuration($options);
//
//        //cant create the connections here - these need to be created inside the coroutine
////        foreach (self::$CONFIG_RUNTIME['connections'] as $connection_class) {
////            for ($aa = 0; $aa < self::$CONFIG_RUNTIME['max_connections']; $aa++) {
////                $this->available_connections[$connection_class][] = new $connection_class();
////            }
////        }
//        //print $this->object_internal_id.PHP_EOL;
//        $this->is_initialized_flag = TRUE;
//    }

    public function is_initialized() : bool
    {
        return $this->is_initialized_flag;
    }

    public function get_connection(string $connection_class) : ConnectionInterface
    {

        //print 'GET '.count($this->available_connections[$connection_class]).PHP_EOL;
        if (!$this->is_initialized()) {

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
                return $Connection;
            } else {
                //print 'CLOSED'.PHP_EOL;
                $Connection->close();
                unset($Connection);
                return $this->get_connection($connection_class);//this should go to if (count(busy_connections)<max_connections)
            }
        } else {
            //there are no available connections

            if (count($this->busy_connections[$connection_class]) < self::$CONFIG_RUNTIME['max_connections'] ) {
                //the total number of busy connections is below the max number of connections
                //so a new one can be created
                //print 'NEW CONNECTION '.$this->get_object_internal_id().PHP_EOL;
                $Connection = new $connection_class();
                //print 'NEW CONNECTION '.$Connection->get_object_internal_id().PHP_EOL;
                //print 'PUSH BUSY NEW '.$Connection->get_object_internal_id().PHP_EOL;
                array_push($this->busy_connections[$connection_class], $Connection);
                //print 'BUSY CON '.count($this->busy_connections[$connection_class]).' '.self::$CONFIG_RUNTIME['max_connections'].PHP_EOL;
                //print 'CONN STATS B '.$r.' '.count($this->busy_connections[$connection_class]).' '.count($this->available_connections[$connection_class]).PHP_EOL;
                return $Connection;
            } else {
                //all connections are busy and no new ones can be created
                //suspend the current coroutine until some connections are freed
                $current_cid = \Co::getcid();
                $this->suspended_coroutines[] = $current_cid;
                //print 'SUSPEND'.PHP_EOL;
                \Co::suspend();

                //the connection will be resumed here
                //if it is resumed it is assumed that there are connections active
                return $this->get_connection($connection_class);
            }
        }

    }

    /**
     * @param ConnectionInterface $Connection
     * @throws RunTimeException
     */
    public function free_connection(ConnectionInterface $Connection) : void
    {

        if (!$this->is_initialized()) {

        }

        $connection_class = get_class($Connection);
        if (!isset($this->busy_connections[$connection_class])) {
            throw new RunTimeException(sprintf(t::_('The provided connection is of class %s and the Pool has no knowledge of such class. It seems the provided connection was not created through this Pool.'), get_class($Connection) ));
        }
        $connection_found = FALSE;
        foreach ($this->busy_connections[$connection_class] as $key => $BusyConnection) {
            if ($Connection === $BusyConnection) {
                $connection_found = TRUE;
                //$Connection = array_pop($this->busy_connections[$connection_class]);
                unset($this->busy_connections[$connection_class][$key]);
                $this->busy_connections[$connection_class] = array_values($this->busy_connections[$connection_class]);
                array_push($this->available_connections[$connection_class], $Connection);
                //check for any suspended coroutines that can be resumed
                if (count($this->suspended_coroutines)) {
                    $suspended_cid = array_pop($this->suspended_coroutines);
                    //print 'RESUME'.PHP_EOL;
                    \Co::resume($suspended_cid);
                }
            }
        }
        if (!$connection_found) {
            //lets see will it be found in the available connections
            foreach ($this->available_connections[$connection_class] as $AvailableConnection) {
                if ($Connection === $AvailableConnection) {
                    //print_r($this->available_connections);
                    //print_r($this->busy_connections);
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