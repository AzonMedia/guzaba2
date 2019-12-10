<?php
declare(strict_types=1);

namespace Guzaba2\Mvc\Traits;

use Guzaba2\Http\Body\Str;
use Guzaba2\Http\Body\Stream;
use Guzaba2\Http\Body\Structured;
use Guzaba2\Http\Response;
use Guzaba2\Http\StatusCode;
use Guzaba2\Mvc\Exceptions\InterruptControllerException;
use Guzaba2\Translator\Translator as t;
use Psr\Http\Message\ResponseInterface;

/**
 * Trait ControllerHelpers
 * @package Guzaba2\Mvc\Traits
 * Contains factory methods for easier creation of Responses
 */
trait ResponseFactories
{

    public function exit_with_badrequest(array $structure = [])
    {
        $Request = self::get_structured_badrequest_response($structure);
        throw new InterruptControllerException($Request);
    }

    /**
     * Factory for creating HTTP
     * @return ResponseInterface
     */
    public static function get_structured_ok_response(iterable $structure = []) : ResponseInterface
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

    public static function get_structured_forbidden_response(array $structure = []) : ResponseInterface
    {
        if (!$structure) {
            $structure['message'] = sprintf(t::_('You are not allowed to perform the requested action.'));
        }
        $Response = new Response(StatusCode::HTTP_FORBIDDEN, [], new Structured($structure));
        return $Response;
    }

    public static function get_structured_unauthorized_response(array $structure = []) : ResponseInterface
    {
        if (!$structure) {
            $structure['message'] = sprintf(t::_('The requested action requires authentication.'));
        }
        $Response = new Response(StatusCode::HTTP_UNAUTHORIZED, [], new Structured($structure));
        return $Response;
    }
}