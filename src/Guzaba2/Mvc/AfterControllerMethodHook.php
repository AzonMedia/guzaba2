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

    }

//    public function get_response(): ResponseInterface
//    {
//        return $this->Response;
//    }
}