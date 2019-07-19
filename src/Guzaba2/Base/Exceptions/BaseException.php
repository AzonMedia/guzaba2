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
 * @package        Base
 * @license        http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author        Veselin Kenashkov <kenashkov@azonmedia.com>
 */

namespace Guzaba2\Base\Exceptions;

use Guzaba2\Base\Traits\SupportsObjectInternalId;
use Guzaba2\Base\Traits\StaticStore;
use Guzaba2\Coroutine\Coroutine;
use Throwable;

/**
 * Class BaseException
 * All exceptions inherit this one
 *
 * Extends the \Exception class. There is no new functionality added, but clean up & security related code required by the framework.
 *
 * When an exception is created is it being cloned and stored in @see self::$current_exception as this is needed for the transactions to retrieve it using self::get_interrupting_exception()
 * This (cloned) exception self::$current_exception is automatically removed by self::__destruct().
 * The reason why not a reference to the originally created/thrown exception is not stored in self::$current_exception = $this is that this will prevent the exception to be destroyed at the end of the scope.
 * This makes the self::$current_exception wrong and then the transactions may report a wrong reason for being interrupted (for example a transaction interrupted by return may report that was interrupted by an exception).
 * Having a second exception avoind the need of a second reference to the current one and the current one clears $current_exception and destroys the cloned exception when the current one is destroyed.
 * @see self::clone_exception()
 * @see self::setProperty()
 * @see self::get_current_exception()
 * @see Transactions\transaction::get_interrupting_exception()
 */
abstract class BaseException extends \Exception
{

    use SupportsObjectInternalId;
    use StaticStore;

    //TODO - rework this to be coroutine aware - there can be multiple interrupting exceptions in the various routines
    /**
     * @var \Throwable
     */
    //private static $CurrentException = NULL;

    /**
     * @var \Guzaba2\Transactions\Transaction
     */
    private $InterruptedTransaction = NULL;

    //private static $is_in_clone_flag = FALSE;

    private $is_cloned_flag = FALSE;

    // properties that contain additional debug info follow
    /**
     * The execution ID @see kernel::get_execution_id()
     * @var int
     */
    protected $execution_id = 0;

    /**
     * How much time was spent until this exception was created
     * @var float
     */
    protected $execution_time = 0;


    protected $execution_details = [
        'temporary_roles' => [] ,
        'temporary_privileges' => [],
        'subject_id' => 0,
        'active_environment_vars' => []
    ];

    protected $executionContext = NULL;

    protected $session_subject_id = 0;//this may be different from the execution context subject_id (may be temporarily substituted)

    protected $time_created = 0;
    protected $microtime_created = 0;

    /**
     *
     * @var int
     */
    protected $memory_usage = 0;

    /**
     *
     * @var int
     */
    protected $real_memory_usage = 0;

    /**
     *
     * @var int
     */
    protected $peak_memory_usage = 0;

    /**
     *
     * @var int
     */
    protected $real_peak_memory_usage = 0;

    protected $load_average = [];

    protected $is_framework_exception_flag = FALSE;



