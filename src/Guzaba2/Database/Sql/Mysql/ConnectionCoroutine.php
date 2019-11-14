<?php


namespace Guzaba2\Database\Sql\Mysql;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Database\ConnectionFactory;
use Guzaba2\Database\Exceptions\ConnectionException;
use Guzaba2\Database\Exceptions\QueryException;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Database\Interfaces\StatementInterface;
use Guzaba2\Kernel\Exceptions\ErrorException;
use Guzaba2\Translator\Translator as t;

/**
 * Class ConnectionCoroutine
 * Because Swoole\Corotuine\Mysql\Statement does not support binding parameters by name, but only by position this class addresses this.
 * @package Guzaba2\Database\Sql\Mysql
 */
abstract class ConnectionCoroutine extends Connection
{

    //public function __construct(array $options)
    public function __construct()
    {
        parent::__construct();

        $this->connect();
    }

    public function connect() : void
    {
        $this->NativeConnection = new \Swoole\Coroutine\Mysql();
        $ret = $this->NativeConnection->connect(static::CONFIG_RUNTIME);
        if (!$ret) {
            throw new ConnectionException(sprintf(t::_('Connection of class %s to %s:%s could not be established due to error: [%s] %s .'), get_class($this), self::CONFIG_RUNTIME['host'], self::CONFIG_RUNTIME['port'], $this->NativeConnection->connect_errno, $this->NativeConnection->connect_error));
        }
    }

    public function prepare(string $query) : StatementInterface
    {
        if (!$this->get_coroutine_id()) {
            throw new RunTimeException(sprintf(t::_('Attempting to prepare a statement for query "%s" on a connection that is not assigned to any coroutine.'), $query));
        }
        $Statement = $this->prepare_statement($query, StatementCoroutine::class);
        return $Statement;
    }

    //TODO implement timeout parameter - on timeout this will interrupt the connection so the connection will need to be reestablished
    /**
     * Executes the provided queries in parallel
     * @param array $queries_data
     * @return array
     * @throws RunTimeException
     */
    public static function execute_parallel_queries(array $queries_data) : array
    {
        $callables = [];
        foreach ($queries_data as $query_data) {
            $query = $query_data['query'];
            $params = $query_data['params'];
            $called_class = get_called_class();
            $callables[] = static function () use ($query, $params, $called_class) : iterable {
                $Connection = static::get_service('ConnectionFactory')->get_connection($called_class, $CR);
                $Statement = $Connection->prepare($query);
                $Statement->execute($params);
                $data = $Statement->fetchAll();
                return $data;
            };
        }
        $ret = Coroutine::executeMulti(...$callables);
        return $ret;
    }
}
