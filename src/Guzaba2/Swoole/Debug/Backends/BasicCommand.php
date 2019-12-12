<?php
declare(strict_types=1);

namespace Guzaba2\Swoole\Debug\Backends;

use Psr\Log\LogLevel;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Translator\Translator as t;
use Azonmedia\Debug\Interfaces\CommandInterface;

abstract class BasicCommand implements CommandInterface
{
    abstract public function handle(string $command, string $current_prompt, ?string &$change_prompt_to = NULL) : ?string;

    public static function get_class_name()
    {
        if (FALSE !== ($pos = strrpos(static::class, "\\"))) {
            return substr(static::class, $pos + 1);
        }

        return static::class;
    }

    public function can_handle(string $command) : bool
    {
        return array_key_exists($command, static::$commands);
    }

    public static function handles_commands() : string
    {
        $ret = sprintf(t::_('%s avilable commands:%s'), static::get_class_name(), PHP_EOL);

        foreach (static::$commands as $k => $v) {
            $ret = sprintf(t::_('%s%s%s'), $ret, $k, PHP_EOL);
        }

        return $ret;
    }

    public static function help(?string $command = null) : string
    {
        $class_name = static::get_class_name();

        if (null === $command) {
            return sprintf(t::_('%s - shows details about ORM store(s) - type help %s to see available commands'), $class_name, strtolower($class_name));
        } else if (0 === strcasecmp($class_name, $command)) {
            return static::handles_commands();
        } else {
            return sprintf(t::_('%s: %s'), $command, static::$commands[$command]['help_str']);
        }
    }
}
