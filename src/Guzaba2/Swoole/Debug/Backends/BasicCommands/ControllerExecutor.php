<?php
declare(strict_types=1);

namespace Guzaba2\Swoole\Debug\Backends\BasicCommands;

use Azonmedia\Debug\Interfaces\CommandInterface;
use Azonmedia\Utilities\GeneralUtil;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Http\Body\Stream;
use Guzaba2\Http\Body\Structured;
use Guzaba2\Http\ContentType;
use Guzaba2\Http\Method;
use Guzaba2\Http\QueueRequestHandler;
use Guzaba2\Http\Request;
use Guzaba2\Http\RequestHandler;
use Guzaba2\Http\Response;
use Guzaba2\Http\StatusCode;
use Guzaba2\Http\Uri;
use Guzaba2\Swoole\Debug\Backends\BasicCommand;
use Guzaba2\Swoole\Server;
use Guzaba2\Translator\Translator as t;
use GuzabaPlatform\Platform\Application\Middlewares;
use Psr\Http\Message\ServerRequestInterface;
use SebastianBergmann\CodeCoverage\Report\PHP;

/**
 * Class ControllerExecutor
 * @package Guzaba2\Swoole\Debug\Backends\BasicCommands
 *
 * Allows for execution of any controller (method + route) like:
 * W0>>> execute get /some/route {"json":"args"}
 * W0>>> execute get /some/route arg1=val1 arg2=val2
 *
 * Use W0>>> accept to set json or text (equivalent of application/json and text/plain (default one)
 * W0>>> accept json
 * W0>>> accept application/json
 * W0>>> accept text
 * W0>>> accept text/plain
 */
