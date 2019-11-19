<?php
namespace Guzaba2\Mvc\Interfaces;
use Psr\Http\Message\RequestInterface;
interface ControllerInterface
{
    public function get_request() : RequestInterface ;
    public static function get_routes() : ?iterable ;
    //public function redirect();
}