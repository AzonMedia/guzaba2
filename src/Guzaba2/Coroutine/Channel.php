<?php
declare(strict_types=1);


namespace Guzaba2\Coroutine;

use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Translator\Translator as t;

class Channel extends \Swoole\Coroutine\Channel
{
    public function push(/* mixed */ $data, /* float */ $timeout = 0) : void
    {
        if (is_object($data) && method_exists($data, '_before_change_context')) {
            $data->_before_change_context();
            $class = get_class($data);
        }

        parent::push($data, $timeout);
    }

    public function pop(/* float */ $timeout = 0) /* mixed */
    {
        $data = parent::pop($timeout);
        if (is_object($data) && method_exists($data, '_after_change_context')) {
            $data->_after_change_context();
        }
        return $data;
    }
}
