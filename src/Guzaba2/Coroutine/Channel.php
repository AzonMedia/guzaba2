<?php


namespace Guzaba2\Coroutine;


use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Translator\Translator as t;

class Channel extends \Swoole\Coroutine\Channel
{


    public function push( /* mixed */ $data, /* float */ $timeout = NULL) : void
    {
        if (is_object($data) && method_exists($data, '_before_change_context')) {
            $data->_before_change_context();
            $class = get_class($data);
            if (Coroutine::hasData($class)) {
                throw new RunTimeException(sprintf(t::_('An instance of class %s has static data set. This is not allowed when pushing an object between coroutines in a channel. Please unset the static data in %s::_before_change_context().'), $class, $class ));
            }
        }

        parent::push($data, $timeout);
    }

    public function pop(/* float */ $timeout = NULL) /* mixed */
    {
        $data = parent::pop($timeout);
        if (is_object($data) && method_exists($data, '_after_change_context')) {
            $data->_after_change_context();
        }
        return $data;
    }

}