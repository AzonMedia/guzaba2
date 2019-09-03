<?php


namespace Guzaba2\Di;

use Azonmedia\Lock\Backends\SwooleTableBackend;
use Azonmedia\Lock\CoroutineLockManager;
use Azonmedia\Lock\Backends\NullBackend;
use Guzaba2\Base\Interfaces\ConfigInterface;
use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;
use Guzaba2\Base\Traits\SupportsConfig;
use Guzaba2\Base\Traits\SupportsObjectInternalId;
use Guzaba2\Database\ConnectionFactory;
use Guzaba2\Database\ConnectionProviders\Pool;

use Guzaba2\Kernel\Kernel;
use Guzaba2\Orm\Store\Memory;
use Guzaba2\Orm\Store\Nosql\Redis;
use Guzaba2\Orm\Store\Sql\Mysql;
use Guzaba2\Orm\Store\NullStore;

use Guzaba2\Orm\MetaStore\SwooleTable;
use Guzaba2\Orm\MetaStore\NullMetaStore;

//use org\guzaba\framework\database\classes\QueryCache;

class Container extends \Azonmedia\Di\Container implements ConfigInterface, ObjectInternalIdInterface
{
    use SupportsObjectInternalId;

    use SupportsConfig;

    protected const CONFIG_DEFAULTS = [
        'ConnectionFactory'             => [
            'class'                         => ConnectionFactory::class,
            'args'                          => [
                'ConnectionProvider'            => 'ConnectionProviderPool',
            ],
        ],
        'ConnectionProviderPool'       => [
            'class'                         => Pool::class,
            'args'                          => [],
        ],
//        'SomeExample'                   => [
//            'class'                         => SomeClass::class,
//            'args'                          => [
//                'arg1'                      => 20,
//                'arg2'                      => 'something'
//            ],
//        ]
        'OrmStore'                      => [
            'class'                         => Memory::class,//the Memory store is the first to be looked into
            'args'                          => [
                'FallbackStore'                 => 'RedisOrmStore',
            ],
        ],
        'RedisOrmStore'                 => [
            'class'                         => Redis::class,
            'args'                          => [
                'FallbackStore'                 => 'MysqlOrmStore',
            ],
        ],
        'MysqlOrmStore'                 => [
            'class'                         => Mysql::class,
            'args'                          => [
                'FallbackStore'                 => 'NullOrmStore',
                'connection_class'              => \Azonmedia\Glog\Application\MysqlConnection::class,
            ]
        ],
        'NullOrmStore'                  => [
            'class'                         => NullStore::class,
            'args'                          => [
                'FallbackStore'                 => NULL,
            ],
        ],
        'OrmMetaStore'                  => [
            'class'                         => SwooleTable::class,
            'args'                          => [
                'FallbackMetaStore'             => 'NullOrmMetaStore',
            ],
            'initialize_immediately'        => TRUE,
        ],
        'NullOrmMetaStore'              => [
            'class'                         => NullMetaStore::class,
            'args'                          => [
                'FallbackStore'                 => NULL,
            ],
        ],
        'QueryCache' => [
            'class'                         => QueryCache::class,
            'args'                          => [
                // TODO add required params
            ],
        ],
        'LockManager'                   => [
            'class'                         => CoroutineLockManager::class,
            'args'                          => [
                'Backend'                       => 'LockManagerBackend',
                'Logger'                        => [Kernel::class, 'get_logger'],
            ],
            'initialize_immediately'        => TRUE,
        ],
        'LockManagerBackend'            => [
            'class'                         => SwooleTableBackend::class,
            'args'                          => [
                'Logger'                        => [Kernel::class, 'get_logger'],
            ],
        ],
    ];

    protected const CONFIG_RUNTIME = [];

    public function __construct(array $config = [])
    {
        if (!$config) {
            $config = self::CONFIG_RUNTIME;
        }
        parent::__construct($config);
    }
}
