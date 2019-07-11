<?php


namespace Guzaba2\Database\Sql\Mysql;


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
        'host'      => 'localhost',
        'port'      => 3306,
        'user'      => 'root',
        'password'  => '',
        'database'  => '',
    ];

    protected const CONFIG_RUNTIME = [];

    protected $MysqlCo;

    //public function __construct(array $options)
    public function __construct()
    {
        parent::__construct();
        //self::update_runtime_configuration($options);

        $this->MysqlCo = new \Swoole\Coroutine\Mysql();

        $ret = $this->MysqlCo->connect(static::CONFIG_RUNTIME);
        if (!$ret) {
            throw new ConnectionException(sprintf(t::_('Connection of class %s to %s:%s could not be established due to error: [%s] %s .'), get_class($this), self::CONFIG_RUNTIME['host'], self::CONFIG_RUNTIME['port'], $this->MysqlCo->connect_errno, $this->MysqlCo->connect_error ));
        }
    }

    public function prepare(string $query) : StatementInterface
    {
        try {
            $NativeStatement = $this->MysqlCo->prepare($query);
        } catch (ErrorException $exception) {
            throw new QueryException(sprintf(t::_('%s. Connection ID %s. Connection Pool status: %s. '), $exception->getMessage(), $this->get_object_internal_id(), print_r(ConnectionFactory::get_instance()->stats(get_class($this)), TRUE ) ));
        }
        if (!$NativeStatement) {
            throw new QueryException(sprintf(t::_('Preparing query "%s" failed with error: [%s] %s .'), $query, $this->MysqlCo->errno, $this->MysqlCo->error ));
        }
        $Statement = new StatementCoroutine($NativeStatement, $query);
        return $Statement;
    }

    public function close() : void
    {
        $this->MysqlCo->close();
    }

    public function is_connected() : bool
    {
        return $this->MysqlCo->connected;
    }
}