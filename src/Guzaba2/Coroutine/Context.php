<?php


namespace Guzaba2\Coroutine;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;
use Guzaba2\Base\Traits\SupportsObjectInternalId;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Translator\Translator as t;

/**
 * Class Context
 * A wrapper class around Swoole\Coroutine\Context
 * When feature request https://github.com/swoole/swoole-src/issues/2677 is completed then this class will be inheriting \Swoole\Coroutine\Context instead of wrapping around it
 * @package Guzaba2\Coroutine
 */
//class Context extends \Swoole\Coroutine\Context
class Context extends Base
implements ObjectInternalIdInterface
{

    use SupportsObjectInternalId;

    /**
     * @var \Swoole\Coroutine\Context
     */
    protected $Context;

    public function __construct(\Swoole\Coroutine\Context $Context, int $cid)
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
    }

    public function getCid() : int
    {
        return $this->Context->cid;
    }

    public function assignConnection(ConnectionInterface $Connection) : void
    {

        if (isset($this->Context->connections) && is_array($this->Context->connections)) {
            if (!in_array($Connection, $this->Context->connections)) {
                $this->Context->connections[] = $Connection;
            }
        }
    }

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
    public function getConnections() : array
    {
        $ret = [];
        if (isset($this->Context->connections) && is_array($this->Context->connections)) {
            $ret = $this->Context->connections;
        }
        return $ret;
    }

    /**
     * Frees all connections used by the coroutine if they werent freed manualyl before that.
     * To be called at coroutine end as a safety measure.
     */
    public function freeAllConnections() : void
    {
        //print 'FREEEEE';
        $connections = $this->getConnections();
        //print_r($connections);
        foreach ($connections as $Connection) {
            $Connection->free();
        }
    }

    public function __set(string $property, /* mixed */ $value) : void
    {
        $this->Context->{$property} = $value;
    }

    public function __get(string $property) /* mixed */
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
}