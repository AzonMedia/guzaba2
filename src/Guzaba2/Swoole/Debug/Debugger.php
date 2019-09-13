<?php
declare(strict_types=1);

namespace Guzaba2\Swoole\Debug;

use Guzaba2\Base\Base;
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
        'enabled'   => TRUE,
        'base_port' => 10000,//on this port the first worker will listen
        'prompt'    => '{WORKER_ID}>>> ',
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * @var \Guzaba2\Http\Server
     */
    protected $HttpServer;

    /**
     * @var \Swoole\Coroutine\Server
     */
    protected $DebugServer;

    /**
     * @var \Azonmedia\Debug\Interfaces\DebuggerInterface
     */
    protected $Debugger;

    /**
     * @var int
     */
    protected $worker_id;

    /**
     * @var array
     */
    protected $prompt_stack = [];

    public function __construct(\Guzaba2\Http\Server $HttpServer, int $worker_id, \Azonmedia\Debug\Interfaces\DebuggerInterface $Debugger)
    //public function __construct(?\Guzaba2\Http\Server $HttpServer, int $worker_id, \Azonmedia\Debug\Interfaces\DebuggerInterface $Debugger)
    {
        parent::__construct();

        if (!self::is_enabled()) {
            return;
        }

        $this->HttpServer = $HttpServer;
        $this->worker_id = $worker_id;
        $this->Debugger = $Debugger;

        $this->set_prompt($this->substitute_prompt_vars(self::CONFIG_RUNTIME['prompt']));

        //ob_implicit_flush();
        $this->DebugServer = new \Swoole\Coroutine\Server($this->HttpServer->get_host(), self::get_worker_port($worker_id), FALSE);
//        $server->handle(function (Swoole\Coroutine\Server\Connection $conn) use ($server) {
//            while(true) {
//                $data = $conn->recv();
//                $json = json_decode($data, true);
//                Assert::eq(is_array($json), $json['data'], 'hello');
//                $conn->send("world\n");
//            }
//        });
        //$this->DebugServer->handle([$this,'connection_handler']);//Triggers Uncaught TypeError: Argument 1 passed to Swoole\Coroutine\Server::handle() must be callable, array given
        $Function = function (\Swoole\Coroutine\Server\Connection $Connection) : void {
            while (true) {
                $Connection->send($this->get_prompt());
                $command = trim($Connection->recv());
                //Kernel::printk('Received debug command: '.$command.PHP_EOL);
                if (strtolower($command) === 'quit') {
                    $Connection->close();
                    return;
                } else {
                    $set_prompt_to = NULL;
                    $response = $this->Debugger->handle($command, $this->get_prompt(), $set_prompt_to);

                    //Kernel::printk('Debugger response: '.$response.PHP_EOL);
                    if ($response === NULL && $command) {
                        $response = sprintf(t::_('Unknown command %s provided. Try "help" or "quit".'), $command);
                    }
                    if ($set_prompt_to !== NULL) {
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

    protected function substitute_prompt_vars(string $prompt) : string
    {
        $prompt = str_replace('{WORKER_ID}', $this->worker_id, $prompt);
        $prompt = str_replace('{COROUTINE_ID}', \Swoole\Coroutine::getCid(), $prompt);
        return $prompt;
    }

    /**
     * Allows the prompt to be changed from the various debugger backends/command handlers
     */
    public function set_prompt(string $prompt) : void
    {
        //$this->prompts = $prompt;
        array_push($this->prompt_stack, $prompt);
    }

    public function restore_prompt() : void
    {
        if (count($this->prompt_stack) > 1) {
            array_pop($this->prompt_stack);
        } else {
            throw new \RuntimeException(sprintf(t::_('There is no new prompt set/changed in order to restore the previous one.')));
        }
    }

    public function get_prompt() : string
    {
        return $this->prompt_stack[ count($this->prompt_stack) - 1];
    }

    protected function connection_handler(\Swoole\Coroutine\Server\Connection $Connection) : void
    {
        while (true) {
            $command = $Connection->recv();
            $response = $this->Debugger->handle($command);
            if ($response === NULL) {
                $response = sprintf(t::_('Unknown command provided.'));
            }
            //$json = json_decode($data, true);
            //Assert::eq(is_array($json), $json['data'], 'hello');
            $response .= PHP_EOL;
            $conn->send($response);
        }
    }

    public static function is_enabled() : bool
    {
        return self::CONFIG_RUNTIME['enabled'];
    }

    public static function get_worker_port(int $worker_id) : int
    {
        return self::CONFIG_RUNTIME['base_port'] + $worker_id;
    }
}