class ControllerExecutor extends Base implements CommandInterface
{

    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'Middlewares',
            'Server'
        ],
    ];

    protected const CONFIG_RUNTIME = [];

    private const SUPPORTED_COMMANDS = [
        'execute',
        'accept',
    ];

    private const SUPPORTED_SUBCOMMANDS = [
        'connect',
        'delete',
        'get',
        'head',
        'options',
        'patch',
        'post',
        'put',
        'trace',
    ];

    private const COMMANDS_HELP = [
        'execute'   => 'METHOD should be string like POST, GET etc, route should be like /some/route. The optional ARGS can be provided as JSON string or like arg1=val1 arg2=val2',
        'accept'    => 'The valid CONTENT_TYPEs are text (text/plain), json (application/json)',
    ];

    private const SUPPORTED_CONTENT_TYPES = [
        'json'              => ContentType::TYPE_JSON,
        'application/json'  => ContentType::TYPE_JSON,
        'text'              => ContentType::TYPE_TEXT,
        'text/plain'        => ContentType::TYPE_TEXT,
    ];
    
    //private string $accept_content_type = ContentType::TYPE_TEXT;
    private string $accept_content_type = ContentType::TYPE_JSON;//better the default response to be JSON as not many controllers will have a corresponding view for the TEXT type

    /**
     * @param string $command The command sent to the debugger
     * @param string $current_prompt The current value of the prompt is passed
     * @param string|null $change_prompt_to Passed by reference. If a value is passed then the prompt of the debugger will be changed to this value
     * @return string|null If NULL is returned it means it can not handle the provided $command
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public function handle(string $command, string $current_prompt, ?string &$change_prompt_to = NULL): ?string
    {
        $ret = NULL;
        $command_arr = explode(' ',$command);
        //if ($command === 'help '.GeneralUtil::get_class_name(static::class)) {
        if ($command_arr[0] === 'help') {
            if (
                $command_arr[1] === GeneralUtil::get_class_name(static::class)
                ||
                in_array($command_arr[1], self::SUPPORTED_COMMANDS)
            ) {
                unset($command_arr[0]);
                $ret = static::help(implode(' ', $command_arr));
            }
        } elseif ($command_arr[0] === 'accept') {
            $ret = $this->handle_accept($command);
        } elseif ($command_arr[0] === 'execute') {
            $method = $this->extract_method($command);
            $route = $this->extract_route($command);
            $arguments = $this->extract_arguments($command);
            $ret = $this->handle_execute($method, $route, $arguments);
        }
        return $ret;
    }

    /**
     * Extracts the method part in an execute command.
     * @param string $command The unmodified command as provided to the debugger
     * @return string
     * @throws InvalidArgumentException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    private function extract_method(string $command): string
    {
        $command_arr = explode(' ',$command);
        if (!isset($command_arr[1])) {
            throw new InvalidArgumentException(sprintf(t::_('The command %1s does not contain method.'), $command));
        }
        $method = strtolower($command_arr[1]);
        if (!in_array($method, self::SUPPORTED_SUBCOMMANDS)) {
            throw new InvalidArgumentException(sprintf(t::_('The command %1s contains an invalid method %2s.'), $command, $method));
        }
        return $method;
    }

    /**
     * Extracts the route part in an execute command.
     * Does not actually check does the route exist.
     * @param string $command The unmodified command as provided to the debugger
     * @return string
     * @throws InvalidArgumentException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    private function extract_route(string $command): string
    {
        $command_arr = explode(' ',$command);
        if (!isset($command_arr[2])) {
            throw new InvalidArgumentException(sprintf(t::_('No route is provided.')));
        }
        $route = $command_arr[2];
        if ($route[0]!=='/') {
            throw new InvalidArgumentException(sprintf(t::_('The provided command %1s contains an invalid route $2s. The route must begin with /.'), $command, $route));
        }
        return $route;
    }

    /**
     * Extracts the arguments for the controller from the passed command.
     * @param string $command
     * @return array
     */
    private function extract_arguments(string $command): array
    {
        $command_arr = explode(' ',$command);
        $args = [];
        if (isset($command_arr[3])) {
            //there are some arguments provided;
            //try json first
            try {
                $args = json_decode($command_arr[3], TRUE,512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $Exception) {
                for ($aa = 2; $aa < count($command_arr); $aa++) {
                    $arg_arr = explode('=',$command_arr[$aa]);
                    $args[$arg_arr[0]] = $arg_arr[1] ?? NULL;
                }
            }
        }
        return $args;
    }

    /**
     * Handles the "accept" command.
     * @param string $command
     * @return string|null
     * @throws InvalidArgumentException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    private function handle_accept(string $command): ?string
    {
        $command_arr = explode(' ', $command);
        if (empty($command_arr[1])) {
            throw new InvalidArgumentException(sprintf(t::_('The command %1s does not contain an accept type.'), $command));
        }
        $accept_type = strtolower($command_arr[1]);
        if (!isset(self::SUPPORTED_CONTENT_TYPES[$accept_type])) {
            throw new InvalidArgumentException(sprintf(t::_('The command %1s contains an unsupported accept content type %2s. The supported accept content types are %3s.'), $command, $accept_type, implode(',', array_keys(self::SUPPORTED_CONTENT_TYPES)) ));
        }
        $this->accept_content_type = self::SUPPORTED_CONTENT_TYPES[$accept_type];

        $ret = sprintf(t::_('The accept content type is set to %1s.'), $accept_type);
        return $ret;
    }

    /**
     * Handles the "execute" command.
     * @param string $method
     * @param string $route
     * @param array $args
     * @return string|null
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     * @throws LogicException
     */
    private function handle_execute(string $method, string $route, array $args): string
    {

        $ret = '';
        if (self::has_service('Middlewares')) {


            $message = t::_('Content not found or request not understood. The request contains a method and route that could not be found.');
            $Body = new Stream();
            $Body->write($message);
            $Body->rewind();
            $DefaultResponse = (new Response(StatusCode::HTTP_NOT_FOUND, [], $Body) )->withHeader('Content-Length', (string) strlen($message));
            $FallbackHandler = new RequestHandler($DefaultResponse);//this will produce 404
            $QueueRequestHandler = new QueueRequestHandler($FallbackHandler);//the default response prototype is a 404 message
            /** @var Middlewares $Middlewares */
            $Middlewares = self::get_service('Middlewares');
            foreach ($Middlewares as $Middleware) {
                $QueueRequestHandler->add_middleware($Middleware);
            }
            $Request = $this->form_request($method, $route, $args);
            Coroutine::init($Request);
            /** @var Server $Server */
            $Server = self::get_service('Server');
            $Server->get_worker()->increment_served_console_requests();

            $Response = $QueueRequestHandler->handle($Request);

            $ret = $Response->getBody()->getContents();
        } else {
            //try to get the Router service and execute the ExecutorMiddleware
            //TODO - implement this
        }

        return $ret;
    }

    /**
     * Creates a Request from the provided arguments and the selected accept type by the self::handle_accept()
     * @param string $method
     * @param string $route
     * @param array $args
     * @return ServerRequestInterface
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    private function form_request(string $method, string $route, array $args): ServerRequestInterface
    {
        $Uri = new Uri('http', 'localhost', 80, $route);
        $headers = ['Accept' => [ContentType::TYPES_MAP[$this->accept_content_type]['mime']]];
        $cookies = [];
        $time = time();
        /** @var Server $Server */
        $Server = self::get_service('Server');
        $server_params = [
            'console_request'       => TRUE,
            'request_uri'           => $route,
            'path_info'             => $route,
            'request_time'          => $time,
            'request_time_float'    => microtime(true),
            'server_protocol'       => 'HTTP/1.1',//for the sake of controllers executing correctly...
            'server_port'           => $Server->get_worker()->get_debug_port(),
            'remote_port'           => $Server->get_worker()->get_debug_port(),
            'remote_addr'           => '127.0.0.1',
            'master_time'           => $time,

        ];

        $Body = new Structured($args);
        $Request = new Request($method, $Uri, $headers, $cookies, $server_params, $Body);
        return $Request;
    }

    /**
     * Returns a bool can the passed command be handled by this Command handler
     * @param string $command
     * @return bool
     */
    public function can_handle(string $command): bool
    {
        $first_command = explode(' ', $command)[0];
        return in_array($first_command, self::SUPPORTED_COMMANDS);
    }

    /**
     * A static method giving a list as string of the commands it can handle
     * @return string
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public static function handles_commands(): string
    {
        $class_name = GeneralUtil::get_class_name(static::class);
        $ret = sprintf(t::_('%1s available commands:'), $class_name ).PHP_EOL;
        //$ret .= 'accept CONTENT_TYPE'.PHP_EOL;
        //$ret .= 'execute METHOD ROUTE [ARGS]'.PHP_EOL;
        $ret .= sprintf(t::_('%1s METHOD ROUTE [ARGS] - executes a controller with the provided METHOD, ROUTE and optional ARGS'), 'execute').PHP_EOL;
        $ret .= sprintf(t::_('%1s CONTENT_TYPE - sets the content type for the response'), 'accept').PHP_EOL;
        return $ret;
    }

    /**
     * A static method returning help information
     * @param string|null $command
     * @return string
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public static function help(?string $command = NULL): string
    {
        $class_name = GeneralUtil::get_class_name(static::class);
        if (NULL === $command) {
            return sprintf(t::_('%1s - allows for controllers execution - type help %2s to see available commands'), $class_name, strtolower($class_name));
        } else if (0 === strcasecmp($class_name, $command)) {
            return static::handles_commands();
        } else {
            if (isset(self::COMMANDS_HELP[$command])) {
                return sprintf(t::_('%s: %s'), $command, self::COMMANDS_HELP[$command]);
            } else {
                return sprintf(t::_('Unknown command %1s provided to help.'), $command);
            }

        }
    }
}