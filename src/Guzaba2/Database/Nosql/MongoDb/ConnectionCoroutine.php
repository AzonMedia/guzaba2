<?php

namespace Guzaba2\Database\Nosql\MongoDb;

use \MongoDB\Driver\Manager;
use \MongoDB\Driver\Query;
use \MongoDB\Driver\BulkWrite;
use \MongoDB\Driver\WriteConcern;
use \MongoDB\Driver\Command;

use Guzaba2\Database\Connection;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Database\Exceptions\QueryException;
use Guzaba2\Database\Exceptions\ConnectionException;
// use Guzaba2\Coroutine\Coroutine;
// use Guzaba2\Database\ConnectionFactory;
// use Guzaba2\Database\Interfaces\ConnectionInterface;
// use Guzaba2\Database\Interfaces\StatementInterface;
// use Guzaba2\Kernel\Exceptions\ErrorException;

/**
 * Class ConnectionCoroutine
 * Because Swoole\Corotuine\Mysql\Statement does not support binding parameters by name, but only by position this class addresses this.
 * @package Guzaba2\Database\Sql\Mysql
 */
abstract class ConnectionCoroutine extends Connection
{
    protected const CONFIG_DEFAULTS = [
        'host'      => '192.168.0.95',
        'port'      => 27017,
        'database'  => 'swoole',
        'username'  => 'swoole_user',
        'password'  => 'swoole_password',
        'tprefix'   => 'guzaba_',
        'AI_table'  => 'guzaba_autoincrement_counters',
    ];

    protected const CONFIG_RUNTIME = [];

    protected $Manager;

    public function __construct()
    {
        parent::__construct();

        $this->initialize();
    }

    public function initialize()
    {
        if (self::CONFIG_RUNTIME['username'] != '' && self::CONFIG_RUNTIME['password'] != '') {
            $options = sprintf("mongodb://%s:%s@%s:%s/%s", self::CONFIG_RUNTIME['username'], self::CONFIG_RUNTIME['password'], self::CONFIG_RUNTIME['host'], self::CONFIG_RUNTIME['port'], self::CONFIG_RUNTIME['database']);
        } else {
            $options = sprintf("mongodb://%s:%s/%s", self::CONFIG_RUNTIME['host'], self::CONFIG_RUNTIME['port'], self::CONFIG_RUNTIME['database']);            
        }

        try {
            $this->Manager = new Manager($options);
        } catch (\Exception $e) {
            throw new ConnectionException(sprintf(t::_('Connection of class %s to %s:%s could not be established due to error: %s .'), get_class($this), static::CONFIG_RUNTIME['host'], static::CONFIG_RUNTIME['port'], $e->getMessage()));
        }
    }

    // public function __call(string $method, array $args)
    // {
    //     return call_user_func_array(array($this->Manager, $method), $args);
    // }

    /**
     * select from MongoDB
     * @param string $collection
     * @param array $filter
     * @param array $options
     */
    public function query($collection, array $filter = array(), array $options = array()) : array
    {
        if (!$this->get_coroutine_id()) {
            throw new RunTimeException(sprintf(t::_('Attempting to run query to collection "%s" with filter: "%s" on a connection that is not assigned to any coroutine.'), $collection, print_r($filter, TRUE)));
        }

        $data = [];

        $query = new Query($filter, $options);

        $cursor = $this->Manager->executeQuery(self::CONFIG_RUNTIME['database'] . '.' . $collection, $query);
        $cursor->setTypeMap(['root' => 'array', 'document' => 'array']);

        $data = array();
        foreach ($cursor as $document) {
            $document['_id'] = $document['_id']->__toString();
            $data[] = $document;
        }

        return $data;
    }

    /**
     * insert row in MongoDB
     * @param string $collection
     * @param array $data
     */
    public function insert(string $collection, array $data)
    {
        if (!$this->get_coroutine_id()) {
            throw new RunTimeException(sprintf(t::_('Attempting to run query to collection "%s" with filter: "%s" on a connection that is not assigned to any coroutine.'), $collection, print_r($filter, TRUE)));
        }

        $bulk = new BulkWrite();

        try {
            $result = $bulk->insert($data);

            $writeConcern = new WriteConcern(WriteConcern::MAJORITY, 100);
            $r = $this->Manager->executeBulkWrite(self::CONFIG_RUNTIME['database'] . '.' . $collection, $bulk, $writeConcern);

        } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
            $error_code = $e->getWriteResult()->getWriteErrors()[0]->getCode();

            throw new QueryException(null, '', $error_code, sprintf(t::_('Preparing query to collection "%s" with filter: "%s" failed with error: [%s] %s .'), $collection, print_r($filter, TRUE), $error_code, $e->getMessage()), '', []);
        }

