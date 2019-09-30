<?php


namespace Guzaba2\Database\ConnectionProviders;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Channel;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Database\Interfaces\ConnectionProviderInterface;
use Guzaba2\Database\ScopeReference;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Kernel\Kernel;

//TODO - refactor to use channels and make it compatible with the preemptive scheduler
//currently if the coroutine is switched just after the if (count($this->available_connections[$connection_class])) { this can produce error
//while if channels are used then it will just block

/**
 * Class Pool
 * Provides a pool of connections (coroutine based connections) to be used with coroutines.
 * @package Guzaba2\Database
 */
class Pool extends Base implements ConnectionProviderInterface
{
    protected const CONFIG_DEFAULTS = [
        'max_connections'   => 20,
        //'connections'       => [],
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * @var array
     */
    protected $available_connections = [];

    /**
     * Pool constructor.
     * Must be executed at worker start, not before the server start.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Returns a new connection no matter if there is already a connection provided to the current coroutine.
     * @param string $connection_class
     * @return ConnectionInterface
     * @throws RunTimeException
     * @throws InvalidArgumentException
     */
    public function get_new_connection(string $connection_class) : ConnectionInterface
    {
        if (\Swoole\Coroutine::getCid() === -1) {
            throw new RunTimeException(sprintf(t::_('Connections can be obtained from the Pool only in Coroutine context.')));
        }

        if (!class_exists($connection_class)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided connection class %s does not exist.'), $connection_class));
        }
        if (!array_key_exists($connection_class, $this->available_connections)) {
            $this->initialize_connections($connection_class);
        }
        $Connection = $this->available_connections[$connection_class]->pop();//blocks and waits until one is available if there are no available ones

        $Connection->assign_to_coroutine(Coroutine::getCid());

        return $Connection;
    }

    /**
     * Obtains a connection (and marks it as busy).
     * It will reuse a connection from this coroutine if such is found.
     * If a new connection (second, third) for this coroutine is needed self::get_new_connection() is to be used
     * @param string $connection_class
     * @param $ScopeReference
     * @param-out $ScopeReference
     * @return ConnectionInterface
     */
    //public function get_connection(string $connection_class, ?ScopeReference &$ScopeReference = NULL) : ConnectionInterface
    public function get_connection(string $connection_class, &$ScopeReference = '&') : ConnectionInterface
    {
        if (!Coroutine::inCoroutine()) {
            throw new RunTimeException(sprintf(t::_('Connections can be obtained from the Pool only in Coroutine context.')));
        }

        if (is_string($ScopeReference)) {
            throw new InvalidArgumentException(sprintf(t::_('There is no provided ScopeReference variable to %s.'), __METHOD__));
        }

        //check the current scope does it has a connection
        $Context = Coroutine::getContext();
        $Connection = $Context->getConnection($connection_class);//may return NULL

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
        if (!array_key_exists($connection_class, $this->available_connections)) {
            throw new RunTimeException(sprintf(t::_('The provided connection is of class %s and the Pool has no knowledge of such class. It seems the provided connection was not created through this Pool.'), $connection_class));
        }
        $Connection->unassign_from_coroutine();
        $this->available_connections[$connection_class]->push($Connection);
    }

    public function stats(string $connection_class = '') : array
    {
        $ret = [];
        if ($connection_class) {
            // $ret['available_connections'] = [];
            // if (array_key_exists($connection_class, $this->available_connections)) {
            //     foreach ($this->available_connections[$connection_class] as $AvaiableConnection) {
            //         $ret['available_connections'][] = $AvaiableConnection->get_object_internal_id();
            //     }
            // }

            // $ret['busy_connections'] = [];
            // if (array_key_exists($connection_class, $this->busy_connections)) {
            //     foreach ($this->busy_connections[$connection_class] as $BusyConnection) {
            //         $ret['busy_connections'][] = $BusyConnection->get_object_internal_id();
            //     }
            // }
        }

        return $ret;
    }

    /**
     * ping all available connections more often than the connection timeout
     * to be sure that they will stay alive forever
     */
    public function ping_connections(string $connection_class = '') : void
    {
        if ($connection_class && array_key_exists($connection_class, $this->available_connections)) {
            $length = $this->available_connections[$connection_class]->length();

            for ($i = 0; $i < $length; $i ++) {
                $Conn = $this->available_connections[$connection_class]->pop();

                try {
                    // print Kernel::get_worker_id().' ping'.PHP_EOL;
                    $Conn->ping();
                } catch (\Exception $exception) {
                    $Conn->initialize();
                }

                $this->available_connections[$connection_class]->push($Conn);

            }
            //the last $Conn will stay alive => unset it
            unset($Conn);
        }
    }

    private function initialize_connections(string $connection_class) : void
    {
        $this->available_connections[$connection_class] = new Channel(self::CONFIG_RUNTIME['max_connections']);
        for ($aa = 0; $aa < self::CONFIG_RUNTIME['max_connections'] ; $aa++) {
            $Connection = new $connection_class();
            $Connection->set_created_from_factory(TRUE);
            $this->available_connections[$connection_class]->push($Connection);
        }
    }
}
