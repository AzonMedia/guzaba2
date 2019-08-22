<?php


namespace Guzaba2\Mvc;

use Guzaba2\Base\Base;
use Guzaba2\Http\Body\Stream;
use Guzaba2\Http\Body\Structured;
use Guzaba2\Http\Body\Str;
use Guzaba2\Http\Response;
use Guzaba2\Http\StatusCode;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Controller
 * A base class representing a controller. All controllers should inherit this class.
 * @package Guzaba2\Mvc
 */
abstract class Controller extends Base
{

    /**
     * @var RequestInterface
     */
    private $Request;

    public function __construct(RequestInterface $Request)
    {
        parent::__construct();
        $this->Request = $Request;
    }

    public function get_request() : RequestInterface
    {
        return $this->Request;
    }

    /**
     * Factory for creating HTTP
     * @return ResponseInterface
     */
    public static function get_structured_ok_response(array $structure = []) : ResponseInterface
    {
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
}
