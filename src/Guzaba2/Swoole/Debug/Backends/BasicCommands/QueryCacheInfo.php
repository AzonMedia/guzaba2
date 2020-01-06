<?php
declare(strict_types=1);

namespace Guzaba2\Swoole\Debug\Backends\BasicCommands;

use Psr\Log\LogLevel;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Translator\Translator as t;
use Azonmedia\Debug\Interfaces\CommandInterface;

//class QueryCacheInfo implements CommandInterface
class QueryCacheInfo extends \Guzaba2\Swoole\Debug\Backends\BasicCommand
{
    /*
     * NOT IMPLEMENTED
     */
    protected static $commands = [
        'show query cache' => [ 'method' => 'debug_get_data', 'help_str' => 'Dumps Cache' ],
        'get qcache hits' => [ 'method' => 'get_hits', 'help_str' => 'Shows cache hits' ],
        'get qcache hits percentage' => [ 'method' => 'get_hits_percentage', 'help_str' => 'Shows hits as part of hits + misses' ],
        'get qcache misses' => [ 'method' => 'get_misses', 'help_str' => 'Shows cache misses' ],
        'reset qcache stats' => [ 'method' => 'reset_stats', 'help_str' => 'Resets cache stats - resets hits, resets misses' ],
        'reset query cache' => [ 'method' => 'reset_all', 'help_str' => 'Resets cache - clears cache, resets hits, resets misses' ],
/*
        'enable cache' => [ 'ToDo' ],
        'disable cache' => [ 'ToDo' ]
*/
    ];

    public function handle(string $command, string $current_prompt, ?string &$change_prompt_to = NULL) : ?string
    {
        $ret = NULL;
        $class_name = self::get_class_name();

        $tok = strtok($command, ' ');
        if (0 === strcasecmp('help', $tok)) {
            $help_command = preg_replace("/^(\w+\s)/", "", $command);
            if ($this->can_handle($help_command) || 0 === strcasecmp($class_name, $help_command)) {
                $ret = self::help($help_command);
                return $ret;
            } else {
                return NULL;
            }
        }

        if ($this->can_handle($command)) {
            $ret = 'Cache Store details:'.PHP_EOL;
            $structure = [];
            //$OrmStore = self::OrmStore();
            $QueryCacheStore = Kernel::get_service('Cache');
            $PrimaryQueryCacheStore = $QueryCacheStore;
            do {
                Kernel::log(get_class($QueryCacheStore), LogLevel::INFO);
                $structure[] = get_class($QueryCacheStore);
                $FallbackStore = $QueryCacheStore->get_fallback_store();
                $QueryCacheStore = $FallbackStore;
            } while ($FallbackStore);

            $command_ret = call_user_func_array([ $PrimaryQueryCacheStore, self::$commands[$command]['method'] ], []);
            $ret .= implode(' --> ', $structure) . PHP_EOL;
            switch ($command) {
                case 'show ormstore' :
                    $ret .= print_r($command_ret, TRUE);
                    break;
                case 'get qcache hits' :
                    $ret .= sprintf(t::_('%s (%s): %s'), $class_name, $command, $command_ret);
                    break;
                case 'get qcache hits percentage' :
                    $ret .= sprintf(t::_('%s (%s): %s%%'), $class_name, $command, $command_ret);
                    break;
                case 'get qcache misses' :
                    $ret .= sprintf(t::_('%s (%s): %s'), $class_name, $command, $command_ret);
                    break;
                case 'reset qcache stats' :
                    $ret .= sprintf(t::_('%s (%s): stats are reset.'), $class_name, $command);
                    break;
                case 'reset query cache' :
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
