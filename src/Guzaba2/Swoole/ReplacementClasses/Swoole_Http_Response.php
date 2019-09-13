<?php
namespace Swoole\Http;

class Response
{
    public $fd;
    public $socket;
    public $header;
    public $cookie;
    public $trailer;
    public function initHeader()
    {
    }

    public function cookie($name, $value, $expires, $path, $domain, $secure, $httponly)
    {
    }

    public function setCookie($name, $value, $expires, $path, $domain, $secure, $httponly)
    {
    }

    public function rawcookie($name, $value, $expires, $path, $domain, $secure, $httponly)
    {
    }

    public function status($http_code, $reason)
    {
    }

    public function setStatusCode($http_code, $reason)
    {
    }

    public function header($key, $value, $ucwords)
    {
    }

    public function setHeader($key, $value, $ucwords)
    {
    }

    public function trailer($key, $value)
    {
    }

    public function ping()
    {
    }

    public function write($content)
    {
    }

    public function end($content)
    {
    }

    public function sendfile($filename, $offset, $length)
    {
    }

    public function redirect($location, $http_code)
    {
    }

    public function detach()
    {
    }

    public static function create($fd)
    {
    }

    public function upgrade()
    {
    }

    public function push()
    {
    }

    public function recv()
    {
    }

    public function __destruct()
    {
    }
}
