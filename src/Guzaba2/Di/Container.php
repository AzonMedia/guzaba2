<?php


namespace Guzaba2\Di;


use Guzaba2\Base\Interfaces\ConfigInterface;
use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;
use Guzaba2\Base\Traits\SupportsConfig;
use Guzaba2\Base\Traits\SupportsObjectInternalId;
use Guzaba2\Database\ConnectionFactory;
use Guzaba2\Database\ConnectionProviders\Pool;

class Container extends \Azonmedia\Di\Container
    implements ConfigInterface, ObjectInternalIdInterface
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
    ];

    protected static $CONFIG_RUNTIME = [];

    public function __construct(array $options = [])
    {
        self::update_runtime_configuration($options);
        parent::__construct(self::$CONFIG_RUNTIME);
    }
}