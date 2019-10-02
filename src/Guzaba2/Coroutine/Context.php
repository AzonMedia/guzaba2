<?php


namespace Guzaba2\Coroutine;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;
use Guzaba2\Base\Traits\SupportsObjectInternalId;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Translator\Translator as t;
use Psr\Http\Message\RequestInterface;

/**
 * Class Context
 * A wrapper class around Swoole\Coroutine\Context
 * When feature request https://github.com/swoole/swoole-src/issues/2677 is completed then this class will be inheriting \Swoole\Coroutine\Context instead of wrapping around it
 * @package Guzaba2\Coroutine
 */
//class Context extends \Swoole\Coroutine\Context
class Context extends Base implements ObjectInternalIdInterface
{
    use SupportsObjectInternalId;

    /**
     * @var \Swoole\Coroutine\Context
     */
    protected $Context;

    /**
     * Context constructor.
     * @param \Swoole\Coroutine\Context $Context
     * @param int $cid
     * @throws InvalidArgumentException
     */
    public function __construct(\Swoole\Coroutine\Context $Context, int $cid, ?RequestInterface $Request = NULL)
    {
        if (!$cid) {
            throw new InvalidArgumentException(sprintf(t::_('Coroutine ID must be provided when creating a %s object.'), __CLASS__));
        }
        $this->Context = $Context;

        $this->Context->cid = $cid;
        $this->Context->connections = [];
        $this->Context->settings = [];
        $this->Context->static_store = [];//to be used by StaticStore trait
        $this->set_object_internal_id();

        $this->Context->Request = $Request;

        if (Coroutine::completeBacktraceEnabled()) {
            $this->Context->created_backtrace = [];
            $pcid = Coroutine::getPcid();
            if ($pcid > 0) {
                //there is parent coroutine - lets save the backtrace where this coroutine was created
                //by getting the backtrace of the parent coroutine
                $this->Context->created_backtrace = \Swoole\Coroutine::getBackTrace($pcid, \DEBUG_BACKTRACE_IGNORE_ARGS);
            }
        }

    }

    public function getBacktrace() : array
    {
        return $this->Context->created_backtrace;
    }

    /**
     * Returns the coroutine ID to which this context is attached.
     * @return int
     */
    public function getCid() : int
    {
        return $this->Context->cid;
    }

    public function getRequest() : ?RequestInterface
    {
        return $this->Context->Request;
    }

    /**
     * Assign a connection to the coroutine context.
     * @param ConnectionInterface $Connection
     */
    public function assignConnection(ConnectionInterface $Connection) : void
    {
        if (isset($this->Context->connections) && is_array($this->Context->connections)) {
            if (!in_array($Connection, $this->Context->connections)) {
                $this->Context->connections[] = $Connection;
            }
        }
    }

    /**
     * Unassign a connection from the coroutine context. This is to be called when the coroutine no longer uses this connection.
     * @param ConnectionInterface $Connection
     */
    public function unassignConnection(ConnectionInterface $Connection) : void
    {
        if (isset($this->Context->connections) && is_array($this->Context->connections)) {
            foreach ($this->Context->connections as $key=>$AssignedConnection) {
                if ($Connection === $AssignedConnection) {
                    unset($this->Context->connections[$key]);
                }
            }
            $this->Context->connections = array_values($this->Context->connections);
        }
    }

    /**
     * @return array Array of ConnectionInterface
     */
    public function getConnections(?string $connection_class = NULL) : array
    {
        $ret = [];
        if (isset($this->Context->connections) && is_array($this->Context->connections)) {
            $all_connections = $this->Context->connections;
            $ret = $all_connections;
        }

        if ($connection_class && !empty($all_connections)) {
            $ret = [];
            if (!class_exists($connection_class)) {
                throw new InvalidArgumentException(sprintf(t::_('The provided connection_class %s does not exist.')));
            }
            foreach ($all_connections as $Connection) {
                if (get_class($Connection) === $connection_class) {
                    $ret[] = $Connection;
                }
            }
        }
        return $ret;
    }

    /**
     * Usually acoroutine has only one connection of certain class.
     * This class returns this connection if the coroutine has assigned one
     * @param string $connection_class
     * @return ConnectionInterface|NULL
     */
    public function getConnection(string $connection_class) : ?ConnectionInterface
    {
        $connections = $this->getConnections($connection_class);
        $ret = $connections[0] ?? NULL;
        return $ret;
    }

    /**
     * Frees all connections used by the coroutine if they werent freed manualyl before that.
     * To be called at coroutine end as a safety measure.
     */
    public function freeAllConnections() : void
    {
        $connections = $this->getConnections();
        foreach ($connections as $Connection) {
            $Connection->free();
        }
    }

    public function __set(string $property, /* mixed */ $value) : void
    {
        $this->Context->{$property} = $value;
    }

    public function &__get(string $property) /* mixed */
    {
        return $this->Context->{$property};
    }

    public function __isset(string $property) : bool
    {
        return isset($this->Context->{$property});
    }

    public function __unset(string $property) : void
    {
        unset($this->Context->{$property});
    }

    public function __call(string $method, array $args) /* mixed */
    {
        return call_user_func_array([$this->Context, $method], $args);
    }

    public function __staticCall(string $method, array $args) /* mixed */
    {
        return call_user_func_array([self::class, $method], $args);
    }

    public function __destruct()
    {
        $this->freeAllConnections();
        $this->Context = NULL;
    }
}