    /**
     * The constructor calls first the constructor of tha parent class and then its own code.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = '', int $code = 0, \Throwable $previous = NULL)
    {

        parent::__construct($message, $code, $previous);
        $this->set_object_internal_id();

        //self::$executionProfile->increment_value('cnt_guzaba_exceptions_created', 1);

        if (is_object($code)) { //because of a bug in the logic there could be a case where an object was supplied instead of integer
            $code = 0;
        }
        $code = (int) $code;//there was an error that provides code as float and this triggers a fatal error "Wrong parameters for Exception([string $exception [, long $code [, Exception $previous = NULL]]])"
        //$message = (string) $message;
        parent::__construct($message,$code,$previous);

        list($usec, $sec) = explode(" ", microtime());
        $this->time_created = (int) $sec;
        $this->microtime_created = ( (float)$usec + (float)$sec ) ;


        //TODO - reenable the below code
        //$db = framework\database\classes\connection1::get_instance();
        //$this->InterruptedTransaction = $db->get_current_transaction();

        //self::$current_exception = clone $this;//exceptions cant be cloned
        //some exceptions must not be cloned
        //if ( ! self::$is_in_clone_flag ) {
        //    self::$is_in_clone_flag = TRUE;
        //there is no risk of recursion when cloning the exception as the cloned exception is created without invoking its constructor
            //self::$CurrentException = $this->cloneException();//if this was a static method and $this is passed then $this does not get destroyed when expected!!! This does not seem to be related to the Reflection but to the fact that $this is passed (even if this was a dynamic method still fails)
        self::set_static('CurrentException', $this->cloneException());

        //    self::$is_in_clone_flag = FALSE;
        //}


//        $this->session_subject_id = framework\session\classes\sessionSubject::get_instance()->get_index();
//        $this->executionContext = framework\kernel\classes\activeExecutionContext::get_instance()->get();
//        if ($this->executionContext) {
//            $this->execution_details = [
//                'temporary_roles'               => $this->executionContext->getRoles() ,
//                'temporary_privileges'          => $this->executionContext->getPrivileges(),
//                'subject_id'                    => $this->executionContext->getSubject()->get_index(),
//                'active_environment_vars'       => $this->executionContext->getEnvironment()->get_vars(),
//            ];
//        } else {
//            $sessionSubject = framework\session\classes\sessionSubject::get_instance();
//            $activeEnvironment = framework\mvc\classes\activeEnvironment::get_instance()->get();
//            $this->execution_details = [
//                'temporary_roles'               => $sessionSubject->get_temporary_roles(),
//                'temporary_privileges'          => $sessionSubject->get_temporary_privileges(),
//                'subject_id'                    => $sessionSubject->get_index(),
//                'active_environment_vars'       => $activeEnvironment ? $activeEnvironment->get_vars() : framework\init\classes\vars::get_instance()->get_vars(),
//            ];
//        }
//
//        //number_format(memory_get_usage() / 1024 / 1024, 2) . ' MiB',
//        $this->memory_usage = memory_get_usage();
//        $this->real_memory_usage = memory_get_usage(TRUE);
//        $this->peak_memory_usage = memory_get_peak_usage();
//        $this->real_peak_memory_usage = memory_get_peak_usage(TRUE);
//        $this->load_average = sys_getloadavg();//array with three samples (last 1, 5 and 15 minutes)
//        $this->execution_id = k::get_execution_id();
//        $this->execution_time = microtime(true) - k::get_execution()->get_start_microtime();
//
//        $trace = $this->getTrace();
//        if (isset($trace[0])) {
//            $last_stack_frame = $trace[0];
//            if (isset($last_stack_frame['class']) && k::is_framework_class($last_stack_frame['class']) ) {
//                $this->is_framework_exception_flag = TRUE;
//            }
//        }

    }

    public function getDebugData() {
        $ret =
            time().' '.date('Y-m-d H:i:s').PHP_EOL.
            $this->getMessage().':'.$this->getCode().PHP_EOL.
            $this->getFile().':'.$this->getLine().PHP_EOL.
            print_r(k::simplify_trace($this->getTrace()), TRUE);//NOVERIFY
        return $ret;
    }

    /**
     * @return \Guzaba2\Transactions\Transaction|null
     */
    public function getInterruptedTransaction() : ?\Guzaba2\Transactions\Transaction
    {
        return $this->InterruptedTransaction;
    }

    public function __destruct() {
        //self::$CurrentException = NULL;//we need to reset the current exception when this one is being handled (and also destroyed)
        self::set_static('CurrentException', NULL);
    }


    /**
     * Creates a clone of the provided exception.
     * Uses reflection for this as __clone is a private final method which even if it is made accessible still throws an error.
     * Because of this a new instance of the same class is created but with newInstanceWithoutConstructor() and then the properties are copied (by using setAccessible())
     * To be used only by self::__construct().
     *
     * @param \Throwable $exception
     * @return \Throwable
     */
    private function cloneException() : \Throwable
    {


        $class = get_class($this);

        $RClass = new \ReflectionClass($class);
        $NewException = $RClass->newInstanceWithoutConstructor();//we bypass the contructor as we cant 100% know what to provide to the constructor + we dont need to provide anything as the properties will be set further down (this means that the constructor will not be invoked but this is a good thing - we want only the constructor of the real exception to be invoked anyway)
        $rproperties = $RClass->getProperties();
        foreach ($rproperties as $RProperty) {
            if ($RProperty->isStatic()) {
                continue;
            }
            $RProperty->setAccessible(TRUE);
            $NewException->setProperty($RProperty->name, $RProperty->getValue($this));
        }

        $NewException->is_cloned_flag = TRUE;
        unset($RClass);
        unset($rproperties);

        return $NewException;
    }

    public function is_cloned() : bool
    {
        return $this->is_cloned_flag;
    }

