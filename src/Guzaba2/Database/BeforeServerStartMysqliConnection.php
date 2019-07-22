<?php
declare(strict_types=1);

namespace Guzaba2\Database;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Database\Interfaces\StatementInterface;
use Guzaba2\Database\Sql\Mysql\StatementMysqli;

class BeforeServerStartMysqliConnection extends Base implements ConnectionInterface
{
    protected const CONFIG_DEFAULTS = [
        'host'      => '192.168.0.92',
        'port'      => 3306,
        'username'  => 'vesko',
        'password'  => 'impas560',
        'database'  => 'guzaba2',
        'socket' => '',
    ];

    protected const CONFIG_RUNTIME = [];

    protected $Mysqli;

    public function __construct()
    {
        parent::__construct();

        $this->Mysqli = new \mysqli(
            static::CONFIG_RUNTIME['host'],
            static::CONFIG_RUNTIME['username'],
            static::CONFIG_RUNTIME['password'],
            static::CONFIG_RUNTIME['database'],
            static::CONFIG_RUNTIME['port'],
            static::CONFIG_RUNTIME['socket']
        );
    }

    public function prepare(string $query) : StatementInterface
    {
        $NativeStatement = $this->Mysqli->prepare($query);
        $Statement = new StatementMysqli($NativeStatement);
        return $Statement;
    }

    public function free() : void
    {
        if ($this->is_created_from_factory()) {
            // self::ConnectionFactory()->free_connection($this);
        }
    }

    public function close() : void
    {
        // $this->MysqlCo->close();
    }
}