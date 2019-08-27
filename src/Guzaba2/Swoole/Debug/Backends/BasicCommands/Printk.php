<?php

namespace Guzaba2\Swoole\Debug\Backends\BasicCommands;

use Azonmedia\Debug\Interfaces\CommandInterface;
use Guzaba2\Kernel\Kernel;

/**
 * Class Printk
 * Prints messages to the standard output of the swoole server
 * @package Guzaba2\Swoole\Debug\Backends\BasicCommands
 */
class Printk implements CommandInterface
{

    public function handle(string $command, string $current_prompt, ?string &$change_prompt_to = NULL) : ?string
    {
        $ret = NULL;
        if ($this->can_handle($command)) {
            $message = str_replace('printk ','',$command).' ';
            if (!strlen($message)) {
                $ret = 'Please provide the message to be printed.';
            } else {
                $ret = sprintf('The message "%s" printed to the standard output of the swoole server.', $message);
                Kernel::printk($message.PHP_EOL);
            }

        }
        return $ret;
    }

    public function can_handle(string $command) : bool
    {
        return strpos($command, 'printk') === 0;
    }

    public static function handles_commands() : string
    {
        return 'printk';
    }

    public static function help() : string
    {
        return 'printk - prints a message to the standard output of the swoole server';
    }
}
