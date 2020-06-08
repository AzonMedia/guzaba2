<?php
declare(strict_types=1);

namespace Guzaba2\Mvc;

use Azonmedia\Reflection\Reflection;
use Azonmedia\Reflection\ReflectionMethod;
use Guzaba2\Application\Application;
use Guzaba2\Authorization\Exceptions\PermissionDeniedException;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\BaseException;
use Guzaba2\Base\Exceptions\ClassValidationException;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Http\Body\Str;
use Guzaba2\Http\Body\Stream;
use Guzaba2\Http\Method;
use Guzaba2\Http\Response;
use Guzaba2\Http\Server;
use Guzaba2\Http\Body\Structured;
use Guzaba2\Http\ContentType;
use Guzaba2\Http\StatusCode;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Kernel\Runtime;
use Guzaba2\Mvc\Interfaces\ControllerInterface;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Orm\Exceptions\ValidationFailedException;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Translator\Translator as t;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Guzaba2\Mvc\Interfaces\PerActionPhpViewInterface;
use Guzaba2\Mvc\Interfaces\PerControllerPhpViewInterface;
use Guzaba2\Mvc\Exceptions\InterruptControllerException;
use Guzaba2\Orm\Interfaces\ValidationFailedExceptionInterface;
use Psr\Log\LogLevel;

//use Guzaba2\Mvc\Interfaces\ControllerWithAuthorizationInterface;

/**
 * Class ExecutorMiddleware
 *
 *
 * @package Guzaba2\Mvc
 */
class ExecutorMiddleware extends Base implements MiddlewareInterface
{

    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'Events',
        ],
    ];

    protected const CONFIG_RUNTIME = [];


    /**
     * To be used when the Body of the Response is of type Structured
     */
    protected const CONTENT_TYPE_HANDLERS = [
        ContentType::TYPE_XML       => 'xml_hanlder',
        //ContentType::TYPE_SOAP    => 'soap_handler',
        ContentType::TYPE_JSON      => 'json_handler',
        ContentType::TYPE_HTML      => 'html_handler',
        ContentType::TYPE_NATIVE    => 'native_handler',
    ];

    protected const DEFAULT_TYPE_HANDLER = 'json_handler';

    private string $default_handler = self::DEFAULT_TYPE_HANDLER;

//    /**
//     * @var Server
//     */
//    protected Server $Server;

    protected string $override_html_content_type = '';

    /**
     * Multidimensional associative array [$class][$method][0] => ['name' => '', 'type'=>'', 'default_value'=>'']
     * where default_value is optional and will not be present if there is no default value
     * @var array
     */
    protected static array $controllers_params = [];

