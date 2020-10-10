<?php

namespace Guzaba2\Kernel\Exceptions;

use Guzaba2\Base\Exceptions\BaseException;

/**
 * Class PhpErrorException
 * @package Guzaba2\Kernel\Exceptions
 *
 * Unlinke any other exception this one requires $Previous exception to be provided and this needs to be an \Error one.
 */
class PhpErrorException extends BaseException
{

    /**
     * PhpErrorException constructor.
     * @param string $message
     * @param int $code
     * @param \Error $Previous
     * @param string|null $uuid
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public function __construct(string $message, int $code, \Error $Previous, ?string $uuid = null)
    {
        parent::__construct($message, $code, $Previous, $uuid);
    }
}