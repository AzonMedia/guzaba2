<?php
declare(strict_types=1);

namespace Guzaba2\Swoole\Debug\Backends\BasicCommands;

use Psr\Log\LogLevel;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Translator\Translator as t;
use Azonmedia\Debug\Interfaces\CommandInterface;

class OrmStoreInfo extends \Guzaba2\Swoole\Debug\Backends\BasicCommand
{

    //this class does not extend base so no services here
//    protected const CONFIG_DEFAULTS = [
//        'services'      => [
//            'OrmStore',
//        ]
//    ];
//
//    protected const CONFIG_RUNTIME = [];

    protected static $commands = [
        'show ormstore' => [ 'method' => 'debug_get_data', 'help_str' => 'Dumps OrmStore' ],
        'get ormstore hits' => [ 'method' => 'get_hits', 'help_str' => 'Shows ormstore hits' ],
        'get ormstore hits percentage' => [ 'method' => 'get_hits_percentage', 'help_str' => 'Shows hits as part of hits + misses' ],
        'get ormstore misses' => [ 'method' => 'get_misses', 'help_str' => 'Shows ormstore misses' ],
        'reset ormstore stats' => [ 'method' => 'reset_stats', 'help_str' => 'Resets ormstore stats - resets hits, resets misses' ],
        'reset ormstore' => [ 'method' => 'reset_all', 'help_str' => 'Resets ormstore - clears cache, resets hits, resets misses' ],
/*
        'enable ormstore' => [ 'ToDo' ],
        'disable ormstore' => [ 'ToDo' ]
*/
    ];

    public function handle(string $command, string $current_prompt, ?string &$change_prompt_to = NULL) : ?string
    {
        $ret = NULL;
        $class_name = self::get_class_name();

        $tok = strtok($command, ' ');
        if (0 === strcasecmp('help', $tok) || 0 === strcasecmp($class_name, $tok)) {
            $help_command = preg_replace("/^(\w+\s)/", "", $command);
            if ($this->can_handle($help_command) || 0 === strcasecmp($class_name, $help_command)) {
                $ret = self::help($help_command);
                return $ret;
            } else {
                return NULL;
            }
        }

        if ($this->can_handle($command)) {
            $ret = 'ORM Store details:'.PHP_EOL;
            $structure = [];
            //$OrmStore = self::OrmStore();
            $OrmStore = Kernel::get_service('OrmStore');
            $PrimaryOrmStore = $OrmStore;
            do {
                Kernel::log(get_class($OrmStore), LogLevel::INFO);
                $structure[] = get_class($OrmStore);
                $FallbackStore = $OrmStore->get_fallback_store();
                $OrmStore = $FallbackStore;
            } while ($FallbackStore);

            $command_ret = call_user_func_array([ $PrimaryOrmStore, self::$commands[$command]['method'] ], []);
            $ret .= implode(' --> ', $structure) . PHP_EOL;
            switch ($command) {
                case 'show ormstore' :
                    $ret .= print_r($command_ret, TRUE);
                    break;
                case 'get ormstore hits' :
                    $ret .= sprintf(t::_('%s (%s): %s'), $class_name, $command, $command_ret);
                    break;
                case 'get ormstore hits percentage' :
                    $ret .= sprintf(t::_('%s (%s): %.2f%%'), $class_name, $command, (double) $command_ret);
                    break;
                case 'get ormstore misses' :
                    $ret .= sprintf(t::_('%s (%s): %s'), $class_name, $command, $command_ret);
                    break;
                case  'reset ormstore stats' :
                    $ret .= sprintf(t::_('%s (%s): stats are reset.'), $class_name, $command);
                    break;
                case  'reset ormstore' :
                    $ret .= sprintf(t::_('%s (%s): ormstore is reset.'), $class_name, $command);
                    break;
                default :
                    // error
                    break;
            }
        }

        return $ret;
    }
}
