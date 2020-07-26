<?php

declare(strict_types=1);

namespace Guzaba2\Orm\Exceptions;

use Guzaba2\Base\Exceptions\BaseException;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Orm\Interfaces\ValidationFailedExceptionInterface;

/**
 * This exception is thrown by @see ActiveRecord::validate() with all the validation errors that were found.
 * The provided ValidationFailedExceptions to the constructor may have different targets (if for example more than one ActiveRecord instance is being validated)
 */
class MultipleValidationFailedException extends BaseException implements ValidationFailedExceptionInterface, \Iterator, \Countable
{
    /**
     * @var ValidationFailedExceptionInterface[]
     */
    protected array $validation_exceptions = [];

    /**
     * ValidationFailedException constructor.
     * @param array $validation_exceptions An array of ValidationExceptions
     * @param int $code
     * @param \Exception|null $Exception
     * @throws InvalidArgumentException
     * @throws \ReflectionException
     */
    public function __construct(array $validation_exceptions, int $code = 0, ?\Exception $Exception = null)
    {
        self::check_validation_exceptions($validation_exceptions);
        $this->validation_exceptions = $validation_exceptions;
        $messages = $this->getMessages();

        //parent::__construct(implode(' ', $messages), $code, $Exception);
        parent::__construct(implode(PHP_EOL, $messages), $code, $Exception);
    }

    /**
     * Returns an indexed array with the error messages.
     * @return array
     */
    public function getMessages(): array
    {
        $messages = [];
        foreach ($this->validation_exceptions as $ValidationException) {
            $messages[] = $ValidationException->getMessage();
        }
        return $messages;
    }

    /**
     * Returns an indexed array of ValidationException
     * @return array
     */
    public function getExceptions(): array
    {
        return $this->validation_exceptions;
    }

    /**
     * Returns a two-dimensional array with targets & fields
     * @return array
     */
    public function getTargets(): array
    {
        $targets = [];
        foreach ($this->validation_exceptions as $ValidationException) {
            $targets[] = [$ValidationException->getTarget(), $ValidationException->getField()];
        }
        return $targets;
    }

    /**
     * Checks if the provided $validation_errors array conforming to the expected structure.
     * @param array $validation_exceptions
     * @throws InvalidArgumentException
     */
    private static function check_validation_exceptions(array $validation_exceptions): void
    {
        if (!count($validation_exceptions)) {
            throw new InvalidArgumentException(sprintf(t::_('No ValidationFailedExceptions provided.')));
        }
        if (array_keys($validation_exceptions) !== range(0, count($validation_exceptions) - 1)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided $validation_exceptions array it not an indexed array.')));
        }

        foreach ($validation_exceptions as $ValidationException) {
            if (! ($ValidationException instanceof ValidationFailedException)) {
                throw new InvalidArgumentException(sprintf(t::_('An element of the provided $validation_exceptions is not an instance of %s.'), ValidationFailedException::class));
            }
        }
    }

    /**
     * Return the current element
     * @link https://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0.0
     */
    public function current()
    {
        return current($this->validation_exceptions);
    }

    /**
     * Move forward to next element
     * @link https://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function next()
    {
        next($this->validation_exceptions);
    }

    /**
     * Return the key of the current element
     * @link https://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key()
    {
        return key($this->validation_exceptions);
    }

    /**
     * Checks if current position is valid
     * @link https://php.net/manual/en/iterator.valid.php
     * @return bool The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid()
    {
        return $this->current() !== false;
    }

    /**
     * Rewind the Iterator to the first element
     * @link https://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind()
    {
        reset($this->validation_exceptions);
    }

    /**
     * Count elements of an object
     * @link https://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count()
    {
        return count($this->validation_exceptions);
    }
}
