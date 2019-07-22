<?php


namespace Guzaba2\Database\Nosql\Redis;


use Guzaba2\Base\Base;
use Guzaba2\Database\Connection;
use Guzaba2\Database\ConnectionFactory;
use Guzaba2\Database\Exceptions\ConnectionException;
use Guzaba2\Database\Exceptions\QueryException;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Database\Interfaces\StatementInterface;
use Guzaba2\Kernel\Exceptions\ErrorException;
use Guzaba2\Translator\Translator as t;

abstract class ConnectionCoroutine extends Connection
{
    protected const CONFIG_DEFAULTS = [
        'host' => '127.0.0.1',
        'port' => '6379',
        'timeout' => 1.5,
        'password' => '',
        'database' => 0
    ];

    protected const CONFIG_RUNTIME = [];

    protected $RedisCo;

    public function __construct()
    {
        parent::__construct();

        $this->RedisCo = new \Swoole\Coroutine\Redis(static::CONFIG_RUNTIME);
        $this->RedisCo->connect(static::CONFIG_RUNTIME['host'], static::CONFIG_RUNTIME['port']);

        if (! $this->RedisCo->connected) {
            throw new ConnectionException(sprintf(t::_('Connection of class %s to %s:%s could not be established due to error: [%s] %s .'), get_class($this), self::CONFIG_RUNTIME['host'], self::CONFIG_RUNTIME['port'], $this->RedisCo->errCode, $this->RedisCo->errMsg ));
        }
    }

    public function close() : void
    {
        $this->RedisCo->close();
    }
}