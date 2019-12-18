<?php
declare(strict_types=1);

namespace Guzaba2\Mvc;


use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Http\Body\Structured;
use Guzaba2\Mvc\Interfaces\AfterControllerMethodHookInterface;
use Guzaba2\Mvc\Traits\ResponseFactories;
use Guzaba2\Translator\Translator as t;
use Psr\Http\Message\ResponseInterface;

abstract class AfterControllerMethodHook extends Base implements AfterControllerMethodHookInterface
{

    protected const CONFIG_DEFAULTS = [
        'services' => [
            'Events',
        ],
    ];

    protected const CONFIG_RUNTIME = [];

    use ResponseFactories;

    private ResponseInterface $Response;

    public function __construct(ResponseInterface $Response)
    {

        $Body = $Response->getBody();
        if (!($Body instanceof Structured)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided $Response does not use %s body.'), Structured::class ));
        }

        $this->Response = $Response;

    }

    public function __invoke() : ResponseInterface
    {
        return $this->process($this->Response);
    }

    public static function get_vue_namespace() : string
    {
        $called_class = get_called_class();

    }
    
    public static function register_hook(string $controller_class_name, string $event_name, string $hook_class) : void
    {
        $Callback = static function(Event $Event)  use ($hook_class): void
        {
            $Controller = $Event->get_subject();
            $Controller->set_response( (new $hook_class($Controller->get_response()))() );
        };
        $Events = self::get_service('Events');
        $Events->add_class_callback($controller_class_name, $event_name, $Callback);
    }

//    public function get_response(): ResponseInterface
//    {
//        return $this->Response;
//    }
}