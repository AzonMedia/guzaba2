<?php
declare(strict_types=1);

namespace Guzaba2\Mvc;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Http\Body\Stream;
use Guzaba2\Http\Response;
use Guzaba2\Http\Server;
use Guzaba2\Http\Body\Structured;
use Guzaba2\Http\ContentType;
use Guzaba2\Translator\Translator as t;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class ExecutorMiddleware
 *
 *
 * @package Guzaba2\Mvc
 */
class ExecutorMiddleware extends Base
implements MiddlewareInterface
{
    /**
     * @var Server
     */
    protected $Server;

    /**
     * To be used when the Body of the Response is of type Structured
     */
    protected const CONTENT_TYPE_HANDLERS = [
        ContentType::TYPE_XML   => 'xml_hanlder',
        //ContentType::TYPE_SOAP  => 'soap_handler',
        ContentType::TYPE_JSON  => 'json_handler',
        ContentType::TYPE_HTML  => 'html_handler',
    ];

    protected const DEFAULT_TYPE_HANDLER = 'default_hanlder';

    public function __construct(Server $Server)
    {
        parent::__construct();

        $this->Server = $Server;
    }

    public function process(ServerRequestInterface $Request, RequestHandlerInterface $Handler) : ResponseInterface
    {

        $controller_callable = $Request->getAttribute('controller_callable');
        if ($controller_callable) {
            //TODO - execute the _init() method too
            $Response = $controller_callable();//pass the request arguments here
            $Body = $Response->getBody();
            if ($Body instanceof Structured) {
                $requested_content_type = $Request->getContentType();
                $type_handler = self::CONTENT_TYPE_HANDLERS[$requested_content_type] ?? self::DEFAULT_TYPE_HANDLER;
                $Response = [$this, $type_handler]($Request, $Response);
            } else {
                //return the response as it is - it is already a stream and should contain all the needed headers
            }
            return $Response;
        } else {
            //proceed processing which will result in the default response
        }

        return $Handler->handle($Request);
    }

    protected function json_handler(RequestInterface $Request, ResponseInterface $Response) : ResponseInterface
    {
        $StructuredBody = $Response->getBody();
        $structure = $StructuredBody->getStructure();
        $json_string = json_encode($structure);
        $StreamBody = new Stream(NULL, $json_string);
        $Response = $Response->
            withBody($StreamBody)->
            withHeader('Content-type', ContentType::TYPES_MAP[ContentType::TYPE_JSON]['mime'])->
            withHeader('Content-Length', (string) strlen($json_string));
        return $Response;
    }

    protected function html_handler(RequestInterface $Request, ResponseInterface $Response) : ResponseInterface
    {
        $controller_callable = $Request->getAttribute('controller_callable');
        //html null and the rest...
        //if the callable is a class and this class is a controller then we can do a lookup for a corresponding view
        //the first element may be a class or an instance so is_a() should be used
        if (is_array($controller_callable) && isset($controller_callable[0]) && is_a($controller_callable[0], Controller::class, TRUE)) {
            $controller_class = is_string($controller_callable[0]) ? $controller_callable : get_class($controller_callable[0]);
            $view_class = str_replace('\\Controllers\\', '\\Views\\', $controller_class);
            if (class_exists($view_class)) {
                ob_start();
                [ new $view_class($Response), $controller_callable[1]]();
                $view_output = ob_get_contents();
                ob_end_clean();
                $StreamBody = new Stream(NULL, $view_output);
                $Response = $Response->
                    withBody($StreamBody)->
                    withHeader('Content-type', ContentType::TYPES_MAP[ContentType::TYPE_HTML]['mime'])->
                    withHeader('Content-Length', (string) strlen($view_output));
                return $Response;
            } else {
                if ($content_type === NULL) {
                    //no content type is requested (or recognized) and we have a structured response
                    //JSON can be returned instead
                    //or throw an error
                    return $this->json_handler($Request, $Response);
                } else {
                    throw new RunTimeException(sprintf(t::_('Unable to return response from the requested content type %s. A structured body response is returned by controller %s but there is no corresponding view %s.'), $requested_content_type, $controller_class, $view_class));
                }
            }
        } else {
            if ($content_type === NULL) {
                return $this->json_handler($Request, $Response);
            } else {
                throw new RunTimeException(sprintf(t::_('Unable to return response from the requested content type %s. A structured body response is returned by controller %s but there is no view.'), $requested_content_type, $controller_class));
            }
        }
    }

    protected function default_hanlder(RequestInterface $Request, ResponseInterface $Response) : ResponseInterface
    {
        return $this->html_handler($Request, $Response);
    }

}