//    public function __construct(Server $Server, string $override_html_content_type = '')

    /**
     * ExecutorMiddleware constructor.
     * @param string $default_handler
     */
    public function __construct(string $default_handler = self::DEFAULT_TYPE_HANDLER)
    {
        
//        if ($override_html_content_type && !ContentType::is_valid_content_type($override_html_content_type)) {
//            throw new InvalidArgumentException(sprintf(t::_('The provided $override_html_content_type argument to %s() is not a valid content type.'), __METHOD__));
//        }
        
        parent::__construct();
        $this->default_handler = $default_handler;

//        $this->Server = $Server;
//
//        $this->override_html_content_type = $override_html_content_type;
    }

    /**
     * Keeps in static cache the data of all controllers parameters to avoid using Reflection during runtime.
     * @param array $ns_prefixes
     * @throws ClassValidationException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public static function initialize_controller_arguments(array $ns_prefixes) : void
    {
        //$ns_prefixes = array_keys(Kernel::get_registered_autoloader_paths());
        $controller_classes = Controller::get_controller_classes($ns_prefixes);
        foreach ($controller_classes as $class) {

            foreach ((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $RMethod) {
                if ($RMethod->isConstructor()) {
                    continue;
                }
                if ($RMethod->getDeclaringClass()->getName() !== $class) {
                    continue;//do not validate parent methods
                }
                $ordered_parameters = [];
                foreach ($RMethod->getParameters() as $RParameter) {
                    if (!($Rtype = $RParameter->getType())) {
                        throw new ClassValidationException(sprintf(t::_('The controller action %s::%s() has argument %s which is lacking type. All arguments to the controller actions must have their types set.'), $class, $RMethod->getName(), $RParameter->getName() ));
                    }
                    $param_data = ['name' => $RParameter->getName(), 'type' => $RParameter->getType()->getName()];
                    if ($RParameter->isDefaultValueAvailable()) {
                        $param_data['default_value'] = $RParameter->getDefaultValue();
                    }
                    $ordered_parameters[] = $param_data;
                }
                self::$controllers_params[$class][$RMethod->getName()] = $ordered_parameters;
            }
        }
    }

    /**
     * Checks the permissions (if applicable) and executes the controller.
     * If there is view it also executes the view.
     * @param ServerRequestInterface $Request
     * @param RequestHandlerInterface $Handler
     * @return ResponseInterface
     * @throws LogicException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public function process(ServerRequestInterface $Request, RequestHandlerInterface $Handler) : ResponseInterface
    {

        $requested_content_type = $Request->getContentType();

//        if ( ($requested_content_type === NULL || $requested_content_type === ContentType::TYPE_HTML) && $this->override_html_content_type) {
//            $requested_content_type = $this->override_html_content_type;
//        }
        //$type_handler = self::CONTENT_TYPE_HANDLERS[$requested_content_type] ?? self::DEFAULT_TYPE_HANDLER;
        //if no content type or unknown content type use the default handler
        $type_handler = self::CONTENT_TYPE_HANDLERS[$requested_content_type] ?? $this->default_handler;

        $controller_callable = $Request->getAttribute('controller_callable');
        if ($controller_callable) {
            if (is_array($controller_callable)) {

                $controller_arguments = $Request->getAttribute('controller_arguments') ?? []; //in case there are arguments injected by previous middlware like the Routing


                $body_params = $Request->getParsedBody();

                if ($body_params) {

                    if (in_array($Request->getMethodConstant(), [Method::HTTP_POST, Method::HTTP_PUT, Method::HTTP_PATCH]) ) {

                        if ($repeating_arguments = array_intersect( array_keys($controller_arguments), array_keys($body_params) )) {
                            //throw new RunTimeException(sprintf(t::_('The following arguments are present in both the PATH and the request BODY: %s.'), array_values($repeating_arguments) ));
                            $message = sprintf(t::_('The following arguments are present in both the PATH and the request BODY: %s.'), print_r(array_values($repeating_arguments), true) );
                            $Body = new Structured( [ 'message' => $message ] );
                            $Response = new Response(StatusCode::HTTP_BAD_REQUEST,[], $Body);
                            $Response = [$this, $type_handler]($Request, $Response);
                            return $Response;
                        }

                        $controller_arguments += $body_params;

                    } else {
                        $message = sprintf(t::_('Bad request. Request body is supported only for POST, PUT, PATCH methods. %s request was received along with %s arguments.'), $Request->getMethod() , count($body_params) );
                        $Body = new Structured( [ 'message' => $message ] );
                        $Response = new Response(StatusCode::HTTP_BAD_REQUEST,[], $Body);
                        $Response = [$this, $type_handler]($Request, $Response);
                        return $Response;
                    }

                }
                if ($uploaded_files = $Request->getUploadedFiles()) {

                    if (in_array($Request->getMethodConstant(), [Method::HTTP_POST]) ) {

                        if ($uploaded_files) {
                            if ($repeating_arguments = array_intersect($controller_arguments, array_keys($uploaded_files))) {
                                $message = sprintf(t::_('The following arguments are present in both the Uploaded Files and the request BODY or PATH: %s.'), print_r(array_values($repeating_arguments), true));
                                $Body = new Structured(['message' => $message]);
                                $Response = new Response(StatusCode::HTTP_BAD_REQUEST, [], $Body);
                                $Response = [$this, $type_handler]($Request, $Response);
                                return $Response;
                            }
                        }
                        $controller_arguments += $uploaded_files;
                    } else {
                        $message = sprintf(t::_('Bad request. Uploading files is supported only for POST method. %s request was received along with %s arguments.'), $Request->getMethod() , count($body_params) );
                        $Body = new Structured( [ 'message' => $message ] );
                        $Response = new Response(StatusCode::HTTP_BAD_REQUEST,[], $Body);
                        $Response = [$this, $type_handler]($Request, $Response);
                        return $Response;
                    }
                }


                //check if controller has init method
                $init_method_exists = \method_exists($controller_callable[0], '_init');
                if ($init_method_exists) {
                    $Response = self::execute_controller_method($controller_callable[0], '_init', $controller_arguments);
                }
                if (empty($Response)) { //if the _init function hasnt returned any response... it may return response due an error, in the normal case the actual action should be invoked
                    $Response = self::execute_controller_method($controller_callable[0], $controller_callable[1], $controller_arguments);
                }

                $Body = $Response->getBody();
                if ($Body instanceof Structured) {
                    //if structured response is returned by the controller and the handler is html_handler, set this to json_handler
                    if ($type_handler === 'html_handler') {
                        Kernel::log(sprintf(t::_('The requested content type is "html" (html_handler) while the response body is Structured. Switching to json_handler.')), LogLevel::NOTICE);
                        $type_handler = 'json_handler';
                    }
                    $Response = [$this, $type_handler]($Request, $Response);
                } else {
                    //return the response as it is - it is already a stream and should contain all the needed headers
                }

                //TODO add cleanup code that unsets all properties set on the child controller
                
                return $Response;
            } elseif (is_object($controller_callable) || is_string($controller_callable)) {
                //Closure or object of class with __invoke or function name
                $Response = $controller_callable($Request);
                return $Response;
//            } elseif (is_string($controller_callable)) {
//                $Response = $controller_callable($Request);
//                return $Response;
            } else {
                throw new LogicException(sprintf(t::_('An unsupported type "%s" for controller_callable encountered.'), gettype($controller_callable)));
            }
        } else {
            //pass the processing to the next handler - usually this will result in the default response
        }

        return $Handler->handle($Request);
    }

    //public function execute_controller_method(ControllerInterface $Controller, string $method, array $controller_arguments) : ?ResponseInterface

    /**
     * @param ActiveRecordController $Controller
     * @param string $method
     * @param array $controller_arguments
     * @return ResponseInterface|null
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function execute_controller_method(ActiveRecordController $Controller, string $method, array $controller_arguments) : ?ResponseInterface
    {

        $Response = NULL;

        try {

            //checks is there a class permission to execute the given action
            $Controller::check_class_permission($method);

            $ordered_arguments = [];
            foreach (self::$controllers_params[get_class($Controller)][$method] as $param) {
                if (isset($controller_arguments[$param['name']])) {
                    $value = $controller_arguments[$param['name']];
                } elseif (array_key_exists('default_value', $param)) {
                    $value = $param['default_value'];
                } else {
                    $message = sprintf(t::_('No value provided for parameter $%s in %s::%s(%s).'), $param['name'], get_class($Controller), $method, (new ReflectionMethod(get_class($Controller), $method))->getParametersList() );
                    throw new InvalidArgumentException($message, 0, NULL, 'fa3a19d3-d001-4afe-8077-9245cea4fa05' );
                }
                if (Reflection::isValidType($param['type'])) {
                    settype($value, $param['type']);
                }
                $ordered_arguments[] = $value;
            }
            //because the Response is immutable it needs to be passed around instead of modified...
            $Event = self::get_service('Events')::create_event($Controller, '_before_'.$method, $ordered_arguments, NULL);
            $ordered_arguments = $Event->get_event_return() ?? $ordered_arguments;
            //no need to do get_response() - instead of that if the execution needs to be interrupted throw InterruptControllerException
            //if (!($Response = $Controller->get_response())) { //if the _before_method events have not produced a response call the method
            $Response = [$Controller, $method](...$ordered_arguments);
            //if ($Response) {
            //    $Controller->set_response($Response);
            //}
            //self::get_service('Events')::create_event($Controller, '_after_'.$method);//these can replace the response too (to append it)
            //$Response = $Controller->get_response();//the _after_ events may have changed the Response
            //}
            //$Response = self::get_service('Events')::create_event($Controller, '_after_'.$method, [], $Response) ?? $Response;//these can replace the response too (to append it)
            $Event = self::get_service('Events')::create_event($Controller, '_after_'.$method, [], $Response);
            $Response = $Event->get_event_return() ?? $Response;

//            //as the events/hooks work only with Structured body and the structure there can be passed by reference there is no need to pass around the response
//            //its bosy->struct can be just changed
//            //of course for any other type of response body this will not work.
//            self::get_service('Events')::create_event($Controller, '_before_'.$method);
//            $Response = [$Controller, $method](...$ordered_arguments);
//            self::get_service('Events')::create_event($Controller, '_after_'.$method);//these can replace the response too (to append it)

        } catch (InterruptControllerException $Exception) {
            $Response = $Exception->getResponse();
            if (Application::get_deployment() === Application::DEPLOYMENT['DEVELOPMENT']) {
                Kernel::exception_handler($Exception);
            }
        } catch (PermissionDeniedException $Exception) {
            $Response = Controller::get_structured_forbidden_response( [ 'message' => $Exception->getMessage() ] ); //dont use getPrettyMessage for generic and expected exceptions as this one
            if (Application::get_deployment() === Application::DEPLOYMENT['DEVELOPMENT']) {
                Kernel::exception_handler($Exception);
            }
        } catch (RecordNotFoundException $Exception) {
            $Response = Controller::get_structured_notfound_response( [ 'message' => $Exception->getMessage() ] );
            if (Application::get_deployment() === Application::DEPLOYMENT['DEVELOPMENT']) {
                Kernel::exception_handler($Exception);
            }
        } catch (InvalidArgumentException | ValidationFailedExceptionInterface $Exception) {
            $Response = Controller::get_structured_badrequest_response(['message' => $Exception->getMessage() ]);
            if (Application::get_deployment() === Application::DEPLOYMENT['DEVELOPMENT']) {
                Kernel::exception_handler($Exception);
            }
        } catch (BaseException $Exception) {
            $Response = Controller::get_structured_servererror_response( [ 'message' => $Exception->getPrettyMessage() ] ); //use getPrettymessage for unexpected exceptions like this one
            Kernel::exception_handler($Exception);//this is an unexpected error - always print the backtrace
        } catch (\Throwable $Exception) {
            $Response = Controller::get_structured_servererror_response( [ 'message' => $Exception->getMessage() ] ); //no getPrettyMessage() is available here
            Kernel::exception_handler($Exception);//this is an unexpected error - always print the backtrace
        } finally {
            //only two exceptions will be passed to the exception_handler() (see above)
//            if (isset($Exception) && !($Exception instanceof InterruptControllerException) ) {
//                Kernel::exception_handler($Exception);
//            }
        }
        return $Response;
    }

    /**
     * @param RequestInterface $Request
     * @param ResponseInterface $Response
     * @return ResponseInterface
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    protected function json_handler(RequestInterface $Request, ResponseInterface $Response) : ResponseInterface
    {
        $StructuredBody = $Response->getBody();
        $structure = $StructuredBody->getStructure();
        //$json_string = json_encode($structure, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $json_string = json_encode($structure, Structured::getJsonFlags());
        $StreamBody = new Stream(NULL, $json_string);
        $Response = $Response->
            withBody($StreamBody)->
            withHeader('Content-Type', ContentType::TYPES_MAP[ContentType::TYPE_JSON]['mime'])->
            withHeader('Content-Length', (string) strlen($json_string));
        return $Response;
    }

    /**
     * Just returns the response as it is.
     * @param RequestInterface $Request
     * @param ResponseInterface $Response
     * @return ResponseInterface
     */
    protected function native_handler(RequestInterface $Request, ResponseInterface $Response): ResponseInterface
    {
        return $Response;
    }

