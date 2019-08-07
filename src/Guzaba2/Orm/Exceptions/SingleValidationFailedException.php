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

use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Translator\Translator as t;

/**
 * This exception is to be used when only a single error needs to be thrown (instead of using the validationFailedException and construct an array).
 *
 *
 */
class SingleValidationFailedException extends ValidationFailedException
{
    /**
     *
     * @param string $field_name The field name / property as found in the ORM class / database.
     * @param int $error_code Validation constant as found in activeRecordVase::$validation_map
     * @param string $error_message A descriptive error message
     * @param int $exception_code Corresponds to the $code argument to \Exception
     * @throws InvalidArgumentException
     */
    public function __construct(string $field_name, int $error_code, string $error_message, int $exception_code = 0)
    {
        $error_arr = [];
        if (!$field_name) {
            throw new InvalidArgumentException(sprintf(t::_('"%s" first argument $field_name must have value.'), __METHOD__));
        }
        if (!$error_code) {
            throw new InvalidArgumentException(sprintf(t::_('"%s" second argument $error_code must have value.'), __METHOD__));
        } elseif (!isset(ActiveRecord::$validation_map[$error_code])) {
            throw new InvalidArgumentException(sprintf(t::_('"%s" second argument $error_code has an invalid value. Please see activeRecordBase::$validation_map for the acceptable value listed there as keys (constants).'), __METHOD__));
        }
        if (!$error_message) {
            throw new InvalidArgumentException(sprintf(t::_('"%s" first argument $error_message must have value.'), __METHOD__));
        }

        //$error_arr[$field_name] = array('code'=>$error_code,'message'=>$error_message);
        //since 0.7.1 the format has changed
        //@see validationFailedException::__construct()
        $error_arr = [0 => [0 => $field_name, 1 => $error_code, 2 => $error_message]];
        //$error_arr = [[$field_name, $error_code, $error_message]];//same as above but for clarity leave the above
        parent::__construct($error_arr, $exception_code);
    }
}