    public function getTimeCreated() : int
    {
        return $this->time_created;
    }

    public function getMicrotimeCreated() : float
    {
        return $this->microtime_created;
    }

    public function getSessioNSubjectId() : int
    {
        return $this->session_subject_id;
    }

    public function getExecutionId() : int
    {
        return $this->execution_id;
    }

    public function getExecutionContext() : ?framework\kernel\classes\executionContext
    {
        return $this->executionContext;
    }

    public function getExecutionDetails() : array
    {
        return $this->execution_details;
    }

    /**
     * Allows the trace of the exception to be overriden.
     *
     * It uses Reflection to make the private property $trace accessible
     *
     * This is needed because on certain cases we cant have a traceException created and then thrown if/when needed.
     * If this is done in @see framework\orm\classes\destroyedInstance::__construct() it triggers bug: @see https://bugs.php.net/bug.php?id=76047
     *
     * Because of this instead of creating an exception there we just store the backtrace as given by debug_backtrace() in an array and then if/when needed to throw an excetpnio (because a destroyedInstance has been accessed) a new traceException will be created, its properties updated and then set as a previous exception
     */
    public function setTrace(array $backtrace) : void
    {
        $this->setProperty('trace', $backtrace);
    }

    public function setFile(string $file) : void
    {
        $this->setProperty('file', $file);
    }

    public function setLine(int $line) : void
    {
        $this->setProperty('line', $line);
    }

    public function setCode(int $code) : void
    {
        $this->setProperty('code', $code);
    }

    public function setMessage(string $message) : void
    {
        $this->setProperty('message', $message);
    }

    public function setAllData(framework\base\exceptions\traceInfoObject $traceInfoObject) {

    }

    public function get_memory_usage() : int
    {
        return $this->memory_usage;
    }

    public function get_real_memory_usage() : int
    {
        return $this->real_memory_usage;
    }

    public function get_peak_memory_usage() : int
    {
        return $this->peak_memory_usage;
    }

    public function get_real_peak_memory_usage() : int
    {
        return $this->real_peak_memory_usage;
    }

    public function get_system_load_average() : array
    {
        return $this->load_average;
    }

    public function is_framework_exception() : bool
    {
        return $this->is_framework_exception_flag;
    }

    /**
     * There is a case when we want to append to the message of an exception that is thrown and inject this before it is being caught
     * This is done in the rollbackcontainer - we get the current transaction and then the interrupting exception and we can append it there
     */
    public function appendMessage($message) {
        $this->message .= ' '.$message;
    }

    /**
     * Returns the current exception (if there is such)
     * To be used by @see Guzaba2\Transactions\Transaction::get_interrupting_exception()
     * @return \Throwable
     */
    public static function getCurrentException() : ?\Throwable
    {
        //return self::$CurrentException;
        return self::get_static('CurrentException');
    }

    /**
     * Returns an array of messages from this exception and all previous exceptions (if there are such)
     * The first message is from this exception, the second is from its previous exception, and so on...
     * @return array Array of strings (error messages)
     */
    public function getAllMessages() : array
    {
        $messages = [];
        $exception = $this;
        do {
            $messages[] = $exception->getMessage().' ';
            $exception = $exception->getPrevious();

        } while($exception);
        return $messages;

    }

    /**
     * Returns an array of messages from this exception and all previous exceptions (if there are such)
     * The first message is from this exception, the second is from its previous exception, and so on...
     * @return string A concatenated string of all error messages;.
     */
    public function getAllMessagesAsString() : string
    {
        $message = implode(' ',$this->getAllMessagesAsArray());
        return $message;
    }

    /**
     * We need only the error string, not the trace.
     * The parent will also show the backtrace.
     * @override
     */
    public function __toString() {
        return $this->getMessage();
    }

    public function toStandardString() : string
    {
        return parent::__toString();
    }

    /**
     * Returns the caller using the internal debug_backtrace() function.
     * @param int $level $level=1 means the parent caller, 2 means the parent of the parent call and so on
     * @return array
     */
    protected function _get_caller($level=1) {
        $trace_arr = debug_backtrace();
        return $trace_arr[$level+1];
    }

