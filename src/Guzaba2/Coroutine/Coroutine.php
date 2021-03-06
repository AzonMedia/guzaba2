<?php

declare(strict_types=1);

namespace Guzaba2\Coroutine;

use Azonmedia\Apm\Interfaces\ProfilerInterface;
use Azonmedia\Di\Container;
use Azonmedia\Lock\Interfaces\LockManagerInterface;
use Azonmedia\Utilities\GeneralUtil;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\BaseException;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Base\Interfaces\ConfigInterface;
use Guzaba2\Base\Traits\SupportsConfig;
use Guzaba2\Base\Traits\SupportsObjectInternalId;
use Guzaba2\Base\Traits\UsesServices;
use Guzaba2\Coroutine\Exceptions\ContextDestroyedException;
use Guzaba2\Event\Events;
use Guzaba2\Http\Request;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Execution\CoroutineExecution;
use Psr\Http\Message\RequestInterface;

/**
 * Class Coroutine
 * Extends \co and overrides the create() methods. This is needed as this class keeps a hierarchy of the created coroutines.
 * Also currently
 * @package Guzaba2\Coroutine
 */
class Coroutine extends \Swoole\Coroutine implements ConfigInterface
{
    use SupportsObjectInternalId;

    use SupportsConfig;

    use UsesServices;


