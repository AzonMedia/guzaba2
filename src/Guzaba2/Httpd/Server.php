<?php

declare(strict_types=1);

namespace Guzaba2\Httpd;

use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Http\Interfaces\WorkerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Guzaba2\Translator\Translator as t;

/**
 * Class Server
 * @package Guzaba2\Httpd
 *
 * Represents a typical web server like Apache or CGI
 * This is just a wrapper class for the method serving the request.
 * The server settings like host & port are only used to store the correct settings in case they are needed by another clas (thorugh the Server service)
 */
class Server extends \Guzaba2\Http\Server
{

    protected array $handlers = [];

    public function __construct(string $host, int $port, array $options = [])
    {
        if (!isset($options['document_root'])) {
            //set the document root to the directory where the entry point index.php is
            //this can be obtained by the backtrace
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $options['document_root'] = dirname($bt[ count($bt) - 1]['file']);

        }
        parent::__construct($host, $port, $options);
    }

    public function on(string $event_name, callable $callable): void
    {
        $this->handlers[$event_name] = $callable;
    }

    public function start(): void
    {
        throw new RunTimeException(sprintf(t::_('The %s class does not support the %s() method.'), get_class($this), __METHOD__));
    }

    public function stop(): void
    {
        throw new RunTimeException(sprintf(t::_('The %s class does not support the %s() method.'), get_class($this), __METHOD__));
    }

    public function is_task_worker(): bool
    {
        return false;
    }

    public function get_worker_id(): int
    {
        return -1;
    }

    public function get_worker_pid(): int
    {
        return getmypid();
    }

    public function get_worker(): WorkerInterface
    {
        throw new RunTimeException(sprintf(t::_('The %s class does not support the %s() method.'), get_class($this), __METHOD__));
    }

    public function get_document_root(): ?string
    {
        //return $this->option_is_set('document_root') ? $this->get_option('document_root'): NULL;
        return $this->get_option('document_root');
    }


    /**
     * As this class does not represent an embedded server the handle method needs to be explicitly invoked.
     * Unlike in Swoole where the handler is invoked internally.
     * @param string $event_name
     * @param mixed ...$args
     * @return mixed
     */
    public function handle(string $event_name, ...$args) /* mixed */
    {
        //$handler = $this->get_handler($event_name);
        //return $handler(...$args);
        return $this->get_handler($event_name)(...$args);
    }

    /**
     * The event name is case insensitive.
     * @param string $event_name
     * @return callable|null
     */
    public function get_handler(string $event_name): ?callable
    {
        $ret = null;
        foreach ($this->handlers as $handler_name => $callable) {
            if (strtolower($handler_name) === strtolower($event_name)) {
                $ret = $callable;
                break;
            }
        }
        return $ret;
    }
}