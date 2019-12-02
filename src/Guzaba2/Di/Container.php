<?php


namespace Guzaba2\Di;

use Azonmedia\Di\Exceptions\ContainerException;
use Azonmedia\Di\Exceptions\NotFoundException;
use Azonmedia\Di\Interfaces\CoroutineDependencyInterface;
use Azonmedia\Glog\Application\RedisConnection;
use Azonmedia\Lock\Backends\SwooleTableBackend;
use Azonmedia\Lock\CoroutineLockManager;
use Azonmedia\Lock\Backends\NullBackend;
use Azonmedia\Di\SampleClass;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Base\Interfaces\ConfigInterface;
use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;
use Guzaba2\Base\Traits\SupportsConfig;
use Guzaba2\Base\Traits\SupportsObjectInternalId;
use Guzaba2\Database\ConnectionFactory;
use Guzaba2\Database\ConnectionProviders\Basic;
use Guzaba2\Database\ConnectionProviders\Pool;

use Guzaba2\Kernel\Kernel;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Store\Memory;
use Guzaba2\Orm\Store\Nosql\Redis;
use Guzaba2\Orm\Store\Sql\Mysql;
use Guzaba2\Orm\Store\NullStore;

use Guzaba2\Orm\MetaStore\SwooleTable;
use Guzaba2\Orm\MetaStore\NullMetaStore;

//use org\guzaba\framework\database\classes\QueryCache;
use Guzaba2\Transaction\TransactionManager;
use org\guzaba\framework\database\classes\QueryCache;

//class Container extends \Azonmedia\Di\Container implements ConfigInterface, ObjectInternalIdInterface
class Container extends \Azonmedia\Di\CoroutineContainer implements ConfigInterface, ObjectInternalIdInterface
{
    use SupportsObjectInternalId;

    use SupportsConfig;

    /**
     * Dependencies are stored in the registry
     * @see app/registry/dev.php
     */
    protected const CONFIG_DEFAULTS = [
        'dependencies' => [

        ]
    ];

    protected const CONFIG_RUNTIME = [];

    public function __construct(array $config = [])
    {
        if (!$config) {
            $config = self::CONFIG_RUNTIME;
        }
        parent::__construct($config['dependencies']);
    }

    public static function get_default_current_user_id() /* scalar */
    {
        //return self::CONFIG_RUNTIME['dependencies']['DefaultCurrentUser']['args']['index'] ?? 0;
        return self::CONFIG_RUNTIME['dependencies']['DefaultCurrentUser']['args']['index'] ?? ActiveRecordInterface::INDEX_NEW;
    }
}
