<?php
declare(strict_types=1);


namespace Guzaba2\Authorization;


use Guzaba2\Base\Base;
use Guzaba2\Coroutine\Cache;
use Guzaba2\Event\Event;
use Guzaba2\Kernel\Interfaces\ClassInitializationInterface;
use Guzaba2\Kernel\Kernel;

class ClassInitialization extends Base implements ClassInitializationInterface
{
    protected const CONFIG_DEFAULTS = [
        'services' => [
            'Events',
            'ContextCache',
        ],
    ];

    protected const CONFIG_RUNTIME = [];

    public static function run_all_initializations() : array
    {
        self::initialize_roles();
        return ['initialize_roles'];
    }

    public static function initialize_roles() : void
    {
        $Events = self::get_service('Events');
        $ContextCache = self::get_service('ContextCache');
        //if the RolesHierarchy is modified remove all cached roles inheritance for the current request
        $Callback = static function(Event $Event) use ($ContextCache): void
        {
            $ContextCache->delete('all_inherited_roles', '');
        };
        $Events->add_class_callback(RolesHierarchy::class, '_after_write', $Callback);
        $Events->add_class_callback(RolesHierarchy::class, '_after_delete', $Callback);
    }

}