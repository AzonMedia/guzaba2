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


    /**
     * Contains the interrupting exception only in non-coroutine mode
     * @var \Throwable
     */
    private static ?\Exception $CurrentException = null;

//    public const STATIC_STORE = [
//        'CurrentException'  => NULL,
//    ];

//    /**
//     * @var \Guzaba2\Transactions\MemoryTransaction
//     */
//    private $InterruptedTransaction = NULL;

    //private static $is_in_clone_flag = FALSE;

    private bool $is_cloned_flag = false;

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

    protected $is_framework_exception_flag = false;

    protected $is_rethrown_flag = false;

    protected $context_changed_flag = false;

    protected $created_in_coroutine_id = 0;

    protected ?string $uuid = null;

    /**
     * The constructor calls first the constructor of tha parent class and then its own code.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     * @param string|null $uuid
     * @throws ContextDestroyedException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function __construct(string $message = '', int $code = 0, \Throwable $previous = null, ?string $uuid = null)
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
        //self::set_static('CurrentException', $this->cloneException());


        //if (Coroutine::inCoroutine()) {
        $cid = Kernel::get_cid();
        if ($cid > 0) {
            $this->created_in_coroutine_id = $cid;
            //it is too late here to get the trace where was this coroutine created/started
            //this is done at the time the coroutine is started - the backtrace is saved in the Context
            //$this->setTrace(Coroutine::getFullBacktrace());
            if ($this instanceof ContextDestroyedException) {
                //there is no context so we cant preserve the current exception
            } else {
                //$Context = \Swoole\Coroutine::getContext();
                $Context = Coroutine::getContext();
                if (!isset($Context->{self::class})) {
                    $Context->{self::class} = new \stdClass();
                }
                $Context->{self::class}->CurrentException = $this->cloneException();
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

    public function getCompleteMessage(): string
    {
        $message = parent::getCompleteMessage();
        //$Server = \Swoole\Server::getInstance();//no longer supported as of Swoole 4.5.0
        $Server = Kernel::get_http_server();
        if ($Server) {
            //$wid = $Server->worker_id;
            $wid = $Server->get_worker_id();
        } else {
            $wid = -1;
        }

        $cid = Kernel::get_cid();
        $pre = 'W' . $wid . 'C' . $cid . ': ';// W0C-1 - how is that possible? be inside the worker but not in coroutine?
        $message = $pre . $message;
        return $message;
    }

    /**
     * @overrides
     * @return string|null
     */
    public function getErrorComponentClass(): ?string
    {
        $ret = null;
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
                        $component_class = $package_ns . 'Component';
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

    public function _before_change_context(): void
    {
        $this->context_changed_flag = true;
        // self::unset_all_static();
    }



//    /**
//     * @return \Guzaba2\Transactions\MemoryTransaction|null
//     */
//    public function getInterruptedTransaction() : ?\Guzaba2\MemoryTransaction\MemoryTransaction
//    {
//        return $this->InterruptedTransaction;
//    }

    /**
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function __destruct()
    {
        //print 'EXC DESTR'.PHP_EOL;
        //self::$CurrentException = NULL;//we need to reset the current exception when this one is being handled (and also destroyed)
        if (!$this->context_changed_flag) {
            //self::set_static('CurrentException', NULL);
            //if (Coroutine::inCoroutine()) {
            if (Kernel::get_cid() > 0) {
                if ($this instanceof ContextDestroyedException) {
                    //no context so nothing to reset
                } else {
                    try {
                        $Context = Coroutine::getContext();
                        //$Context->CurrentException = NULL;
                        $Context = \Swoole\Coroutine::getContext();
                        if (!isset($Context->{self::class})) {
                            $Context->{self::class} = new \stdClass();
                        }
                        $Context->{self::class}->CurrentException = null;
                    } catch (ContextDestroyedException $Exception) {
                        //ignore
                    }
                }
            } else {
                self::$CurrentException = null;
            }
        }
    }

    public function rethrow(): void
    {
        $this->is_rethrown_flag = true;
        //self::set_static('CurrentException', NULL);
        if (Coroutine::inCoroutine()) {
            $Context = Coroutine::getContext();
            $Context->CurrentException = null;
        } else {
            self::$CurrentException = null;
        }
    }

    public function is_rethrown(): bool
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
     * @throws \ReflectionException
     */
    private function cloneException(): \Throwable
    {
        return self::cloneExceptionStatic($this);
    }

    private static function cloneExceptionStatic(\Throwable $Exception): \Throwable
    {
        $class = get_class($Exception);

        $RClass = new \ReflectionClass($class);
        $NewException = $RClass->newInstanceWithoutConstructor();//we bypass the contructor as we cant 100% know what to provide to the constructor + we dont need to provide anything as the properties will be set further down (this means that the constructor will not be invoked but this is a good thing - we want only the constructor of the real exception to be invoked anyway)
        $rproperties = $RClass->getProperties();
        foreach ($rproperties as $RProperty) {
            if ($RProperty->isStatic()) {
                continue;
            }
            $RProperty->setAccessible(true);
            $NewException->setProperty($RProperty->name, $RProperty->getValue($Exception));
        }

        $NewException->is_cloned_flag = true;
        unset($RClass);
        unset($rproperties);

        return $NewException;
    }

    public function is_cloned(): bool
    {
        return $this->is_cloned_flag;
    }

    public function getSessioNSubjectId(): int
    {
        return $this->session_subject_id;
    }

    public function getExecutionId(): int
    {
        return $this->execution_id;
    }

    public function getExecutionContext() //: ?framework\kernel\classes\executionContext
    {
        return $this->executionContext;
    }

    public function getExecutionDetails(): array
    {
        return $this->execution_details;
    }



    public function setAllData(/*framework\base\exceptions\traceInfoObject*/ $traceInfoObject)
    {
    }

    public function get_memory_usage(): int
    {
        return $this->memory_usage;
    }

    public function get_real_memory_usage(): int
    {
        return $this->real_memory_usage;
    }

    public function get_peak_memory_usage(): int
    {
        return $this->peak_memory_usage;
    }

    public function get_real_peak_memory_usage(): int
    {
        return $this->real_peak_memory_usage;
    }

    public function get_system_load_average(): array
    {
        return $this->load_average;
    }

    public function is_framework_exception(): bool
    {
        return $this->is_framework_exception_flag;
    }

    /**
     * There is a case when we want to append to the message of an exception that is thrown and inject this before it is being caught
     * This is done in the rollbackcontainer - we get the current transaction and then the interrupting exception and we can append it there
     */
    public function appendMessage($message)
    {
        $this->message .= ' ' . $message;
    }

    /**
     * Returns the current exception (if there is such)
     * To be used by
     * @return \Throwable
     * @throws ContextDestroyedException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException @see Guzaba2\Transactions\MemoryTransaction::get_interrupting_exception()
     * @throws \ReflectionException
     */
    public static function getCurrentException(): ?\Throwable
    {
        //return self::$CurrentException;
        //return self::get_static('CurrentException');
        if (Coroutine::inCoroutine()) {
            $Context = Coroutine::getContext();
            $Exception = $Context->{self::class}->CurrentException ?? self::$CurrentException;
        } else {
            $Exception = self::$CurrentException;
        }
        //return $Exception;//this will export a reference to the CurrentException and this should not be allowed as in the __destruct() the CurrentException is also destroyed
        //if there are leaked references this will not happen and the CurrentException will overflow outside the stack where it was caught
        if ($Exception) {
            $NewException = self::cloneExceptionStatic($Exception);
        } else {
            $NewException = null;
        }
        return $NewException;
    }
}
