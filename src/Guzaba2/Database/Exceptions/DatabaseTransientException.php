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
 * @category    Guzaba Framework
 * @package    Object-Relational Mapping
 * @subpackage    Exceptions
 * @copyright    Copyright (c) Guzaba Ltd - http://guzaba.com
 * @license    http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author    Vesselin Kenashkov <vesko@webstudiobulgaria.com>
 */

namespace Guzaba2\Database\Exceptions;

/**
 * This exception is to be thrown when the error returned from the DB is not a permanent one like a deadlock. The deadlock error can be solved by just retrying the transaction and is not a permanent one.
 */
class DatabaseTransientException extends QueryException
{
}
