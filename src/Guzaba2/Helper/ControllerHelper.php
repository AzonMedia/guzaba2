<?php


namespace Guzaba2\Helper;

use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Translator\Translator as t;

class ControllerHelper
{
    /**
     * Returns a md5 hash for the callable (the different types of callables are hashed differently).
     * Does not validate is it really a valid callable but only treats the provided argument as callable and hashes it.
     * Because of this the signature is not enforced to callable
     *
     * @param callable $callable
     * @return string md5 hash
     *
     * @throws InvalidArgumentException
     * @since 0.7.7.1
     * @author vesko@azonmedia.com
     * @created 20.03.2019
     */
    public static function get_callable_hash(callable $callable): string
    {
        if (is_string($callable)) {
            $hash = md5($callable);
        } elseif (is_array($callable)) {
            if (count($callable) != 2) {
                throw new InvalidArgumentException(sprintf(t::_('An array is provided as a callable but the array contains %s elements instead of 2.'), count($callable)));
            }
            if (is_object($callable[0])) {
                $hash = md5(spl_object_hash($callable[0]) . $callable[1]);
                //NOTE - if the object gets destroyed its spl object hash may get reused by another!
            } elseif (is_string($callable[0])) {
                $hash = md5($callable[0] . $callable[1]);
            } else {
                throw new InvalidArgumentException(sprintf(t::_('The first element of the callable array is not a string or object but a "%s".'), gettype($callable[0])));
            }
        } elseif (is_object($callable)) {
            $hash = md5(spl_object_hash($callable));
            //NOTE - if the object gets destroyed its spl object hash may get reused by another!
        } else {
            throw new InvalidArgumentException(sprintf(t::_('The provided argument doesnt seem to be a valid callable. It is of type "%s".'), gettype($callable)));
        }
        return $hash;
    }
}