<?php

declare(strict_types=1);

namespace Guzaba2\Http;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Translator\Translator as t;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Class Middlewares
 * @package GuzabaPlatform\Platform\Application
 * A service that provides access to inject middlewares by the components
 */
class Middlewares extends Base implements \Iterator, \Countable
{
    private iterable $middlewares = [];

    public function __construct(MiddlewareInterface ...$middlewares)
    {
        $this->add_multiple(...$middlewares);
    }

    /**
     * @return iterable
     */
    public function get_middlewares(): iterable
    {
        return $this->middlewares;
    }

    public function add_multiple(MiddlewareInterface ...$middlewares): void
    {
        foreach ($middlewares as $Middleware) {
            $this->add($Middleware);
        }
    }

    /**
     * If no BeforeMiddleware is provided then the new Middleware will be appended.
     * Returns FALSE if the Middleware being added is already added.
     * If the BeforeMiddleware it not added already an RunTimeException will be thrown.
     * @param MiddlewareInterface $Middleware
     * @param null $BeforeMiddleware
     * @return bool
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public function add(MiddlewareInterface $Middleware, /* MiddlewareInterface|string|null */ $BeforeMiddleware = null): bool
    {
        if ($this->has_middleware($Middleware)) {
            return false;
        }
        if ($BeforeMiddleware) {
            if (!$this->has_middleware($BeforeMiddleware)) {
                throw new RunTimeException(sprintf(t::_('The $BeforeMiddleware instance of class %s is not found in the middlewares list.'), is_object($BeforeMiddleware) ? get_class($BeforeMiddleware) : $BeforeMiddleware));
            }
            $middlewares = [];
            if (is_string($BeforeMiddleware)) {
                foreach ($this->middlewares as $AddedMiddleware) {
                    if (get_class($AddedMiddleware) === $BeforeMiddleware) {
                        $middlewares[] = $Middleware;
                    }
                    $middlewares[] = $AddedMiddleware;
                }
            } elseif ($BeforeMiddleware instanceof MiddlewareInterface) {
                foreach ($this->middlewares as $AddedMiddleware) {
                    if ($AddedMiddleware === $BeforeMiddleware) {
                        $middlewares[] = $Middleware;
                    }
                    $middlewares[] = $AddedMiddleware;
                }
            } else {
                throw new InvalidArgumentException(sprintf(t::_('An unsupported type %s was provided to $BeforeMiddleware argument of %s().'), gettype($BeforeMiddleware), __METHOD__));
            }
            $this->middlewares = $middlewares;
        } else {
            $this->middlewares[] = $Middleware;
        }
        return true;
    }

    public function remove(/* MiddlewareInterface|string */ $Middleware): bool
    {

        if (is_string($Middleware)) {
            if (!class_exists($Middleware)) {
                throw new InvalidArgumentException(sprintf(t::_('The provided middleware class %s does not exist.'), $Middleware));
            }
        } elseif (is_object($Middleware)) {
            if (!($Middleware instanceof MiddlewareInterface)) {
                throw new InvalidArgumentException(sprintf(t::_('The provided middleware instance of class %s must implement the %s interface.'), get_class($Middleware), MiddlewareInterface::class));
            }
        } else {
            throw new InvalidArgumentException(sprintf(t::_('An unsupported type %s was provided to $BeforeMiddleware argument of %s().'), gettype($Middleware), __METHOD__));
        }

        $ret = false;

        foreach ($this->middlwares as $key => $RegisteredMiddleware) {
            if (is_string($Middleware)) {
                if ($RegisteredMiddleware instanceof $Middleware) {
                    unset($this->middlewares[$key]);
                    $this->middlewares = array_values($this->middlewares);
                    $ret = true;
                    break;
                }
            } elseif (is_object($Middleware)) {
                if ($Middleware === $RegisteredMiddleware) {
                    unset($this->middlewares[$key]);
                    $this->middlewares = array_values($this->middlewares);
                    $ret = true;
                    break;
                }
            } else {
                //future use
            }
        }
        return $ret;
    }

    /**
     * @param string|MiddlewareInterface $Middleware
     * @return bool
     * @throws InvalidArgumentException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function has_middleware(/* MiddlewareInterface|string */ $Middleware): bool
    {
        $ret = false;
        if (is_string($Middleware)) {
            if (!$Middleware) {
                throw new InvalidArgumentException(sprintf(t::_('No middleware class provided to method %s().'), __METHOD__));
            }
            if (!class_exists($Middleware)) {
                throw new InvalidArgumentException(sprintf(t::_('The provided $Middleware argument %s to method %s() does not contain an existing class name.'), $Middleware, __METHOD__));
            }
            if (!is_a($Middleware, MiddlewareInterface::class, true)) {
                throw new InvalidArgumentException(sprintf(t::_('The provided $Middleware argument %s to method %s() does not implement %s.'), $Middleware, __METHOD__, MiddlewareInterface::class));
            }

            foreach ($this->middlewares as $AddedMiddleware) {
                if ($Middleware === get_class($AddedMiddleware)) {
                    $ret = true;
                }
            }
        } elseif ($Middleware instanceof MiddlewareInterface) {
            foreach ($this->middlewares as $AddedMiddleware) {
                if ($Middleware === $AddedMiddleware) {
                    $ret = true;
                }
            }
        } else {
            throw new InvalidArgumentException(sprintf(t::_('An unsupported type %s was provided to $Middleware argument of %s().'), gettype($BeforeMiddleware), __METHOD__));
        }

        return $ret;
    }

    /**
     * Returns the first middleware that matches the provided $middleware_class.
     * @param string $middleware_class
     * @return MiddlewareInterface|null
     * @throws InvalidArgumentException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function get_middleware(string $middleware_class): ?MiddlewareInterface
    {
        if (!$middleware_class) {
            throw new InvalidArgumentException(sprintf(t::_('No middleware class provided to method %s().'), __METHOD__));
        }
        if (!class_exists($middleware_class)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided $Middleware argument %s to method %s() does not contain an existing class name.'), $middleware_class, __METHOD__));
        }
        $ret = null;
        foreach ($this->middlewares as $Middleware) {
            if ($Middleware instanceof $middleware_class) {
                $ret = $Middleware;
                break;
            }
        }
        return $ret;
    }

    public function current()
    {
        return current($this->middlewares);
    }

    public function next()
    {
        next($this->middlewares);
    }

    public function key()
    {
        return key($this->middlewares);
    }

    public function valid()
    {
        return $this->current() !== false;
    }

    public function rewind()
    {
        reset($this->middlewares);
    }

    public function count() /* int */
    {
        return count($this->middlewares);
    }
}
