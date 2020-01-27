<?php
declare(strict_types=1);

namespace Guzaba2\Mvc\Interfaces;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface ControllerInterface
{
    public function get_request() : ?RequestInterface ;
    //public function set_response(ResponseInterface $Response) : void ;
    //public function get_response() : ?ResponseInterface ;
    public static function get_routes() : ?iterable ;
    //public function redirect();
}