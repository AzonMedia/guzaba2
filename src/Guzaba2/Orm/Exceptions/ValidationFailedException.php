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

/**
 * This exception is thrown by @see activeRecordValidation::validate() with all the validation errors that were found.
 *
 */
class ValidationFailedException extends BaseException
{
    protected $error_arr = [];

    /**
     * The expected array (@param array $error_arr
     * @param int $code The error code as expected by \Exception $code parameter
     * @since 0.7.1) structure for $error_arr is:
     * $error_arr = [
     *     0 => [ //first error (multiple errors per field can be returned
     *         0 => (string) 'FIELDNAME',
     *         1 => (int) activeRecordBase::V_NOTVALID, //any of the constants as per the activeRecordBase::$validation_map
     *         2 => (string) 'Error description'
     *     ],
     *     1 => [...] //second error
     * ];
     */
    public function __construct(array $error_arr = [], $code = 0)
    {
        $this->error_arr = $error_arr;
        $message = '';
        foreach ($error_arr as $error) {
            //$message .= ' '.$error['message'];
            //changed format since 0.7.1
            $message .= ' ' . $error[2];
        }
        parent::__construct($message, $code);
    }

    /**
     * Returns an associative array with the errors.
     * The key of the array is the field name and the value.
     * This allows only for a single error per field to be returned even if there was more errors for some field in the provided array to the __construct().
     * Only the last error for each field will be returned.
     * @return array
     * @example
     * $ret = [
     *     'field1' => ['code' => 1, 'message' => 'some message' ],
     *     'field2' => [ ... ],
     * ];
     */
    public function getErrors(): array
    {
        //return $this->error_arr;
        //changed format since 0.7.1
        $ret = [];
        foreach ($this->error_arr as $error) {
            $ret[$error[0]] = ['code' => $error[1], 'message' => $error[2]];
        }
        return $ret;
    }

    /**
     * Returns all errors as they were provided in the $error_arr argument to the @return array
     * @see __construct() for example structure
     * @author vesko@azonmedia.com
     * @since 0.7.1
     * @created 12.02.2018
     */
    public function getAllErrors(): array
    {
        return $this->error_arr;
    }

    /**
     * Returns an indexed array with an error message for each field.
     * Please note that only one message per field will be returned (the last one from the $errors_arr provided to the __construct()) even if there were more errors for some field.
     * This methos @return array
     * @uses self::getErrors() to get the messages.
     */
    public function getMessages(): array
    {
        //$messages = array();
        //foreach ($this->error_arr as $error) {
        //    $messages[] = $error['message'];
        //}
        //return $messages;
        $ret = [];
        $errors = $this->getErrors();
        foreach ($errors as $error) {
            $ret[] = $error['message'];//the message
        }
        return $ret;
    }


    /**
     * Returns an indexed array with all error messages that were contained in the $error_arr argument provided to the @return array
     * @see __construct().
     * This array may contain more than one error per field.
     * @author vesko@azonmedia.com
     * @since 0.7.1
     * @created 12.02.2018
     */
    public function getAllMessages(): array
    {
        $ret = [];
        $errors = $this->getAllErrors();
        foreach ($errors as $error) {
            $ret[] = $error[2];//the message
        }
        return $ret;
    }
}
