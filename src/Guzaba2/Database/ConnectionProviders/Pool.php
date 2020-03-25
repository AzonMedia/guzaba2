<?php
declare(strict_types=1);


namespace Guzaba2\Database\ConnectionProviders;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Channel;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Coroutine\Resources;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Database\Interfaces\ConnectionProviderInterface;
use Guzaba2\Resources\ScopeReference;
use Guzaba2\Resources\Interfaces\ResourceInterface;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Kernel\Kernel;

//TODO - refactor to use channels and make it compatible with the preemptive scheduler
//currently if the coroutine is switched just after the if (count($this->available_connections[$connection_class])) { this can produce error
//while if channels are used then it will just block

/**
 * Class Pool
 * Provides a pool of connections (coroutine based connections) to be used with coroutines.
 * It can also work in non coroutine context.
 * @package Guzaba2\Database
 */
class Pool extends Provider
{
    protected const CONFIG_DEFAULTS = [
        'max_connections'   => 20,
        //'connections'       => [],
        'services'          => [
            'Apm',
        ],
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * Associative array with class_name as key and Channel as value.
     * The Channels contains multiple connections (the pool)
     * @var array
     */
    protected array $available_connections = [];

    /**
     * An associative array with class_name as key and ConnectionInterface as value.
     * To be used outside Coroutine context - there will be one connection per class in this case.
     * @var array
     */
    protected array $single_connections = [];

    /**
     * Pool constructor.
     * Must be executed at worker start, not before the server start.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * To be called immediately before going in coroutine mode.
     */
    public function close_all_connections() : void
    {
        foreach ($this->single_connections as $Connection) {
            $Connection->close();
        }
        $this->single_connections = [];//destroy the objects as well

        foreach ($this->available_connections as $Channel) {
            $length = $Channel->length();
            for ($i = 0; $i < $length; $i ++) {
                $Connection = $Channel->pop();
                $Connection->close();
            }
        }
        $this->available_connections = [];
    }

    /**
     * Obtains a connection (and marks it as busy).
     * It will reuse a connection from this coroutine if such is found.
     * If a new connection (second, third) for this coroutine is needed self::get_new_connection() is to be used
     * @param string $connection_class
     * @param ScopeReference|null $ScopeReference
     * @return ConnectionInterface
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @param-out $ScopeReference
     */
    public function get_connection(string $connection_class, ?ScopeReference &$ScopeReference) : ConnectionInterface
    {

        //debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
//        if (!Coroutine::inCoroutine()) {
//            throw new RunTimeException(sprintf(t::_('cConnections can be obtained from the Pool only in Coroutine context.')));
//        }
        //check the current scope does it has a connection

        if (Coroutine::inCoroutine()) {
            $Context = Coroutine::getContext();
            $Connection = $Context->{Resources::class}->get_resource($connection_class);//may return NULL
        }


        //no connection assigned to the current coroutine was found - assign a new one
        if (empty($Connection)) {
            $Connection = $this->get_new_connection($connection_class);

        }

        $Connection->increment_scope_counter();
//        if (!$ScopeReference) {
//            $ScopeReference = new \Guzaba2\Resources\ScopeReference($Connection);
//        }
        if ($ScopeReference) {
            throw new InvalidArgumentException(sprintf(t::_('An existing ScopeReference containing resource of class %s was provided to %s().'), get_class($ScopeReference->get_resource()), __METHOD__));
        }
        $ScopeReference = new \Guzaba2\Resources\ScopeReference($Connection);

        return $Connection;
    }

    /**
     * Frees the provided connection.
     * This is to be used only by Connection->free() / Connection->decrement_scope_counter()
     * @param ConnectionInterface $Connection
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function free_connection(ConnectionInterface $Connection) : void
    {
//        if (!Coroutine::inCoroutine()) {
//            throw new RunTimeException(sprintf(t::_('Connections can be freed in the Pool only in Coroutine context.')));
//        }

        if (Coroutine::inCoroutine()) {
            $connection_class = get_class($Connection);
            if (!array_key_exists($connection_class, $this->available_connections)) {
                throw new RunTimeException(sprintf(t::_('The provided connection is of class %s and the Pool has no knowledge of such class. It seems the provided connection was not created through this Pool.'), $connection_class));
            }
            $Connection->unassign_from_coroutine();
            $this->available_connections[$connection_class]->push($Connection);
        } else {
            //do not close it as it will be reused
            //$Connection->close();
            //$Connection = NULL;
        }
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
        if (!Coroutine::inCoroutine()) {
            throw new RunTimeException(sprintf(t::_('The method %s can be used only in coroutine context.'), __METHOD__ ));
        }
        if ($connection_class && array_key_exists($connection_class, $this->available_connections)) {
            $connection_classes = [$connection_class];
        } elseif (empty($connection_class)) {
            $connection_classes = array_keys($this->available_connections);
        } else {
            $connection_classes = [];
        }

        foreach ($connection_classes as $connection_class) {
            $length = $this->available_connections[$connection_class]->length();

            for ($i = 0; $i < $length; $i ++) {
                $Connection = $this->available_connections[$connection_class]->pop();

                try {
                    $Connection->ping();
                } catch (\Exception $Exception) {
                    $Connection->initialize();
                }

                $this->available_connections[$connection_class]->push($Connection);
            }
            //the last $Conn will stay alive => unset it
            unset($Connection);
        }
    }

    private function initialize_connections(string $connection_class) : void
    {
        if (!Coroutine::inCoroutine()) {
            throw new RunTimeException(sprintf(t::_('The method %s can be used only in coroutine context.'), __METHOD__ ));
        }
        $this->available_connections[$connection_class] = new Channel(self::CONFIG_RUNTIME['max_connections']);
        for ($aa = 0; $aa < self::CONFIG_RUNTIME['max_connections'] ; $aa++) {
            $Connection = new $connection_class();
            //$Connection->set_created_from_factory(TRUE);
            $this->available_connections[$connection_class]->push($Connection);
        }
    }

    /**
     * Returns a new connection. To be used when the current coroutine has no connection of this type already asigned (check in Coroutine\Context)
     * @param string $connection_class
     * @return ConnectionInterface
     * @throws RunTimeException
     * @throws InvalidArgumentException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    private function get_new_connection(string $connection_class) : ConnectionInterface
    {
//        if (\Swoole\Coroutine::getCid() === -1) {
//            throw new RunTimeException(sprintf(t::_('Connections can be obtained from the Pool only in Coroutine context.')));
//        }

        if (!class_exists($connection_class)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided connection class %s does not exist.'), $connection_class));
        }
        if (Coroutine::inCoroutine()) {
            if (!array_key_exists($connection_class, $this->available_connections)) {
                //TODO - add an option these to be initialized at server startup
                $this->initialize_connections($connection_class);
            }
            // increment the time for waiting for free connection

            $time_start_waiting = (double) microtime(TRUE);

            $Connection = $this->available_connections[$connection_class]->pop();//blocks and waits until one is available if there are no available ones

            $time_end_waiting = (double) microtime(TRUE);
            //$eps = 0.0001;
            $time_waiting_for_connection = $time_end_waiting - $time_start_waiting;

            //if (self::has_service('Apm') && abs($time_waiting_for_connection) > $eps )  {
            if (self::has_service('Apm') && abs($time_waiting_for_connection) > Kernel::MICROTIME_EPS )  {
                $Apm = self::get_service('Apm');
                $Apm->increment_value('time_waiting_for_connection', $time_waiting_for_connection);
            }

            $Connection->assign_to_coroutine(Coroutine::getCid());
        } else {
            if (!array_key_exists($connection_class, $this->single_connections)) {
                $this->single_connections[$connection_class] = new $connection_class();
            }
            return $this->single_connections[$connection_class];
        }



        return $Connection;
    }
}