        return $result;
    }

    /**
     * update row in MongoDB
     * @param array $filter
     * @param string $collection
     * @param array $data
     * @param bool $multi
     * @param bool $upsert
     *
     * if upsert = false => update
     * if upsert = true => insert ot update
     *
     * @return void
     */
    public function update(array $filter, string $collection, array $data, bool $upsert = false, bool $multi = false) : void
    {
        $bulk = new BulkWrite();

        try {
            $bulk->update(
                $filter,
                ['$set' => $data],
                ['multi' => $multi, 'upsert' => $upsert]
            );

            $writeConcern = new WriteConcern(WriteConcern::MAJORITY, 100);
            $r = $this->Manager->executeBulkWrite(self::CONFIG_RUNTIME['database'] . '.' . $collection, $bulk, $writeConcern);

        } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
            $error_code = $e->getWriteResult()->getWriteErrors()[0]->getCode();

            throw new QueryException(null, '', $error_code, sprintf(t::_('Preparing query to collection "%s" with filter: "%s" failed with error: [%s] %s .'), $collection, print_r($filter, TRUE), $error_code, $e->getMessage()), '', []);
        }
    }

    /**
     * delete row from MongoDB
     * @param array $filter
     * @param string $collection
     * @param bool $limit (0 = no limit)
     *
     * @return void
     */
    public function delete(array $filter, string $collection, bool $limit = true) : void
    {
        $bulk = new BulkWrite();

        $bulk->delete(
            $filter,
            ['limit' => $limit]
        );

        $writeConcern = new WriteConcern(WriteConcern::MAJORITY, 100);
        $r = $this->Manager->executeBulkWrite(self::CONFIG_RUNTIME['database'] . '.' . $collection, $bulk, $writeConcern);
    }

    public function ping() : bool
    {
        $ret = FALSE;
        $command = new Command(['ping' => 1]);

        try {
            $cursor = $this->Manager->executeCommand(self::CONFIG_RUNTIME['database'], $command);
            $ret = TRUE;
        } catch (MongoDB\Driver\Exception $e) {
            throw new RunTimeException($e->getMessage());
        }

        return $ret;
    }

    public function close() : void
    {
        // cannot close MongoDB Connection
    }

    public function get_autoincrement_value($collection_name) : int
    {
        $command = new Command([
            'findandmodify' => self::CONFIG_RUNTIME['AI_table'],
            'query' => ['_id' => $collection_name],
            'update' => ['$inc' => ['AI' => 1]],
            'new' => TRUE,
            'upsert' => TRUE,
            'fields' => ['AI' => 1]
        ]);

        $cursor = $this->Manager->executeCommand(self::CONFIG_RUNTIME['database'], $command);
        $cursor->setTypeMap(['root' => 'array', 'document' => 'array']);
        $result = $cursor->toArray()[0];

        $next_id = $result['value']['AI'];

        return $next_id;
    }

    public function set_autoincrement_value($collection_name, $object_id) : void
    {
        $this->update(
            ['_id' => $collection_name],
            self::CONFIG_RUNTIME['AI_table'],
            ['AI' => $object_id],
            TRUE
        );
    }

    /**
     * Returns the ID of the last insert.
     * Must be executed immediately after the insert query
     *
     * @return int
     */
    // public function get_last_insert_id() : int
    // {
    //     return $this->MysqlCo->insert_id;
    // }

    /**
     * @return int
     */
    // public function get_affected_rows() : int
    // {
    //     return $this->MysqlCo->affected_rows;
    // }

    // public function get_last_error() : string
    // {
    //     return $this->MysqlCo->error;
    // }

    // public function get_last_error_number() : int
    // {
    //     return $this->MysqlCo->errno;
    // }
}
