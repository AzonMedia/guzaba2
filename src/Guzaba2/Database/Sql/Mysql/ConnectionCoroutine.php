<?php
declare(strict_types=1);


namespace Guzaba2\Database\Sql\Mysql;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
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
 * The Coroutine/Mysql connection is always initialized with strict_mode = TRUE and fetch_mode = TRUE (meaning
 * @package Guzaba2\Database\Sql\Mysql
 */
abstract class ConnectionCoroutine extends Connection
{

    /**
     * The supported options by \Swoole\Coroutine\Mysql()
     */
    public const SUPPORTED_OPTIONS = [
        'host',
        'user',
        'password',
        'database',
        'port',
        'timeout',
        'charset',
        'strict_type',
        'fetch_mode',
    ];

    public function __construct(array $options)
    {
        $this->connect($options);
        parent::__construct();

    }

    private function connect(array $options) : void
    {
        $this->NativeConnection = new \Swoole\Coroutine\Mysql();

        //$ret = $this->NativeConnection->connect(static::CONFIG_RUNTIME);
        //$config = array_merge( ['strict_mode' => TRUE, 'fetch_mode' => TRUE ], static::CONFIG_RUNTIME);
        //let the fetch_mode to be configurable
        $config = ['strict_type' => TRUE];//but strict_type must be always TRUE
        $config = array_merge($options, $config);
        static::validate_options($options);
        $this->options = $config;
        //$config = array_filter($config, fn(string $key) : bool => in_array($key, self::SUPPORTED_OPTIONS), ARRAY_FILTER_USE_KEY );
//        foreach ($config as $key=>$value) {
//            if (!in_array($key, self::SUPPORTED_OPTIONS)) {
//                throw new InvalidArgumentException(sprintf(t::_('An invalid connection option %s is provided to %s. The valid options are %s.'), $key, \Swoole\Coroutine\Mysql::class, implode(', ', self::SUPPORTED_OPTIONS) ));
//            }
//        }
        if (!array_key_exists('fetch_mode', $config)) {
            $config['fetch_mode'] = FALSE;//better to be false by default as having it to true allows for a connection to be returned to the pool without all the data to have been fetched.
        }

        $ret = $this->NativeConnection->connect($config);

        if (!$ret) {
            throw new ConnectionException(sprintf(t::_('Connection of class %s to %s:%s could not be established due to error: [%s] %s .'), get_class($this), self::CONFIG_RUNTIME['host'], self::CONFIG_RUNTIME['port'], $this->NativeConnection->connect_errno, $this->NativeConnection->connect_error));
        }
    }

//    public function get_options() : array
//    {
//        return $this->NativeConnection->serverInfo ?? [];
//    }

    public function get_fetch_mode() : bool
    {
        //return $this->NativeConnection->serverInfo['fetch_mode'];
        return $this->get_options()['fetch_mode'];
    }

    public function prepare(string $query) : StatementInterface
    {
        if (!$this->get_coroutine_id() && $this->get_connection_id() !== NULL) { //if there is no connection ID allow to prepare as this is the SELECT CONNECTION_ID() query (or other initialization query run at connect)
            throw new RunTimeException(sprintf(t::_('Attempting to prepare a statement for query "%s" on a connection that is not assigned to any coroutine.'), $query));
        }
        $Statement = $this->prepare_statement($query, StatementCoroutine::class, $this);
        return $Statement;
    }

    //TODO implement timeout parameter - on timeout this will interrupt the connection so the connection will need to be reestablished

    /**
     * Executes the provided queries in parallel
     * @param array $queries_data
     * @return array
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
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
