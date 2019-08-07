<?php

namespace Guzaba2\Base\Exceptions;

class NotImplementedException extends BaseException
{
    public function __construct($message = '', $code = 0, \Exception $exception = null)
    {
        if (!$message) {
            $trace = $this->getTrace();
            $message = sprintf(t::_('%s::%s() is not implemented.'), $trace[0]['class'], $trace[0]['function']);
        }

        parent::__construct($message, $code, $exception);
    }
}