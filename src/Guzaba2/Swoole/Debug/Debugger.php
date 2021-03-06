<?php

declare(strict_types=1);

namespace Guzaba2\Swoole\Debug;

use Azonmedia\Debug\Interfaces\CommandInterface;
use Azonmedia\Debug\Interfaces\DebuggerInterface;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Http\Server;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Kernel\Kernel;

/**
 * Class Debugger
 * Uses Swoole\Coroutine\Server which is available after Swoole version 4.4
 * @package Guzaba2\Swoole
 */
class Debugger extends Base
{
    protected const CONFIG_DEFAULTS = [
        //'enabled'   => TRUE,
        //'base_port' => 10000,//on this port the first worker will listen
        'prompt'    => '{WORKER_ID}>>> ',
    ];

    protected const CONFIG_RUNTIME = [];

    public const DEFAULT_BASE_DEBUG_PORT = 10_000;

    private int $base_debug_port = self::DEFAULT_BASE_DEBUG_PORT;

    /**
     * @var \Guzaba2\Http\Server
     */
    private Server $HttpServer;

    /**
     * @var \Swoole\Coroutine\Server
     */
    private \Swoole\Coroutine\Server $DebugServer;

    /**
     * @var DebuggerInterface
     */
    private DebuggerInterface $Debugger;

    /**
     * @var int
     */
    private int $worker_id;

    /**
     * @var bool
     */
    private bool $is_task_worker_flag;

    /**
     * @var array
     */
    protected array $prompt_stack = [];

    /**
     * Debugger constructor.
     * @param Server $HttpServer
     * @param int $worker_id
     * @param DebuggerInterface $Debugger
     * @param int $base_debug_port
     */
    //public function __construct(\Guzaba2\Http\Server $HttpServer, int $worker_id, \Azonmedia\Debug\Interfaces\DebuggerInterface $Debugger, int $base_debug_port = self::DEFAULT_BASE_DEBUG_PORT)
    public function __construct(\Guzaba2\Http\Server $HttpServer, DebuggerInterface $Debugger, int $base_debug_port = self::DEFAULT_BASE_DEBUG_PORT)
    {
        parent::__construct();

        $this->HttpServer = $HttpServer;
        $this->worker_id = $HttpServer->get_worker_id();
        $this->is_task_worker_flag = $HttpServer->is_task_worker();
        $this->Debugger = $Debugger;
        $this->base_debug_port = $base_debug_port;

        $this->set_prompt($this->substitute_prompt_vars(self::CONFIG_RUNTIME['prompt']));


        $this->DebugServer = new \Swoole\Coroutine\Server($this->HttpServer->get_host(), $this->get_worker_port($this->worker_id), false);
//        $server->handle(function (Swoole\Coroutine\Server\Connection $conn) use ($server) {
//            while(true) {
//                $data = $conn->recv();
//                $json = json_decode($data, true);
//                Assert::eq(is_array($json), $json['data'], 'hello');
//                $conn->send("world\n");
//            }
//        });
        $Function = function (\Swoole\Coroutine\Server\Connection $Connection): void {
            while (true) {
                //print $Connection->exportSocket()->fd.' '.microtime(TRUE).PHP_EOL;
                /** @var \Swoole\Coroutine\Socket $Socket */
                $Socket = $Connection->exportSocket();
                if (!$Socket->checkLiveness()) { //the socket session was interrupted by the other side (app killed)... not using the normal way using "quit"
                    $Connection->close();
                    return;
                }
                $Connection->send($this->get_prompt());
                $command = trim($Connection->recv());
                //Kernel::printk('Received debug command: '.$command.PHP_EOL);
                if (strtolower($command) === 'quit') {
                    $Connection->close();
                    return;
                } else {
                    $set_prompt_to = null;
                    $response = $this->Debugger->handle($command, $this->get_prompt(), $set_prompt_to);

                    //Kernel::printk('Debugger response: '.$response.PHP_EOL);
                    if ($response === null && $command) {
                        $response = sprintf(t::_('Unknown command %s provided. Try "help" or "quit".'), $command);
                    }
                    if ($set_prompt_to !== null) {
                        if ($set_prompt_to === '{RESTORE}') {
                            $this->restore_prompt();
                        } else {
                            $this->set_prompt($set_prompt_to);
                        }
                    }
                    //$json = json_decode($data, true);
                    //Assert::eq(is_array($json), $json['data'], 'hello');
                    $response .= PHP_EOL;
                    $Connection->send($response);
                }
            }
        };
        $this->DebugServer->handle($Function);

        $this->DebugServer->start();
    }

    protected function substitute_prompt_vars(string $prompt): string
    {
        $replacement = ( $this->is_task_worker_flag ? 'TW' : 'W' ) . $this->worker_id;
        $prompt = str_replace('{WORKER_ID}', $replacement, $prompt);
        $prompt = str_replace('{COROUTINE_ID}', \Swoole\Coroutine::getCid(), $prompt);
        return $prompt;
    }

    /**
     * Allows the prompt to be changed from the various debugger backends/command handlers
     */
    public function set_prompt(string $prompt): void
    {
        //$this->prompts = $prompt;
        array_push($this->prompt_stack, $prompt);
    }

    public function restore_prompt(): void
    {
        if (count($this->prompt_stack) > 1) {
            array_pop($this->prompt_stack);
        } else {
            throw new \RuntimeException(sprintf(t::_('There is no new prompt set/changed in order to restore the previous one.')));
        }
    }

    public function get_prompt(): string
    {
        return $this->prompt_stack[ count($this->prompt_stack) - 1];
    }

    /**
     * Returns the debug port for the given worker.
     * @param int $worker_id
     * @return int
     */
    public function get_worker_port(int $worker_id): int
    {
        return $this->base_debug_port + $worker_id;
    }

//    /**
//     * Returns all dirs where there are debug commands classes.
//     * @return array
//     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
//     */
//    public static function get_debug_command_classes_dirs() : array
//    {
//        $ns_prefixes = array_keys(Kernel::get_registered_autoloader_paths());
//        $command_classes = Kernel::get_classes($ns_prefixes, CommandInterface::class);
//        $command_dir_paths = [];
//        foreach ($command_classes as $command_class) {
//            $command_class_path = Kernel::get_class_path($command_class);
//            $dir_path = dirname($command_class_path);
//            if (!in_array($dir_path, $command_dir_paths, TRUE)) {
//                $command_dir_paths[] = $dir_path;
//            }
//        }
//        return $command_dir_paths;
//    }

    /**
     * Returns an associative array with all debug command classes
     * @return array
     * @throws InvalidArgumentException
     * @throws \ReflectionException
     */
    public static function get_debug_command_classes(): array
    {
        $ret = [];
        $ns_prefixes = array_keys(Kernel::get_registered_autoloader_paths());
        $classes = Kernel::get_classes($ns_prefixes, CommandInterface::class);
        foreach ($classes as $class) {
            if ((new \ReflectionClass($class))->isInstantiable()) {
                $ret[] = $class;
            }
        }
        //also get all classes from Azomedia\Debug namespace
        $package_base_path = dirname(( new \ReflectionClass(\Azonmedia\Debug\Debugger::class))->getFileName());//azonmedia/debug is a dependency so should exist...
        $basic_commands_path = $package_base_path . '/Backends/BasicCommands';
        $files = glob($basic_commands_path . '/*.php');
        foreach ($files as $file) {
            require_once($file);
            $class = 'Azonmedia\\Debug\\Backends\\BasicCommands\\' . basename($file, '.php');
            $ret[] = $class;
        }
        return $ret;
    }
}