    /**
     * Returns the caller class using the internal debug_backtrace() function.
     * @param int $level $level=1 means the parent caller, 2 means the parent of the parent call and so on
     * @return string
     */
    protected function _get_caller_class($level=1) {
        //$caller_arr = $this->_get_caller($level);
        $trace_arr = debug_backtrace(0);
        $caller_arr = $trace_arr[$level+1];
        if (!isset($caller_arr['class'])) {
            $trace = debug_backtrace();
            throw new framework\base\exceptions\runTimeException(sprintf(t::_('%s::%s is not called from class and _get_caller_class() can not be used.'),$trace[1]['class'],$trace[1]['function']));
        }
        return $caller_arr['class'];
    }

    /**
     * Returns the caller method using the internal debug_backtrace() function.
     * @param int $level $level=1 means the parent caller, 2 means the parent of the parent call and so on
     * @return string
     */
    protected function _get_caller_method($level=1) {
        //$caller_arr = $this->_get_caller($level);
        $trace_arr = debug_backtrace(0);
        $caller_arr = $trace_arr[$level+1];
        if (!isset($caller_arr['function'])) {
            $trace = debug_backtrace();
            throw new framework\base\exceptions\runTimeException(sprintf(t::_('%s::%s is not called from class and _get_caller_method() can not be used.'),$trace[1]['class'],$trace[1]['function']));
        }
        return $caller_arr['function'];
    }




    public static function setPreviousExceptionStatic(\Throwable $exception, \Guzaba2\Base\Interfaces\TraceInfoInterface $previous) : void
    {

        if ($previous instanceof framework\base\interfaces\traceInfo ) {
            $previous = $previous->getAsException();
        }


        self::setPropertyStatic($exception, 'previous', $previous);
    }

    /**
     * This allows a previous exception to be set on an existing exception (this can be set only during the construction)
     * This is to be used to set traceException as a previous exception to an existing one.
     * But we will not be limiting the signature only to the traceException
     *
     * If the provided argument is traceInfoObject not a traceException (these two classes implement traceInfo) then the traceInfoObject will be converted to traceException
     *
     * @param framework\base\interfaces\traceInfo $exception
     */
    //public function setPreviousException(\Throwable $previous) : void
    public function setPreviousException( \Guzaba2\Base\Interfaces\TraceInfoInterface $previous) : void
    {
        /*
        $reflection = new \ReflectionClass($this);
        while( ! $reflection->hasProperty('previous') ) {
            $reflection = $reflection->getParentClass();
        }
        $prop = $reflection->getProperty('previous');
        $prop->setAccessible(true);
        $prop->setValue($this, $previous);
        $prop->setAccessible(false);
        */
        if ($previous instanceof framework\base\interfaces\traceInfo ) {
            $previous = $previous->getAsException();
        }


        $this->setProperty('previous', $previous);
    }

    public static function prependAsFirstExceptionStatic(\Throwable $exception, \Throwable $FirstException) : void
    {
        $CurrentFirstException = self::getFirstExceptionStatic($exception);
        self::setPreviousExceptionStatic($CurrentFirstException, $FirstException);
    }

    public function prependAsFirstException(\Throwable $FirstException) : void
    {
        self::prependAsFirstExceptionStatic($this, $FirstException);
    }

    /**
     * The first exception of a chain of previous exceptions.
     *
     */
    public static function getFirstExceptionStatic(\Throwable $exception) : \Throwable
    {
        do {
            $previous_exception = $exception->getPrevious();
            if ($previous_exception) {
                $exception = $previous_exception;
            }
        } while($previous_exception);

        return $exception;
    }

    public function getFirstException() : \Throwable
    {
        return self::getFirstExceptionStatic($this);
    }


    /**
     *
     * It uses Reflection to make the private property $previous accessible
     *
     */
    private function setProperty(string $property_name, /* mixed */ $property_value) : void
    {
        // $reflection = new \ReflectionClass($this);
        // while( ! $reflection->hasProperty($property_name) ) {
        //     $reflection = $reflection->getParentClass();
        // }
        // $prop = $reflection->getProperty($property_name);
        // $prop->setAccessible(true);
        // $prop->setValue($this, $property_value);
        // $prop->setAccessible(false);
        self::setPropertyStatic($this, $property_name, $property_value);
    }

    private static function setPropertyStatic(\Throwable $exception, string $property_name, /* mixed */ $property_value) : void
    {
        $reflection = new \ReflectionClass($exception);
        while( ! $reflection->hasProperty($property_name) ) {
            $reflection = $reflection->getParentClass();
        }
        $prop = $reflection->getProperty($property_name);
        $prop->setAccessible(true);
        $prop->setValue($exception, $property_value);
        $prop->setAccessible(false);
    }
}