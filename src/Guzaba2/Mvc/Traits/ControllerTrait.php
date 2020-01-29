<?php
declare(strict_types=1);

namespace Guzaba2\Mvc\Traits;


use Azonmedia\Reflection\ReflectionClass;
use Psr\Http\Message\RequestInterface;

trait ControllerTrait
{

    /**
     * @return RequestInterface
     */
    public function get_request() : ?RequestInterface
    {
        return $this->Request;
    }

    /**
     * @return array
     * @throws \ReflectionException
     */
    public static function get_actions() : array
    {
        $ret = [];
        $class = get_called_class();
        $RClass = new ReflectionClass($class);
        foreach ($RClass->getOwnMethods(\ReflectionMethod::IS_PUBLIC) as $RMethod) {
            if (!$RMethod->isStatic() && $RMethod->getName()[0] !== '_') {
                $ret[] = $RMethod->getName();
            }
        }
        return $ret;
    }
}