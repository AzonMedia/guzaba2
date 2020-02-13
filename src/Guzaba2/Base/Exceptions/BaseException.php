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

use Azonmedia\Packages\Packages;
use Azonmedia\Utilities\GeneralUtil;
use Azonmedia\Utilities\StackTraceUtil;
use Guzaba2\Base\Traits\SupportsObjectInternalId;
use Guzaba2\Base\Traits\StaticStore;
use Guzaba2\Base\Traits\ContextAware;
use Guzaba2\Base\Exceptions\Traits\ExceptionPropertyModification;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Coroutine\Exceptions\ContextDestroyedException;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Translator\Translator as t;
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
abstract class BaseException extends \Azonmedia\Exceptions\BaseException
{
    use SupportsObjectInternalId;
    //use StaticStore;
    use ContextAware;
    //use ExceptionPropertyModification;

    //public const ERROR_REFERENCE_DEFAULT_URL = 'http://error-reference.guzaba.org/error/';

    //TODO - rework this to be coroutine aware - there can be multiple interrupting exceptions in the various routines
    /**
     * @var \Throwable
     */
    private static $CurrentException = NULL;

//    public const STATIC_STORE = [
//        'CurrentException'  => NULL,
//    ];

    /**
     * @var \Guzaba2\Transactions\Transaction
     */
    private $InterruptedTransaction = NULL;

    //private static $is_in_clone_flag = FALSE;

    private bool $is_cloned_flag = FALSE;

    // properties that contain additional debug info follow
    /**
     * The execution ID @see kernel::get_execution_id()
     * @var int
     */
    private $execution_id = 0;

    /**
     * How much time was spent until this exception was created
     * @var float
     */
    private $execution_time = 0;


    private $execution_details = [
        'temporary_roles' => [] ,
        'temporary_privileges' => [],
        'subject_id' => 0,
        'active_environment_vars' => []
    ];

    //private $session_subject_id = 0;//this may be different from the execution context subject_id (may be temporarily substituted)


//    /**
//     *
//     * @var int
//     */
//    protected int $memory_usage;
//
//    /**
//     *
//     * @var int
//     */
//    protected int $real_memory_usage;
//
//    /**
//     *
//     * @var int
//     */
//    protected int $peak_memory_usage;
//
//    /**
//     *
//     * @var int
//     */
//    protected int $real_peak_memory_usage;

    protected $load_average = [];

    protected $is_framework_exception_flag = FALSE;

    protected $is_rethrown_flag = FALSE;

    protected $context_changed_flag = FALSE;

    protected $created_in_coroutine_id = 0;

    protected ?string $uuid = NULL;

    /**
     * The constructor calls first the constructor of tha parent class and then its own code.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = '', int $code = 0, \Throwable $previous = NULL, ?string $uuid = NULL)
    {

        parent::__construct($message, $code, $previous, $uuid);
        

        $this->set_object_internal_id();
        $this->set_created_coroutine_id();

            
        

        //TODO - reenable the below code
        //$db = framework\database\classes\connection1::get_instance();
        //$this->InterruptedTransaction = $db->get_current_transaction();

        //self::$current_exception = clone $this;//exceptions cant be cloned
        //some exceptions must not be cloned
        //if ( ! self::$is_in_clone_flag ) {
        //    self::$is_in_clone_flag = TRUE;
        //there is no risk of recursion when cloning the exception as the cloned exception is created without invoking its constructor
        //self::$CurrentException = $this->cloneException();//if this was a static method and $this is passed then $this does not get destroyed when expected!!! This does not seem to be related to the Reflection but to the fact that $this is passed (even if this was a dynamic method still fails)
        //print_r(StackTraceUtil::get_backtrace());
        //self::set_static('CurrentException', $this->cloneException());


        //if (Coroutine::inCoroutine()) {
        if (\Swoole\Coroutine::getCid() > 0) {
            $this->created_in_coroutine_id = \Swoole\Coroutine::getCid();
            //it is too late here to get the trace where was this coroutine created/started
            //this is done at the time the coroutine is started - the backtrace is saved in the Context
            //$this->setTrace(Coroutine::getFullBacktrace());
            if ($this instanceof ContextDestroyedException) {
                //there is no context so we cant preserve the current exception
            } else {
                $Context = \Swoole\Coroutine::getContext();
                $Context->CurrentException = $this->cloneException();
            }

        } else {
            self::$CurrentException = $this->cloneException();
        }

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

//    public function __toString() : string
//    {
//        return self::getCompleteMessage();
//    }

    /**
     * @overrides
     * @return string|null
     */
    public function getErrorComponentClass() : ?string
    {
        $ret = NULL;
        $Packages = new Packages(Packages::get_application_composer_file_path());
        foreach ($this->getTrace() as $frame) {
            if (!empty($frame['class'])) {
                //Guzaba2 has no psr-4 loader so it needs to be checked explicitly
                if (strpos($frame['class'], 'Guzaba2\\') === 0) {
                    $ret = 'Guzaba2\\Component';
                    break;
                } else {
                    $Package = $Packages->get_package_by_class($frame['class']);
                    if ($Package) {
                        $package_ns = Packages::get_package_namespace($Package);
                        $component_class = $package_ns.'Component';
                        if (class_exists($component_class)) {
                            $ret = $component_class;
                            break;
                        }
                    }
                }

            }
        }
        return $ret;
    }

    public function _before_change_context() : void
    {
        $this->context_changed_flag = TRUE;
        // self::unset_all_static();
    }



    /**
     * @return \Guzaba2\Transactions\Transaction|null
     */
    public function getInterruptedTransaction() : ?\Guzaba2\Transaction\Transaction
    {
        return $this->InterruptedTransaction;
    }

    public function __destruct()
    {
        //print 'EXC DESTR'.PHP_EOL;
        //self::$CurrentException = NULL;//we need to reset the current exception when this one is being handled (and also destroyed)
        if (!$this->context_changed_flag) {
            //self::set_static('CurrentException', NULL);
            if (Coroutine::inCoroutine()) {
                if ($this instanceof ContextDestroyedException) {
                    //no context so nothing to reset
                } else {
                    try {
                        $Context = Coroutine::getContext();
                        $Context->CurrentException = NULL;
                    } catch (ContextDestroyedException $Exception) {
                        //ignore
                    }
                }

            } else {
                self::$CurrentException = NULL;
            }
        }
    }

    public function rethrow() : void
    {
        $this->is_rethrown_flag = TRUE;
        //self::set_static('CurrentException', NULL);
        if (Coroutine::inCoroutine()) {
            $Context = Coroutine::getContext();
            $Context->CurrentException = NULL;
        } else {
            self::$CurrentException = NULL;
        }
    }

    public function is_rethrown() : bool
    {
        return $this->is_rethrown_flag;
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
        return $this->microtime_created / 1_000_000;
    }

    public function getMicrotimeCreated() : int
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

    public function getExecutionContext() //: ?framework\kernel\classes\executionContext
    {
        return $this->executionContext;
    }

    public function getExecutionDetails() : array
    {
        return $this->execution_details;
    }



    public function setAllData(/*framework\base\exceptions\traceInfoObject*/ $traceInfoObject)
    {
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
    public function appendMessage($message)
    {
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
        //return self::get_static('CurrentException');
        if (Coroutine::inCoroutine()) {
            $Context = Coroutine::getContext();
            $ret = $Context->CurrentException ?? self::$CurrentException;
        } else {
            $ret = self::$CurrentException;
        }
    }


}
