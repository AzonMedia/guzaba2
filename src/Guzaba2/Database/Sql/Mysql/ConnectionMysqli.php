<?php

namespace Guzaba2\Database\Sql\Mysql;

use Guzaba2\Database\Interfaces\StatementInterface;

/**
 * Class Connection
 * A class containing a mysqli connection
 * @package Guzaba2\Database\Sql\Mysql
 */
abstract class ConnectionMysqli extends \Guzaba2\Database\Connection
{
    protected const CONFIG_DEFAULTS = [
        'host'      => 'localhost',
        'port'      => 3306,
        'user'      => 'root',
        'password'  => '',
        'database'  => '',
    ];

    protected const CONFIG_RUNTIME = [];

    protected $Mysqli;

    public function __construct(array $options)
    {
        parent::__construct();
        self::update_runtime_configuration($options);
        $this->Mysqli = new \mysqli(
            self::CONFIG_RUNTIME['host'],
            self::CONFIG_RUNTIME['username'],
            self::CONFIG_RUNTIME['password'],
            self::CONFIG_RUNTIME['database'],
            self::CONFIG_RUNTIME['port'],
            self::CONFIG_RUNTIME['socket']
        );
    }

    public function prepare(string $query) : StatementInterface
    {
        $NativeStatement = $this->Mysqli->prepare($query);
        $Statement = new StatementMysqli($NativeStatement);
        return $Statement;
    }
}
