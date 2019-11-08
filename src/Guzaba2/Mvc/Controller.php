<?php


namespace Guzaba2\Mvc;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Http\Body\Stream;
use Guzaba2\Http\Body\Structured;
use Guzaba2\Http\Body\Str;
use Guzaba2\Http\Response;
use Guzaba2\Http\StatusCode;
use Guzaba2\Mvc\Interfaces\ControllerInterface;
use Guzaba2\Mvc\Exceptions\InterruptControllerException;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Translator\Translator as t;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;


/**
 * Class Controller
 * A base class representing a controller. All controllers should inherit this class.
 * TODO add execute event
 * @package Guzaba2\Mvc
 */
//abstract class Controller extends ActiveRecord
abstract class Controller extends Base
implements ControllerInterface
{

//    protected const CONFIG_DEFAULTS = [
//        'services'      => [
//            'AuthorizationProvider',
//        ],
//
//    ];

    protected const CONFIG_DEFAULTS = [
        //confusing
        //'store_class'       =>  \Guzaba2\Orm\Store\BlankStore::class,//means that the controllers are not stored in the DB and are not using permissions
        //'store_class'     => NULL,//means the controllers are stored in the defined store of Orm
        //'store_class'       => 'some_class',//a custom storage for the controllers

        'store_service'       => NULL,//means no storage for the controllers, internally \Guzaba2\Orm\Store\BlankStore::class is used
        //'store_service'       => 'OrmStore',//use the standard OrmStore
        //'store_service'         => 'AnotherService',//the controllers can be stored in different storage service
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * @var RequestInterface
     */
    private $Request;

    /**
     * Controller constructor.
     * @param RequestInterface $Request
     */
    public function __construct(RequestInterface $Request)
    {
        parent::__construct();
//        $store_service = 'BlankOrmStore';
//        if (self::CONFIG_RUNTIME['store_service'] === NULL) {
//
//        }
        //parent::__construct( ['controller_class' => get_class($this)] );
        $this->Request = $Request;

    }


    /**
     * @return RequestInterface
     */
    public function get_request() : RequestInterface
    {
        return $this->Request;
    }

    /**
     * May be overriden by a child class to provide routing set in an external source like database.
     * Or suppress certain routes based on permissions.
     * This will allow for the routes to be changed without code modification.
     * @return iterable|null
     */
    public static function get_routes() : ?iterable
    {
        $ret = NULL;
        if (defined('static::ROUTES')) {
            //validate the routes
            foreach (static::ROUTES as $route => $route_data) {
                if ($route[0] !== '/') {
                    throw new RunTimeException(sprintf(t::_('The route "%s" of Controller class %s seems wrong. All routes must begin with "/".'), $route, get_called_class() ));
                }
            }
            $ret = static::ROUTES;
        }
        return $ret;
    }

    public function exit_with_badrequest(array $structure = [])
    {
        $Request = self::get_structured_badrequest_response($structure);
        throw new InterruptControllerException($Request);
    }

    /**
     * Factory for creating HTTP
     * @return ResponseInterface
     */
    public static function get_structured_ok_response(array $structure = []) : ResponseInterface
    {
//        if (!$structure) {
//            throw new InvalidArgumentException(sprintf(t::_('It is required to provide structure to the response.')));
//        }
        $Response = new Response(StatusCode::HTTP_OK, [], new Structured($structure));
        return $Response;
    }

    public static function get_stream_ok_response(string $content) : ResponseInterface
    {
        $Response = new Response(StatusCode::HTTP_OK, [], new Stream(NULL, $content));
        return $Response;
    }

    public static function get_string_ok_response(string $content) : ResponseInterface
    {
        $Response = new Response(StatusCode::HTTP_OK, [], new Str($content));
        return $Response;
    }



    public static function get_structured_notfound_response(array $structure = []) : ResponseInterface
    {
        if (!$structure) {
            $structure['message'] = sprintf(t::_('The requested resource does not exist.'));
        }
        $Response = new Response(StatusCode::HTTP_NOT_FOUND, [], new Structured($structure));
        return $Response;
    }

    public static function get_structured_badrequest_response(array $structure = []) : ResponseInterface
    {
//        if (!$structure) {
//            throw new InvalidArgumentException(sprintf(t::_('It is required to provide structure to the response.')));
//        }
        $Response = new Response(StatusCode::HTTP_BAD_REQUEST, [], new Structured($structure));
        return $Response;
    }

    public static function get_structured_unauthorized_response(array $structure = []) : ResponseInterface
    {
        if (!$structure) {
            $structure['message'] = sprintf(t::_('You are not allowed to access the requested resource.'));
        }
        $Response = new Response(StatusCode::HTTP_UNAUTHORIZED, [], new Structured($structure));
        return $Response;
    }
}
