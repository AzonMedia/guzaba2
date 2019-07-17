<?php


namespace Guzaba2\Database\Sql\Mysql;


use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
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
        'host'      => '192.168.0.51',
        'port'      => 3306,
        'user'      => 'vesko',
        'password'  => 'impas560',
        'database'  => 'guzaba2',
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
        //self::update_runtime_configuration($options);

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

        try {
            $NativeStatement = $this->MysqlCo->prepare($query);
        } catch (ErrorException $exception) {

            if ($this->MysqlCo->errno === 2006 || $this->MysqlCo->errno === 2013) {
                // If connection is lost, try to reconnect and prepare the query again
                sprintf(t::_("MySQL Connection is Lost with Error No %s. Trying to reconnect ..."), $this->MysqlCo->errno);
                $this->initialize();
                return $this->prepare($query);
            } else {
                throw new QueryException(sprintf(t::_('%s. Connection ID %s. '), $exception->getMessage(), $this->get_object_internal_id() ));
            }
        }
        if (!$NativeStatement) {
            throw new QueryException(sprintf(t::_('Preparing query "%s" failed with error: [%s] %s .'), $query, $this->MysqlCo->errno, $this->MysqlCo->error ));
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