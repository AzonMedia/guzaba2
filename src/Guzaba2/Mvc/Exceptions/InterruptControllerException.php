<?php

declare(strict_types=1);

namespace Guzaba2\Mvc\Exceptions;

use Guzaba2\Base\Exceptions\BaseException;
use Guzaba2\Translator\Translator as t;
use Psr\Http\Message\ResponseInterface;

class InterruptControllerException extends BaseException
{

    protected ResponseInterface $Response;

    public function __construct(ResponseInterface $Response)
    {
        $this->Response = $Response;
        $message = sprintf(t::_('This is not a real exception but just an execution interrupt.'));
        $code = 0;
        $previous = null;
        parent::__construct($message, $code, $previous);
    }

    public function getResponse(): ResponseInterface
    {
        return $this->Response;
    }

    public function get_response(): ResponseInterface
    {
        return $this->getResponse();
    }
}