//
//    /**
//     * @param RequestInterface $Request
//     * @param ResponseInterface $Response
//     * @return ResponseInterface
//     * @throws RunTimeException
//     */
//    protected function default_handler(RequestInterface $Request, ResponseInterface $Response) : ResponseInterface
//    {
//        return $this->html_handler($Request, $Response);
//    }

    protected function html_handler(RequestInterface $Request, ResponseInterface $Response): ResponseInterface
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
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    protected function per_controller_php_view_handler(RequestInterface $Request, ResponseInterface $Response): ResponseInterface
    {
        $controller_callable = $Request->getAttribute('controller_callable');
        $content_type = $Request->getContentType();
        //html null and the rest...
        //if the callable is a class and this class is a controller then we can do a lookup for a corresponding view
        //the first element may be a class or an instance so is_a() should be used
        $controller_class = is_string($controller_callable[0]) ? $controller_callable : get_class($controller_callable[0]);
        if (
            is_array($controller_callable)
            && isset($controller_callable[0])
            && is_a($controller_callable[0], Controller::class, TRUE)
            && strpos($controller_class, 'Controllers')
        ) {


            $view_class = str_replace('\\Controllers\\', '\\Views\\', $controller_class);

            if (class_exists($view_class)) {
                if (method_exists($view_class, $controller_callable[1])) {
                    ob_start();
                    [new $view_class($Request), $controller_callable[1]]();
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
                //throw new RunTimeException(sprintf(t::_('Unable to return response from the requested content type %s. A structured body response is returned by controller %s but there is no view.'), $content_type, print_r($controller_callable, TRUE)));
                $controller_str = Controller::get_controller_callable_as_string($controller_callable);
                throw new RunTimeException(sprintf(t::_('Unable to return response from the requested content type %s. A structured body response is returned by controller %s but there is no view.'), $content_type, $controller_str));
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
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
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
