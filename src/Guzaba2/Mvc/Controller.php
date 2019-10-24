<?php


namespace Guzaba2\Mvc;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Http\Body\Stream;
use Guzaba2\Http\Body\Structured;
use Guzaba2\Http\Body\Str;
use Guzaba2\Http\Response;
use Guzaba2\Http\StatusCode;
use Guzaba2\Mvc\Interfaces\ControllerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Controller
 * A base class representing a controller. All controllers should inherit this class.
 * TODO add execute event
 * @package Guzaba2\Mvc
 */
abstract class Controller extends Base
implements ControllerInterface
{
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
     * This will allow for the routes to be changed without code modification.
     * @return iterable|null
     */
    public static function get_routes() : ?iterable
    {
        $ret = NULL;
        if (defined('static::ROUTES')) {
            $ret = static::ROUTES;
        }
        return $ret;
    }

    //throws
    //public function redirect() {}

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
