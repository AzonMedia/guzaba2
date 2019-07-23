<?php


namespace Guzaba2\Database\Sql\Mysql;


use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Database\Connection;
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
    protected const CONFIG_DEFAULTS = [
        'host'      => 'localhost',
        'port'      => 3306,
        'user'      => 'root',
        'password'  => '',
        'database'  => '',
    ];

    protected const CONFIG_RUNTIME = [];

    protected $MysqlCo;

    /**
     * Contains the original query using named paramenters
     * @var string
     */
    protected $original_query = '';



    //public function __construct(array $options)
    public function __construct()
    {
        parent::__construct();

        $this->initialize();
    }

    private function initialize(){

        $this->MysqlCo = new \Swoole\Coroutine\Mysql();

        $ret = $this->MysqlCo->connect(static::CONFIG_RUNTIME);
        if (!$ret) {
            throw new ConnectionException(sprintf(t::_('Connection of class %s to %s:%s could not be established due to error: [%s] %s .'), get_class($this), self::CONFIG_RUNTIME['host'], self::CONFIG_RUNTIME['port'], $this->MysqlCo->connect_errno, $this->MysqlCo->connect_error ));
        }
    }

    public function prepare(string $query) : StatementInterface
    {
        if (!$this->get_coroutine_id()) {
            throw new RunTimeException(sprintf(t::_('Attempting to prepare a statement for query "%s" on a connection that is not assigned to any coroutine.'), $query));
        }

        $this->original_query = $query;

        $expected_parameters = [];
        $query = self::convert_query_for_binding($query, $expected_parameters);

        /*
        try {
            // With Swoole 4.3.6 and older if the connection is lost, this will throw an exception
            $NativeStatement = $this->MysqlCo->prepare($query);
        } catch (ErrorException $exception) {

            if ($this->MysqlCo->errno === 2006 || $this->MysqlCo->errno === 2013) {
                // If connection is lost, try to reconnect and prepare the query again
                print(sprintf(t::_("MySQL Connection is Lost with Error No %s. Trying to reconnect ...\n"), $this->MysqlCo->errno));
                $this->initialize();
                return $this->prepare($query);
            } else {
                throw new QueryException(sprintf(t::_('%s. Connection ID %s. '), $exception->getMessage(), $this->get_object_internal_id() ));
            }
        }
        */

        $NativeStatement = $this->MysqlCo->prepare($query);
        if (!$NativeStatement) {
            // With Swoole 4.4.0 and next if the connection is lost, prepare will NOT throw an exception, but errno will be set
            if ($this->MysqlCo->errno === 2006 || $this->MysqlCo->errno === 2013) {
                // If connection is lost, try to reconnect and prepare the query again
                print(sprintf(t::_("MySQL Connection is Lost with Error No %s. Trying to reconnect ...\n"), $this->MysqlCo->errno));
                $this->initialize();
                return $this->prepare($query);
            } else {
                throw new QueryException(sprintf(t::_('Preparing query "%s" failed with error: [%s] %s .'), $query, $this->MysqlCo->errno, $this->MysqlCo->error ));
            }
        }
        $Statement = new StatementCoroutine($NativeStatement, $query, $expected_parameters);
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

    /**
     * Will start number of coroutines corresponding to the number of queries - 1 (the current coroutine & connection will be used too)
     * @param array $queries Twodimensional indexed array containing 'query' and 'params' keys in the second dimension
     * @return array
     */
    public function execute_multiple_queries(array $queries_data) : array
    {

        $queries_count = count($queries_data);

        $channel = new \Swoole\Coroutine\Channel( count( $queries_data) );

        $current_coroutine_query_data = array_pop($queries_data);

        $Function = function(string $query, array $params = []) use ($channel) {
            print 'substart'.PHP_EOL;
            $Connection = self::ConnectionFactory()->get_connection(get_class($this), $CR);
            $Statement = $Connection->prepare($query);
            $Statement->execute($params);
//            $data = $Statement->fetchAll();
//            print 'subok'.PHP_EOL;
//            $channel->push($data);
        };
        foreach ($queries_data as $query_data) {
            Coroutine::create($Function, $query_data['query'], $query_data['params']);
        }
        //the coroutines are started
        //execute in the main coroutine the poped query
        $Statement = $this->prepare($current_coroutine_query_data['query']);
        $Statement->execute($current_coroutine_query_data['params']);
        $data = $Statement->fetchAll();
        print 'ok'.PHP_EOL;
        $channel->push($data);

        $ret = [];
        for( $aa = 0; $aa<$channel->length(); $aa++) {
            print 'pop'.PHP_EOL;
            $ret[] = $channel->pop();
        }


        return $ret;
    }

    private static function convert_query_for_binding($named_params_query, array &$expected_parameters = []) : string
    {

        preg_match_all('/:([a-zA-Z0-9_]*)/', $named_params_query, $matches);
        if (isset($matches[1]) && count($matches[1])) {
            $expected_parameters = $matches[1];
        }
        $query = preg_replace('/:([a-zA-Z0-9_]*)/', '?', $named_params_query);
        return $query;
    }
}