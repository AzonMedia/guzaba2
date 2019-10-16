<?php
declare(strict_types=1);

namespace Guzaba2\Mvc;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\LogicException;
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
use Guzaba2\Mvc\Interfaces\PerActionPhpViewInterface;
use Guzaba2\Mvc\Interfaces\PerControllerPhpViewInterface;

/**
 * Class ExecutorMiddleware
 *
 *
 * @package Guzaba2\Mvc
 */
class ExecutorMiddleware extends Base implements MiddlewareInterface
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

    protected const DEFAULT_TYPE_HANDLER = 'default_handler';

    public function __construct(Server $Server)
    {
        parent::__construct();

        $this->Server = $Server;
    }

    /**
     *
     *
     * @param ServerRequestInterface $Request
     * @param RequestHandlerInterface $Handler
     * @return ResponseInterface
     * @throws RunTimeException
     */
    public function process(ServerRequestInterface $Request, RequestHandlerInterface $Handler) : ResponseInterface
    {
        $controller_callable = $Request->getAttribute('controller_callable');
        if ($controller_callable) {
            if (is_array($controller_callable)) {
                $controller_arguments = $Request->getAttribute('controller_arguments') ?? [];


                if ($body_params = $Request->getParsedBody()) {
                    if (! empty(array_intersect($controller_arguments, $body_params))) {
                        throw new RunTimeException(sprintf(t::_('Arguments is not unique.')));
                    }

                    $controller_arguments += $body_params;
                }

                //check if controller has init method
                $init_method_exists = \method_exists($controller_callable[0], '_init');

                if ($init_method_exists) {
                    $RMethod = new \ReflectionMethod(get_class($controller_callable[0]), '_init');
                    $parameters = $RMethod->getParameters();

                    $ordered_parameters = [];

                    foreach ($parameters as $key => $parameter) {
                        $argType = $parameter->getType();

                        if (isset($controller_arguments[$parameter->getName()])) {
                            $value = $controller_arguments[$parameter->getName()];
                        } elseif ($parameter->isDefaultValueAvailable() && ! isset($controller_arguments[$parameter->getName()])) {
                            $value = $parameter->getDefaultValue();
                        }

                        if (isset($value)) {
                            $value = $controller_arguments[$parameter->getName()];
                            //will throw exception if type missing
                            settype($value, (string) $argType);
                            $ordered_parameters[] = $value;
                            unset($value);
                        }
                    }

                    //\call_user_func_array([$controller_callable[0], '_init'], $ordered_parameters);
                    $Response = [$controller_callable[0], '_init'](...$ordered_parameters);
                }

                if (empty($Response)) { //if the _init function hasnt returned any response... it may return response due an error, in the normal case the actual action should be invoked
                    $RMethod = new \ReflectionMethod(get_class($controller_callable[0]), $controller_callable[1]);
                    $parameters = $RMethod->getParameters();
                    $ordered_parameters = [];

                    foreach ($parameters as $key => $parameter) {
                        $argType = $parameter->getType();

                        if (isset($controller_arguments[$parameter->getName()])) {
                            $value = $controller_arguments[$parameter->getName()];
                        } elseif ($parameter->isDefaultValueAvailable() && ! isset($controller_arguments[$parameter->getName()])) {
                            $value = $parameter->getDefaultValue();
                        }

                        if (isset($value)) {
                            $value = $controller_arguments[$parameter->getName()];
                            //will throw exception if type missing
                            settype($value, (string) $argType);
                            $ordered_parameters[] = $value;
                            unset($value);
                        }
                    }

                    $Response = $controller_callable(...$ordered_parameters);
                }

                $Body = $Response->getBody();
                if ($Body instanceof Structured) {
                    $requested_content_type = $Request->getContentType();
                    $type_handler = self::CONTENT_TYPE_HANDLERS[$requested_content_type] ?? self::DEFAULT_TYPE_HANDLER;
                    $Response = [$this, $type_handler]($Request, $Response);
                } else {
                    //return the response as it is - it is already a stream and should contain all the needed headers
                }



                //TODO add cleanup code that unsets all properties set on the child controller


                return $Response;
            } elseif (is_object($controller_callable)) {
                //Closure or class with __invoke
                $Response = $controller_callable($Request);
                return $Response;
            } elseif (is_string($controller_callable)) {
                $Response = $controller_callable($Request);
                return $Response;
            } else {
                throw new LogicException(sprintf(t::_('An unsupported type "%s" for controller_callable encountered.'), gettype($controller_callable)));
            }
        } else {
            //pass the processing to the next handler - usually this will result in the default response
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


    /**
     * @param RequestInterface $Request
     * @param ResponseInterface $Response
     * @return ResponseInterface
     * @throws RunTimeException
     */
    protected function default_handler(RequestInterface $Request, ResponseInterface $Response) : ResponseInterface
    {
        return $this->html_handler($Request, $Response);
    }

    protected function html_handler(RequestInterface $Request, ResponseInterface $Response) : ResponseInterface
    {
        $controller_callable = $Request->getAttribute('controller_callable');
        if ($controller_callable instanceof PerActionPhpViewInterface) {
            $Response = $this->per_action_php_view_handler($Request, $Response);
        } elseif ($controller_callable instanceof PerControllerPhpViewInterface) {
            $Response = $this->per_controller_php_view_handler($Request, $Response);
        } else {
            $Response = $this->per_controller_php_view_handler($Request, $Response);
        }
        return $Response;
    }

    /**
     * Default html_handler
     * Calls View object for parsing the response
     * The view object is located PARENT_DIRECTORY_OF_CONTROLLER/Views/Controller_name.php::action_name
     *
     * @param RequestInterface $Request
     * @param ResponseInterface $Response
     * @return ResponseInterface
     * @throws RunTimeException
     */
    protected function per_controller_php_view_handler(RequestInterface $Request, ResponseInterface $Response) : ResponseInterface
    {
        $controller_callable = $Request->getAttribute('controller_callable');
        $content_type = $Request->getContentType();
        //html null and the rest...
        //if the callable is a class and this class is a controller then we can do a lookup for a corresponding view
        //the first element may be a class or an instance so is_a() should be used
        if (
            is_array($controller_callable)
            &&
            isset($controller_callable[0])
            && is_a($controller_callable[0], Controller::class, TRUE)
        ) {
            $controller_class = is_string($controller_callable[0]) ? $controller_callable : get_class($controller_callable[0]);
            $view_class = str_replace('\\Controllers\\', '\\Views\\', $controller_class);
            if (class_exists($view_class)) {
                if (method_exists($view_class, $controller_callable[1])) {
                    ob_start();
                    [new $view_class($Response), $controller_callable[1]]();
                    $view_output = ob_get_contents();
                    ob_end_clean();
                    if (!strlen($view_output)) {
                        throw new RunTimeException(sprintf(t::_('There is no content printed from view %s::%s().'), $view_class, $controller_callable[1]));
                    }
                    $StreamBody = new Stream(NULL, $view_output);
                    $Response = $Response->
                        withBody($StreamBody)->
                        withHeader('Content-type', ContentType::TYPES_MAP[ContentType::TYPE_HTML]['mime'])->
                        withHeader('Content-Length', (string) strlen($view_output));
                    return $Response;
                } else {
                    throw new RunTimeException(sprintf(t::_('The view class %s has no method %s.'), $view_class, $controller_callable[1]));
                }
            } else {
                if ($content_type === NULL) {
                    //no content type is requested (or recognized) and we have a structured response
                    //JSON can be returned instead
                    //or throw an error
                    return $this->json_handler($Request, $Response);
                } else {
                    throw new RunTimeException(sprintf(t::_('Unable to return response from the requested content type %s. A structured body response is returned by controller %s but there is no corresponding view %s.'), $content_type, $controller_class, $view_class));
                }
            }
        } else {
            if ($content_type === NULL) {
                return $this->json_handler($Request, $Response);
            } else {
                throw new RunTimeException(sprintf(t::_('Unable to return response from the requested content type %s. A structured body response is returned by controller %s but there is no view.'), $content_type, print_r($controller_callable, TRUE)));
            }
        }
    }


    /**
     * Alternative html_handler
     * Uses ordinary .phtml files for view scripts
     *
     * The path to the view script is PARENT_DIRECTORY_OF_CONTROLLER/views/controller_name/action_name.phtml
     *
     * @param RequestInterface $Request
     * @param ResponseInterface $Response
     * @return ResponseInterface
     * @throws RunTimeException
     * @throws \ReflectionException
     */
    protected function per_action_php_view_handler(RequestInterface $Request, ResponseInterface $Response) : ResponseInterface
    {
        $controller_callable = $Request->getAttribute('controller_callable');
        $content_type = $Request->getContentType();
        if (is_array($controller_callable) && isset($controller_callable[0]) && is_a($controller_callable[0], Controller::class, TRUE)) {
            // Resolving the view script file path
            $controller_class = is_string($controller_callable[0]) ? $controller_callable : get_class($controller_callable[0]);
            $reflection = new \ReflectionClass($controller_class);
            $controller_class_path = $reflection->getFileName();
            $controller_path_parts = pathinfo($controller_class_path);
            $views_dir = str_replace('/Controllers', '/views', $controller_path_parts['dirname']);
            $view_file_path = sprintf('%s/%s/%s.phtml', $views_dir, strtolower($controller_path_parts['filename']), $controller_callable[1]);

            if (file_exists($view_file_path)) {
                ob_start();
                $this->render_view($view_file_path, $Response);
                $view_output = ob_get_clean();
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
                    throw new RunTimeException(sprintf(t::_('Unable to return response from the requested content type %s. A structured body response is returned by controller %s but there is no corresponding view.'), $content_type, $controller_class));
                }
            }
        } else {
            if ($content_type === NULL) {
                return $this->json_handler($Request, $Response);
            } else {
                throw new RunTimeException(t::_('Unable to return response; Controller not found.'));
            }
        }
    }

    /**
     * Renders the view script
     * All the array keys passed in the Structure of the response body will be accessible through variables with the same names in the view script
     * For example $struct['message'] will be accessible through $message
     *
     * @param $template
     * @param ResponseInterface $Response
     */
    protected function render_view($template, ResponseInterface $Response)
    {
        extract($Response->getBody()->getStructure());
        include $template;
    }
}
