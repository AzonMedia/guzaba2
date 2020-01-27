<?php
declare(strict_types=1);

namespace Guzaba2\Di;

//class Container extends \Azonmedia\Di\Container implements ConfigInterface, ObjectInternalIdInterface
use Guzaba2\Base\Interfaces\ConfigInterface;
use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;
use Guzaba2\Base\Traits\SupportsConfig;
use Guzaba2\Base\Traits\SupportsObjectInternalId;

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

//    public static function get_default_current_user_id() /* scalar */
//    {
//        //return self::CONFIG_RUNTIME['dependencies']['DefaultCurrentUser']['args']['index'] ?? 0;
//        return self::CONFIG_RUNTIME['dependencies']['DefaultCurrentUser']['args']['index'] ?? ActiveRecordInterface::INDEX_NEW;
//    }

}
