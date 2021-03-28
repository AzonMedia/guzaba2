<?php

declare(strict_types=1);

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

    public const SUPPORTED_OPTIONS = [
        'host',
        'user',
        'password',
        'database',
        'port',
        'socket',
    ];

    /**
     * ConnectionMysqli constructor.
     * @param array $options
     * @param callable|null $after_connect_callback
     * @throws ConnectionException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function __construct(array $options, ?callable $after_connect_callback = null)
    {
        $this->connect($options);
        parent::__construct($after_connect_callback);
    }

    /**
     * @param string $query
     * @return StatementInterface
     * @throws QueryException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     */
    public function prepare(string $query): StatementInterface
    {
        $Statement = $this->prepare_statement($query, StatementMysqli::class, $this);
        return $Statement;
    }

    /**
     * @param array $options
     * @throws ConnectionException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    private function connect(array $options): void
    {
        static::validate_options($options);

        $this->options = $options;
        /*
        $ret = $this->NativeConnection = new \mysqli(
            static::CONFIG_RUNTIME['host'],
            static::CONFIG_RUNTIME['user'],
            static::CONFIG_RUNTIME['password'],
            static::CONFIG_RUNTIME['database'],
            (int) static::CONFIG_RUNTIME['port'],
            static::CONFIG_RUNTIME['socket']
        );
        */
        $ret = $this->NativeConnection = new \mysqli(
            $options['host'],
            $options['user'],
            $options['password'],
            $options['database'],
            (int) $options['port'],
            $options['socket'],
        );

        if (!$ret) {
            throw new ConnectionException(sprintf(t::_('Connection of class %s to %s:%s could not be established due to error: [%s] %s .'), get_class($this), self::CONFIG_RUNTIME['host'], self::CONFIG_RUNTIME['port'], $this->NativeConnection->connect_errno, $this->NativeConnection->connect_error));
        }
        $group_concat_max_len = static::CONFIG_RUNTIME['group_concat_max_len'];
        $this->NativeConnection->query("SET @@group_concat_max_len = {$group_concat_max_len};");
    }
}
