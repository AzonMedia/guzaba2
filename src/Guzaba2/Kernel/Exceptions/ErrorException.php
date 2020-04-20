<?php
declare(strict_types=1);


/**
 * Guzaba Framework 2
 * http://framework2.guzaba.org
 *
 * This source file is subject to the BSD license that is bundled with this
 * package in the file LICENSE.txt and available also at:
 * http://www.opensource.org/licenses/bsd-license.php
 *
 * @category    Guzaba2 Framework
 * @package        Kernel
 * @subpackage      Exceptions
 * @license        http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author        Veselin Kenashkov <kenashkov@azonmedia.com>
 */

namespace Guzaba2\Kernel\Exceptions;

/**
 * Exception thrown by the error handler. It must be thrown only by it and nothing else (there is a check in the constructor for that).
 * It is used to convert all the php errors into exceptions.
 * This class can no longer be final (no exception class can be) because in baseException::clone_exception() we create instances of the exceptions with Reflection without invoking the constructor and when the class if final this is not allowed.
 */
class ErrorException extends \Guzaba2\Base\Exceptions\BaseException
{
    protected $errno;
    protected $errfile;
    protected $errline;
    protected $errcontext;

    public function __construct($errno = '', $errstr = '', $errfile = '', $errline = '', $errcontext = '')
    {

        parent::__construct($errstr);

        $this->errfile = $errfile;

        $this->errline = $errline;
        $this->errno = $errno;//severity in \ErrorException
        $this->errcontext = $errcontext;//no corresponding var in \ErrorException

        //override the internal exception structure
        $this->file = $errfile;
        $this->line = $errline;
    }

    public function getErrorNo()
    {
        return $this->errno;
    }

    public function getErrorFile()
    {
        return $this->errfile;
    }

    public function getErrorLine()
    {
        return $this->errline;
    }

    public function getErrorContext()
    {
        return $this->errcontext;
    }
}