    protected const CONFIG_DEFAULTS = [
        /**
         * This is the maximum number of allowed coroutines within a root (request) coroutine.
         * The hierarchy of the creation of the coroutines is of no importance in related to this limit.
         */
        'max_allowed_subcoroutines'         => 20,
        'max_subcoroutine_exec_time'        => 10, //in seconds
        /**
         * Should a complete backtrace (taking into account parent coroutines) be provided when exception occurrs inside a coroutine
         */
        'enable_complete_backtrace'         => false,//if enabled this will keep individual backtrace for each created coroutine
        //but this is no longer needed as all coroutines should be created with @see self::executeMulti() and never through self::create()
        //and executeMulti keeps the one single backtrace for all awaited coroutines

        'services'                          => [
            'Apm',
        ],
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * The ID of the last corotuine that was created
     * @var int
     */
    protected static int $last_coroutine_id = 0;

    //protected static array $registered_coroutine_services = [];

    /**
     * To be used for storing static data while not in coroutine context
     * @var array
     */
    //protected static array $static_data = [];

    /**
     * @return bool
     */
    public static function completeBacktraceEnabled(): bool
    {
        return self::CONFIG_RUNTIME['enable_complete_backtrace'];
    }

    /**
     * An initialization method that should be always called at the very beginning of the execution of the root coroutine (usually this is the end of the request handler).
     * @param RequestInterface $Request
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws ContextDestroyedException
     * @throws \ReflectionException
     */
    public static function init(?RequestInterface $Request): void
    {

        //it is allowed for sub-corutine to be initialized as it turns out that the \Swoole\Coroutine\Server is now (maybe since 4.5?) running in a parent coroutine
        //and spins a subcoroutine for each incoming request
        //because of this it must be allowed to initialize a subcoroutine
//        if (self::getPcid() > 0) {
//            throw new RunTimeException(sprintf(t::_('%s() can not be called on sub-coroutine.'), __METHOD__));
//        }
        $Context = self::getContext();//this will properly initialize the context
        //$Context->Request = $Request;

        //$Context->{RequestInterface::class} = $Request;//avoid collisions as other libraries may be using the Context
        //even though this is not the perfect solution as someone else might use the same approach and the property name is the Interface name, not the specific Class name
        //to make sure there are not collisions the specific class name is used
        $Context->{Request::class} = $Request;


        
//        foreach (self::$registered_coroutine_services as $class_name) {
//            $Context->{$class_name} = new $class_name();
//        }

        //not really needed as the Apm & Connections object will be destroyed when the Context is destroyed at the end of the coroutine and this will trigger the needed actions.
//        \Swoole\Coroutine::defer(function() use ($Context) {
//            $Context->Apm->store_data();
//            $Context->Connections->freeAllConnections();
//        });


        //this is triggers the object destruction before the $Context it self is destroyed at the end of the coroutine
        //this is needed because the objects being destroyed may be referring to the $Context of the coroutine which is already destroyed
        //this is to say that there is no guarantee that the $Context object is the very
        \Swoole\Coroutine::defer(function () use ($Context) {
            //Kernel::get_di_container()->coroutine_services_cleanup();//fix this!!!
            //cleanup any remaining objects
            $context_vars = get_object_vars($Context);
            foreach ($context_vars as $name => $value) {
                if (is_object($value)) {
                    //TODO - fix this temporary fix - enable coroutine_services_clean()
                    if ($value instanceof LockManagerInterface) { //should be the very last one to be destroyed
                        continue;
                    }
                    //no need to explicitly invoke this
                    //if ($value instanceof Base) {
                    //    $Context->{$name}->__destruct();
                    //}
                    //as the reference below should be the last one (if the coroutines are not awaited then a sub-coroutine may keep holding a reference
                    // and in this case the destructor should not be called here explicitly
                    $Context->{$name} = null;//trigger the destructor
                }
            }
            $Context->is_destroyed = true;
        });
    }

    public static function getRequest($cid = null): ?RequestInterface
    {
        $Context = self::getContext($cid);
        return $Context->{Request::class} ?? null ;
    }


    public static function getCoroutineRequestsStatus(): array
    {
        $ret = [];
        $coroutines = self::listCoroutines();
        foreach ($coroutines as $cid) {
            $pcid = self::getPcid($cid);
            if ($pcid <= 0) { //there is no parent coroutine
                $Request = self::getRequest($cid);
                if ($Request) { //there are coroutines triggered by various timers and they are not triggered by a Request
                    //$ret[$cid] = [$Request->getMethod() => $Request->getUri()->getPath()];
                    $ret[] = [
                        'cid'           => $cid,
                        'method'        => $Request->getMethod(),
                        'route'         => $Request->getUri()->getPath(),
                        'elapsed_time'  => self::getElapsed(),
                    ];
                }
            } else {
                //do not list the sub-coroutines
            }
        }
        return $ret;
    }

    /**
     * @param null $cid
     * @return \Swoole\Coroutine\Context
     * @throws ContextDestroyedException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    public static function getContext($cid = null): \Swoole\Coroutine\Context
    {

        //debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        if (!self::inCoroutine()) {
            throw new RunTimeException(sprintf(t::_('The code is not running in a coroutine thus the context is not available.')));
        }

        $cid = $cid ?? self::getcid();

        $Context = parent::getContext($cid);
        if ($Context === null) {
            throw new ContextDestroyedException(sprintf(t::_('The coroutine %s context is destroyed and can not be used/obtained again.'), self::getCid()));
            //return NULL;
        }

        if (!empty($Context->is_destroyed)) {
            throw new ContextDestroyedException(sprintf(t::_('The coroutine %s context is currently being destroyed and can not be used/obtained again.'), self::getCid()));
        }

        if (empty($Context->is_initialized_flag)) {
            //$Context->Resources = new Resources();
            $Context->{Resources::class} = new Resources();
            //$Context->Channel = new Channel(self::CONFIG_RUNTIME['max_allowed_subcoroutines']);
            $Context->{Channel::class} = new Channel(self::CONFIG_RUNTIME['max_allowed_subcoroutines']);
            $Context->is_initialized_flag = true;
            $Context->sub_coroutine_ids = [];
            $Context->parent_coroutine_id = self::getPcid();
            //$Context->current_user_id = 0;
            //TODO convert these to DI services
            if ($Context->parent_coroutine_id >= 1) {
                //get all properties that are instances
                $ParentContext = self::getContext($Context->parent_coroutine_id);
                foreach ($ParentContext as $property_name => $property_value) {
                    if (is_object($property_value)) {
                        $Context->{$property_name} = $property_value;
                    }
                }
                //each coroutine must have its own Channel
                $Context->{Channel::class} = new Channel(self::CONFIG_RUNTIME['max_allowed_subcoroutines']);
            }
        }
        return $Context;
    }

    /**
     * A wrapper for creating coroutines.
     * This wrapper should be always used instead of calling directly \Swoole\Coroutine::create() as this wrapper keeps track of the coroutines hierarchy.
     * @override
     * @param $callable
     * @param mixed ...$params Any additional arguments will be passed to the coroutine.
     * @return int
     * @throws RunTimeException
     */
    public static function create($callable, ...$params)
    {

        //TODO - this triggers an error - $Context is null in self::getContext() - fix this
//        if (self::getTotalSubCoroutinesCount(self::getRootCoroutineId()) === self::CONFIG_RUNTIME['max_allowed_subcoroutines']) {
//            throw new RunTimeException(sprintf(t::_('The maximum allowed number %s of coroutines per request is reached.'), self::CONFIG_RUNTIME['max_allowed_subcoroutines']));
//        }

        // $current_cid = parent::getcid();

        //cant use $new_cid = parent::create() because $new_id is obtained at a too later stage
        //so instead the callable is wrapped in another callable in which wrapper we obtain the new $cid and process it before the actual callable is executed
        $new_cid = 0;
        $WrapperFunction = static function (...$params) use ($callable, &$new_cid): void {

            //the first thing to do when creating a new coroutine is to obtain/initialize its context (as this accessed the parent context)
            //if this is done later in the coroutine the parent coroutine may no longer exist.
            $Context = self::getContext($new_cid);

            $hash = GeneralUtil::get_callable_hash($callable);

            $new_cid = self::getcid();

            $ParentContext = self::getContext(self::getPcid($new_cid));
            $ParentContext->sub_coroutine_ids[] = $new_cid;
            $ParentChannel = $ParentContext->{Channel::class};

            //each coroutine must have its own global try/catch block as the exception handler is not supported
            try {
                $ret = $callable(...$params);
                $ParentChannel->push(['hash' => $hash, 'ret' => $ret]);
            } catch (\Throwable $Exception) {
                // if (self::completeBacktraceEnabled()) {
                //     //$Exception->prependTrace($Context->getBacktrace());
                //     BaseException::prependTraceStatic($Exception, $ParentContext->getBacktrace());
                // }
                if (!empty($ParentChannel)) {
                    //$chan->push($new_cid);//when the coroutine is over it pushes its ID to the channel of the parent coroutine
                    //before the exception is pushed between coroutines (basically this is pulling the exception outside its context)
                    // it needs to be either cloned or the current exception from the current static context cleaned
                    //$chan->push($Exception);
                    $ParentChannel->push(['hash' => $hash, 'exception' => $Exception]);
                }
            }

            //$Context = self::getContext($new_cid);

            \Swoole\Coroutine::defer(function () use ($Context, $ParentContext) {

                //Kernel::get_di_container()->coroutine_services_cleanup();//it is not possible to trigger the destructors in the right way
                //even if the references are set to NULL in the right way
                //cleanup any remaining objects
                $context_vars = get_object_vars($Context);

                foreach ($context_vars as $name => $value) {
                    if (is_object($value)) {
                        $Context->{$name} = null;//destroy this reference .. but do not call the destructor here.
                        //neither this should trigger the destructor as there should be a reference in the parent coroutine
                        //and the parent coroutine should still be alive as in Guzaba Framework all coroutines should be created
                        //and executed with executeMulti() which awaits them all
                    }
                }

                $Context->is_destroyed = true;

                //do not remove the finished ones
//                $cid = \Swoole\Coroutine::getCid();
//                foreach ($ParentContext->sub_coroutine_ids as $pos => $sub_cid) {
//                    if ($cid === $sub_cid) {
//                        unset($ParentContext->sub_coroutine_ids[$pos]);
//                        break;
//                    }
//                }
//                $ParentContext->sub_coroutine_ids = array_values($ParentContext->sub_coroutine_ids);
            });
        };

        //increment parent coroutine apm values
        //$Apm = self::getContext()->Apm;
        //$Apm->increment_value('cnt_subcoroutines', 1);
        if (self::has_service('Apm')) {
            $Apm = self::get_service('Apm');
            $Apm->increment_value('cnt_subcoroutines', 1);
        }



        parent::create($WrapperFunction, ...$params);

        //cant have await here as this will block
        //there is only one wait for the master coroutine in the request handler (this corotuine was created by the worker not by this method)
        //self::awaitSubCoroutines();

        self::$last_coroutine_id = $new_cid;
        return $new_cid;
    }

    /**
     * Returns TRUE if the current execution is in coroutine
     * @return bool
     */
    public static function inCoroutine(): bool
    {
        $cid = parent::getcid();
        return $cid > 0 ? true : false;
    }

    /**
     * Works like \Swoole\Coroutine::getBacktrace() but returns the backtrace for all the parent coroutines not just the current one.
     * Does not return backtrace for the code outside the root coroutine (which would usually be the coroutine handling the request).
     * The arguments are the same like
     * @param int|null $cid
     * @param int $options
     * @param int $limit
     * @return array
     * @throws ContextDestroyedException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    public static function getFullBacktrace(?int $cid = null, int $options = DEBUG_BACKTRACE_PROVIDE_OBJECT, int $limit = 0): array
    {
        $cid = $cid ?? parent::getcid();
        $parent_cids = self::getParentCoroutines();
        array_unshift($parent_cids, $cid);
        $ret = [];
        foreach ($parent_cids as $cid) {
            $ret = array_merge($ret, parent::getBacktrace($cid, $options, $limit));
        }
        return $ret;
    }

    /**
     * Returns the full backtrace like self::getFullBacktrace() for the current coroutine but does not require any arguments.
     * The returned backtrace is without the arguments and has no limit.
     * @return array
     * @throws ContextDestroyedException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     * @uses self::getFullBacktrace()
     */
    public static function getSimpleBacktrace(): array
    {
        $cid = parent::getcid();
        $parent_cids = self::getParentCoroutines();
        array_unshift($parent_cids, $cid);
        $ret = [];
        foreach ($parent_cids as $cid) {
            $ret = array_merge($ret, parent::getBacktrace($cid, DEBUG_BACKTRACE_IGNORE_ARGS));
        }
        return $ret;
    }

    /**
     * Returns an indexed array with the IDs of all parent coroutines of the provided $cid. The last index is the root coroutine.
     * If no $cid is provided the current coroutine is used.
     * @param int|null $cid
     * @return array
     * @throws ContextDestroyedException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    public static function getParentCoroutines(?int $cid = null): array
    {
        if (!self::inCoroutine()) {
            throw new RunTimeException(sprintf(t::_('The %s() method can be called only in coroutine context.'), __METHOD__));
        }
        $ret = [];

        $current_cid = $cid ?? self::getcid();

        do {
            $Context = self::getContext($current_cid);
            $parent_cid = $Context->parent_coroutine_id;
            if ($parent_cid === -1) {
                break;
            }
            $current_cid = $parent_cid;
        } while (true);
        $ret[] = $current_cid;

        return $ret;
    }

    /**
     * Returns the ID of the root coroutine for the current coroutine or the provided coroutine $cid.
     * If no $cid is provided returns the root coroutine of the current coroutine.
     * If this is in Swoole Server context the root coroutine would be the coroutine started by the worker to serve the request.
     * @param int|null $cid
     * @return int
     * @throws ContextDestroyedException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    public static function getRootCoroutineId(?int $cid = null): int
    {
        if (!self::inCoroutine()) {
            throw new RunTimeException(sprintf(t::_('The %s() method can be called only in coroutine context.'), ___METHOD__));
        }
        $cid = $cid ?? self::getcid();

        $parent_cid = 0;
        do {
            $parent_cid = self::getPcid($cid);
        } while ($parent_cid !== -1);
        $ret = $cid;
        return $ret;
    }

//    /**
//     * Returns the count of all subroutines of the given $cid.
//     * The provided $cid usually is a root coroutine.
//     * @return int
//     */
//    public static function getTotalSubCoroutinesCount(?int $cid = NULL) : int
//    {
//        $cid = $cid ?? parent::getcid();
//        $Function = function (int $cid) use (&$Function) : int {
//            $ret = self::getSubCoroutinesCount($cid);
//            foreach (self::getSubCoroutines($cid) as $sub_coroutine_id) {
//                $ret += $Function($sub_coroutine_id);
//            }
//            return $ret;
//        };
//        return $Function($cid);
//    }
//
//    /**
//     * @param int|null $cid
//     * @return int
//     * @throws RunTimeException
//     */
//    public static function getSubCoroutinesCount(?int $cid = NULL) : int
//    {
//        if (!self::inCoroutine()) {
//            throw new RunTimeException(sprintf(t::_('The %s() method can be called only in coroutine context.'), ___METHOD__));
//        }
//        $cid = $cid ?? self::getcid();
//        $ret = count(self::getContext($cid)->sub_coroutine_ids);
//        return $ret;
//    }

//    /**
//     * Returns an indexed array with the IDs of the subcoroutines created from the provided $cid
//     * @param int|null $cid
//     * @return array
//     */
//    public static function getSubCoroutines(?int $cid = NULL) : array
//    {
//        if (!self::inCoroutine()) {
//            throw new RunTimeException(sprintf(t::_('The %s() method can be called only in coroutine context.'), ___METHOD__));
//        }
//
//        $cid = $cid ?? self::getcid();
//
//        $ret = self::getContext($cid)->sub_coroutine_ids;
//
//        return $ret;
//    }


    /**
     * Executes multiple callables in parallel and blocks until all of them are executed.
     * Returns an indexed array with the return values of the provided callables in the order whcih the callables were provided.
     * The callables are executed in the current Worker, they are not pushed to a TaskWorker.
     * @param callable ...$callables
     * @return array
     * @throws ContextDestroyedException
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    public static function executeMulti(callable ...$callables): array
    {
        if (!count($callables)) {
            throw new InvalidArgumentException(sprintf(t::_('No callables are provided to %1$s()'), __METHOD__));
        }
        $cid = self::getcid();
        if ($cid <= 0) {
            throw new RunTimeException(sprintf(t::_('%1$s() can be executed only from an existing coroutine.'), __METHOD__));
        }

        $Context = self::getContext();
        if (!property_exists($Context, self::class)) {
            $Context->{self::class} = new \stdClass();
        }
        if (!empty($Context->{self::class}->execute_multiple_coroutines)) {
            $message = t::_('There is already a %1$s() running %2$s coroutines.');
            throw new RunTimeException(sprintf($message, __METHOD__, count($Context->{self::class}->execute_multiple_coroutines)));
        }

        foreach ($callables as $callable) {
            //self::create($callable);
            $new_cid = self::create($callable);
            $Context->{self::class}->execute_multiple_coroutines[$new_cid] = GeneralUtil::get_callable_hash($callable);
        }
        $callables_ret = self::awaitSubCoroutines();

        //the return values must be put in the right order
        $ret = [];
        foreach ($callables as $callable) {
            foreach ($callables_ret as $callable_hash => $callable_ret) {
                if (GeneralUtil::get_callable_hash($callable) === $callable_hash) {
                    $ret[] = $callable_ret;
                }
            }
        }

        unset($Context->{self::class}->execute_multiple_coroutines);

        return $ret;
    }

    /**
     * Awaits for all subcoroutines of the current coroutine to end.
     * Blocks the current coroutines until all child coroutines finish.
     * @param int $timeout
     *
     * @return array
     * @throws ContextDestroyedException
     * @throws RunTimeException If the subcoroutines do not finish before the given timeout
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    private static function awaitSubCoroutines(?int $timeout = null): array
    {
        if ($timeout === null) {
            $timeout = self::CONFIG_RUNTIME['max_subcoroutine_exec_time'];
        }

        $Context = self::getContext();

        $Channel = $Context->{Channel::class};

        $subcoroutines_completed_arr = [];
        if (empty($Context->{self::class}->execute_multiple_coroutines)) {
            throw new RunTimeException(sprintf(t::_('There are not current subcoroutines started.')));
        }

        $cnt_coroutines_to_await = count($Context->{self::class}->execute_multiple_coroutines);
        $subcoorutines_arr = $Context->{self::class}->execute_multiple_coroutines;

        for ($aa = 0; $aa < $cnt_coroutines_to_await; $aa++) {
            $ret = $Channel->pop($timeout);
            if ($ret === false) {
                //lets find out which ones timed out
                $failed_subcoroutines_arr = [];
                $bb = 0;
                foreach ($subcoorutines_arr as $sub_cid => $sub_hash) {
                    if (!isset($subcoroutines_completed_arr[$sub_hash])) {
                        $failed_subcoroutines_arr[] = ['cid' => $sub_cid, 'hash' => $sub_hash, 'position' => $bb];
                    }
                    $bb++;
                }
                $message = t::_('The timeout of %1$s seconds for executing the sub-coroutines was reached. The failed sub-coroutines are: %2$s.');
                throw new RunTimeException(sprintf($message, $timeout, print_r($failed_subcoroutines_arr, true)));
            } elseif (!empty($ret['exception'])) {
                //rethrow the exception
                throw $ret['exception'];
                //DO NOT REMOVE THE ABOVE LINE - otherwise the exception may go unnoticed!
            } else {
                //the coroutine finished successfully
            }
            $subcoroutines_completed_arr[$ret['hash']] = $ret['ret'];//the pop returns the subcoroutine ID
        }

        return $subcoroutines_completed_arr;
    }
}
