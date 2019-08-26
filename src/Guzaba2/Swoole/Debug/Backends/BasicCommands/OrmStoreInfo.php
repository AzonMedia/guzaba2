<?php

namespace Guzaba2\Swoole\Debug\Backends\BasicCommands;

use Azonmedia\Debug\Interfaces\CommandInterface;
use Guzaba2\Kernel\Kernel;

class OrmStoreInfo implements CommandInterface
{

    //this class does not extend base so no services here
//    protected const CONFIG_DEFAULTS = [
//        'services'      => [
//            'OrmStore',
//        ]
//    ];
//
//    protected const CONFIG_RUNTIME = [];

    public function handle(string $command) : ?string
    {
        $ret = NULL;
        if ($this->can_handle($command)) {
            $ret = 'ORM Store details:'.PHP_EOL;
            $structure = [];
            //$OrmStore = self::OrmStore();
            $OrmStore = Kernel::get_service('OrmStore');
            do {
                $structure[] = get_class($OrmStore);
                $FallbackStore = $OrmStore->get_fallback_store();
                $OrmStore = $FallbackStore;
            } while ($FallbackStore);
            $ret .= implode(' --> ', $structure).PHP_EOL;
        }
        return $ret;
    }

    public function can_handle(string $command) : bool
    {
        return $command === 'show ormstore';
    }

    public static function handles_commands() : string
    {
        return 'show ormstore';
    }

    public static function help() : string
    {
        return 'show ormstore - shows details about ORM store(s)';
    }
}
