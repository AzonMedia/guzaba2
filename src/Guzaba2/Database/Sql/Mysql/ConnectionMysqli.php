<?php

namespace Guzaba2\Database\Sql\Mysql;

use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Database\Exceptions\ConnectionException;
use Guzaba2\Database\Exceptions\QueryException;
use Guzaba2\Database\Interfaces\StatementInterface;
use Guzaba2\Database\Sql\TransactionalConnection;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Kernel\Kernel;

/**
 * Class Connection
 * A class containing a mysqli connection
 * @package Guzaba2\Database\Sql\Mysql
 */
abstract class ConnectionMysqli extends Connection
{

    public function __construct()
    {
        parent::__construct();
        $this->connect();
    }

    public function prepare(string $query) : StatementInterface
    {
        $Statement = $this->prepare_statement($query, StatementMysqli::class);
        return $Statement;
    }

    public function connect() : void
    {
        Kernel::dump(static::CONFIG_RUNTIME);
        $ret = $this->NativeConnection = new \mysqli(
            static::CONFIG_RUNTIME['host'],
            static::CONFIG_RUNTIME['user'],
            static::CONFIG_RUNTIME['password'],
            static::CONFIG_RUNTIME['database'],
            static::CONFIG_RUNTIME['port'],
            static::CONFIG_RUNTIME['socket']
        );

        if (!$ret) {
            throw new ConnectionException(sprintf(t::_('Connection of class %s to %s:%s could not be established due to error: [%s] %s .'), get_class($this), self::CONFIG_RUNTIME['host'], self::CONFIG_RUNTIME['port'], $this->NativeConnection->connect_errno, $this->NativeConnection->connect_error));
        }
    }
}
