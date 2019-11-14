<?php
declare(strict_types=1);
/*
 * Guzaba Framework
 * http://framework.guzaba.org
 *
 * This source file is subject to the BSD license that is bundled with this
 * package in the file LICENSE.txt and available also at:
 * http://www.opensource.org/licenses/bsd-license.php
 */

/**
 * Description of validationFailedException
 * @category    Guzaba Framework
 * @package        Object-Relational-Mapping
 * @subpackage    Exceptions
 * @copyright    Copyright (c) Guzaba Ltd - http://guzaba.com
 * @license        http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author        Vesselin Kenashkov <vesko@webstudiobulgaria.com>
 */

namespace Guzaba2\Orm\Exceptions;

use Guzaba2\Base\Exceptions\BaseException;
use Guzaba2\Base\Exceptions\InvalidArgumentException;

/**
 * This exception is thrown by @see ActiveRecord::validate() with all the validation errors that were found.
 *
 */
class MultipleValidationFailedException extends ValidationFailedException
{
    /**
     * Array of ValidationException
     * @var array
     */
    protected array $validation_exceptions = [];

    /**
     * ValidationFailedException constructor.
     * @param array $validation_exceptions An array of ValidationExceptions
     * @param int $code
     */
    public function __construct(array $validation_exceptions, int $code = 0, ?\Exception $Exception = NULL)
    {
        self::check_validation_exceptions($validation_exceptions);
        $this->validation_exceptions = $validation_exceptions;
        $messages = $this->get_messages();

        parent::__construct(implode(' ', $messages), $code, $Exception);
    }

    /**
     * Returns an indexed array with the error messages.
     * @return array
     */
    public function get_messages() : array
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
    public function get_validation_exceptions() : array
    {
        return $this->validation_exceptions;
    }

    /**
     * Checks if the provided $validation_errors array conforming to the expected structure
     * @param array $validation_errors
     * @throws InvalidArgumentException
     */
    public static function check_validation_exceptions(array $validation_exceptions) : void
    {
        if ( array_keys($arr) !== range(0, count($arr) - 1) ) {
            throw new InvalidArgumentException(sprintf(t::_('The provided $validation_exceptions array it not an indexed array.')));
        }
        foreach ($validation_exceptions as $ValidationException) {
            if (! ($ValidationException instanceof ValidationFailedException) ) {
                throw new InvalidArgumentException(sprintf(t::_('An element of the provided $validation_exceptions is not an instance of %s.'), ValidationFailedException::class));
            }
        }
    }
}
