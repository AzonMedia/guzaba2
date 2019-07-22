<?php

namespace Guzaba2\Coroutine;

use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Base\Interfaces\ConfigInterface;
use Guzaba2\Base\Traits\SupportsConfig;
use Guzaba2\Base\Traits\SupportsObjectInternalId;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Execution\CoroutineExecution;

/**
 * Class Coroutine
 * Extends \co and overrides the create() methods. This is needed as this class keeps a hierarchy of the created coroutines.
 * Also currently
 * @package Guzaba2\Coroutine
 */
class Coroutine extends \Swoole\Coroutine
    implements ConfigInterface
{

    use SupportsObjectInternalId;

    use SupportsConfig;

    public static $coroutines_ids = [];

    public static $last_coroutine_id = 0;

    protected static $worker_id = 0;

    /**
     * This is the maximum number of allowed coroutines within a root (request) coroutine.
     * The hierarchy of the creation of the coroutines is of no importance in related to this limit.
     */
    //public const MAX_ALLOWED_COROUTINES = 20;

    public const CONFIG_DEFAULTS = [
        'max_allowed_subcoroutines'         => 20,
        'max_subcoroutine_exec_time'        => 5, //in seconds
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * @var callable
     */
    protected $callable;
    protected $settings = [];

    /**
     * An initialization method that should be always called at the very beginning of the execution of the root coroutine (usually this is the end of the request handler).
     */
    public static function init(int $worker_id, string $context_class = \Guzaba2\Coroutine\Context::class) : void
    {

        self::$worker_id = $worker_id;

        $current_cid = self::getcid();
        self::$last_coroutine_id = $current_cid;
        if (!isset(self::$coroutines_ids[$current_cid])) {
            //every coroutine will have its own channel to ensure that it awaits for all its child coroutines to be over

            //defers are called in revers order

            //before unsetting the master coroutine unset the IDs of all subcoroutines
            $Function = function(int $cid) use (&$Function) : void
            {
                foreach (self::$coroutines_ids[$cid] as $key=>$data) {
                    if (is_int($key)) {
                        $Function($data['.']);
                    }
                }
                self::$coroutines_ids[$cid] = NULL;
                unset(self::$coroutines_ids[$cid]);
            };
            //$Function($current_cid);//this will execute before the defer() put in the init()
            defer(function() use ($Function, $current_cid) {
                $Function($current_cid);
            });


            $Context = self::createContextWrapper($current_cid, $context_class);
            parent::defer(function() use ($Context) {
                $Context->freeAllConnections();
                $Context->end_microtime = microtime(TRUE);
            });
            self::$coroutines_ids[$current_cid] = [
                '.'                 => $current_cid,
                '..'                => NULL,
                'chan'              => new \Swoole\Coroutine\Channel(self::CONFIG_RUNTIME['max_allowed_subcoroutines']),
                'context'           => $Context,
            ];
        }

    }

    public static function set_data(string $class, string $key, /* mixed */ $value) : void
    {
        $Context = self::getContext();
        if (!array_key_exists($class, $Context->static_store)) {
            $Context->static_store[$class] = [];
        }
        $Context->static_store[$class][$key] = $value;
    }

    public static function get_data(string $class, string $key) /* mixed */
    {

        if (!self::isset_data($class, $key)) {
            throw new RunTimeException(sprintf(t::_('The coroutine static store does not have key %s for class %s.'), $key, $class));
        }

        return self::getContext()->static_store[$class][$key];
    }

    public static function isset_data(string $class, string $key) : bool
    {
        $Context = self::getContext();
        if (!array_key_exists($class, $Context->static_store)) {
            return FALSE;
        }
        if (!array_key_exists($key, $Context->static_store[$class])) {
            return FALSE;
        }
        return TRUE;
    }

    public static function unset_data(string $class, string $key) : void
    {
        unset(self::getContext()->static_store[$class][$key]);
    }


//    public static function setCoroutineSettings(array $settings, ?int $cid = NULL) : void
//    {
//        if (!array_key_exists($cid, self::$coroutines_ids)) {
//            throw new RunTimeException(sprintf(t::_('The coroutine ID %s was not found in the tree of coroutines. This means that the coroutine %s was not created by using %s::%s().'), $cid, $cid, __CLASS__, 'create'));
//        }
//    }

//    /**
//     * Coroutine constructor.
//     * Cant be invoked from the outside. Use self::createCoroutine()
//     */
//    protected function __construct(callable $callable, array $settings = [])
//    {
//        $this->set_object_internal_id();
//
//        $this->callable = $callable;
//        $this->settings = $settings;
//    }
//
//    /**
//     * Creates a coroutine object (it is invokable)
//     * @param callable $callable
//     * @param array $settings
//     * @return Coroutine
//     */
//    public static function createCoroutine(callable $callable, array $settings = []) : self
//    {
//        if (self::getTotalSubCoroutinesCount(self::getRootCoroutine()) === self::CONFIG_RUNTIME['max_allowed_subcoroutines']) {
//            throw new RunTimeException(sprintf(t::_('The maximum allowed number %s of coroutines per request is reached.'), self::CONFIG_RUNTIME['max_allowed_subcoroutines']));
//        }
//
//        $Coroutine = new self($callable, $settings);
//
//        $current_cid = parent::getcid();
////        $new_cid = parent::create($callable, $params);
////        self::$coroutines_ids[$new_cid] = ['.' => &$new_cid , '..' => &self::$coroutines_ids[$current_cid] ];
////        self::$coroutines_ids[$current_cid][] =& self::$coroutines_ids[$new_cid];
//
//        //cant use $new_cid = parent::create() because $new_id is obtained at a too later stage
//        //so instead the callable is wrapped in another callable in which wrapper we obtain the new $cid and process it before the actual callable is executed
//        $new_cid = 0;
//        $WrapperFunction = function(...$params) use ($callable, &$new_cid, $current_cid) : void
//        {
//            $new_cid = parent::getcid();
//            self::$coroutines_ids[$new_cid] = ['.' => &$new_cid , '..' => &self::$coroutines_ids[$current_cid], 'chan' => new \Swoole\Coroutine\Channel(self::CONFIG_RUNTIME['max_allowed_subcoroutines']), 'settings' => [] ];
//            self::$coroutines_ids[$current_cid][] =& self::$coroutines_ids[$new_cid];
//
//            $CoroutineExecution = CoroutineExecution::get_instance();
//
//            $callable(...$params);
//
//            //$chan = self::getRootCoroutineChannel($new_cid);
//            //$chan->push($new_cid);
//            $chan = self::getParentCoroutineChannel($new_cid);
//            $CoroutineExecution->destroy();
//            $chan->push($new_cid);//when the coroutine is over it pushes its ID to the channel of the parent coroutine
//
//
//
//        };
//        //parent::create($WrapperFunction, ...$params);
//
//        //self::awaitSubCoroutines();
//
//        return $Coroutine;
//    }

    /**
     * Returns the tree structure of the coroutines for the provided root coroutine id,
     * If no root coroutine id is provided the root coroutine id of the current coroutine will be used.
     * @return array
     */
    public static function getHierarchy(int $root_coroutine_id) : array
    {
        $root_coroutine_id = $root_coroutine_id ?? self::getRootCoroutine();
        if (!array_key_exists($root_coroutine_id, self::$coroutines_ids)) {
            throw new RunTimeException(sprintf(t::_('The coroutine ID %s was not found in the tree of coroutines. This means that the coroutine %s was not created by using %s::%s().'), $current_cid, $coroutine_id, __CLASS__, 'create'));
        }
        return self::$coroutines_ids[$root_coroutine_id];
    }

    //public static function getContext(?int $cid = NULL) : \Swoole\Coroutine\Context
    //public static function getContextWrapper($cid = NULL) : \Guzaba2\Coroutine\Context
    public static function getContext($cid = NULL) : \Guzaba2\Coroutine\Context
    {
        $cid = $cid ?? parent::getcid();
        if ($cid <= 0) {
            throw new RunTimeException(sprintf(t::_('The code is not running in a coroutine thus the context is not available.')));
        }
        $Context = self::$coroutines_ids[$cid]['context'];
        return $Context;
    }

    /**
     * Creates a \Guzaba2\Coroutine\Context which wraps aroung Swoole\Coroutine\Context
     * @return Context
     * @throws \Guzaba2\Base\Exceptions\InvalidArgumentException
     */
    private static function createContextWrapper(?int $cid = NULL, string $context_class = \Guzaba2\Coroutine\Context::class) : \Guzaba2\Coroutine\Context
    {
        $cid = $cid ?? parent::getcid();
        if ($context_class !== \Guzaba2\Coroutine\Context::class && !is_a($context_class, \Guzaba2\Coroutine\Context::class)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided class name %s for Context class does not inherit %s.'), $context_class, \Guzaba2\Coroutine\Context::class ));
        }
        $SwooleContext = parent::getContext($cid);
        $Context = new Context($SwooleContext, $cid);
        return $Context;
    }

    /**
     * A wrapper for creating coroutines.
     * This wrapper should be always used instead of calling directly \co::create() as this wrapper keeps track of the coroutines hierarchy.
     * @override
     * @param $callable
     * @param mixed ...$params Any additional arguments will be passed to the coroutine.
     * @return int
     */
    public static function create( $callable, ...$params) {
        /*
        $current_cid = parent::getcid();
        if (!isset(self::$coroutines_ids[$current_cid])) {
            self::$coroutines_ids[$current_cid] = ['.' => $current_cid, '..' => NULL];
        }
        $new_cid = parent::create($callable, $params);
        //self::$coroutines_ids[$current_cid][] = $new_cid;
        self::$coroutines_ids[$new_cid] = ['.' => $new_cid , '..' => &self::$coroutines_ids[$current_cid] ];
        self::$coroutines_ids[$current_cid][] =& self::$coroutines_ids[$new_cid];

        */

        //this will not be needed if init() is called

        //if (!isset(self::$coroutines_ids[$current_cid])) {
        //    self::$coroutines_ids[$current_cid] = ['.' => $current_cid, '..' => NULL];
        //}

        if (self::getTotalSubCoroutinesCount(self::getRootCoroutine()) === self::CONFIG_RUNTIME['max_allowed_subcoroutines']) {
            throw new RunTimeException(sprintf(t::_('The maximum allowed number %s of coroutines per request is reached.'), self::CONFIG_RUNTIME['max_allowed_subcoroutines']));
        }


        $current_cid = parent::getcid();

//        $new_cid = parent::create($callable, $params);
//        self::$coroutines_ids[$new_cid] = ['.' => &$new_cid , '..' => &self::$coroutines_ids[$current_cid] ];
//        self::$coroutines_ids[$current_cid][] =& self::$coroutines_ids[$new_cid];

        //cant use $new_cid = parent::create() because $new_id is obtained at a too later stage
        //so instead the callable is wrapped in another callable in which wrapper we obtain the new $cid and process it before the actual callable is executed
        $new_cid = 0;
        $WrapperFunction = function(...$params) use ($callable, &$new_cid, $current_cid) : void
        {
            $new_cid = parent::getcid();
            //$Context = parent::getContext();
            //$Context->start_microtime = microtime(TRUE);
            //$Context->settings = [];
            $context_class = get_class(self::getRootCoroutineContext());
            $Context = self::createContextWrapper($current_cid, $context_class);
            self::$coroutines_ids[$new_cid] = [
                '.'                     => &$new_cid ,
                '..'                    => &self::$coroutines_ids[$current_cid],
                'chan'                  => new \Swoole\Coroutine\Channel(self::CONFIG_RUNTIME['max_allowed_subcoroutines']),
                'context'               => $Context,
            ];
            self::$coroutines_ids[$current_cid][] =& self::$coroutines_ids[$new_cid];

            $CoroutineExecution = CoroutineExecution::get_instance();

            $callable(...$params);

            $Context->end_microtime = microtime(TRUE);//here is the actual end time of the nested function execution, not the time when this coroutine will be over
            //actually the coroutine will wait for all its subcoroutines to be over
            
            parent::defer(function() use ($Context) {
                $Context->freeAllConnections();
                $Context->end_microtime_with_subcoroutines = microtime(TRUE);
            });

            //$chan = self::getRootCoroutineChannel($new_cid);
            //$chan->push($new_cid);
            $chan = self::getParentCoroutineChannel($new_cid);

            $CoroutineExecution->destroy();

            $chan->push($new_cid);//when the coroutine is over it pushes its ID to the channel of the parent coroutine

        };
        parent::create($WrapperFunction, ...$params);

        self::awaitSubCoroutines();

        self::$last_coroutine_id = $new_cid;
        return $new_cid;
    }

    /**
     * Suspends the current coroutine.
     * For debug reasons (printing message) is overriden.
     * @return void
     */
    public static function suspend() : void
    {
        print 'Coroutine '.parent::getcid().' is SUSPENDED.'.PHP_EOL;
        parent::suspend();
    }

    /**
     * Resumes the provided $cid coroutine.
     * For debug reasons (printing message) is overriden.
     * @param int $cid
     */
    //public static function resume(int $cid) : void
    public static function resume($cid) : void
    {
        print 'Coroutine '.parent::getcid().' is RESUMED.'.PHP_EOL;
        parent::resume($cid);
    }

    /**
     * Returns TRUE if the current execution is in coroutine
     * @return bool
     */
    public static function inCoroutine() : bool
    {
        $cid = parent::getcid();
        return $cid > 0 ? TRUE : FALSE;
    }

    /**
     * Works like \Swoole\Coroutine::getBacktrace() but returns the backtrace for all the parent coroutines not just the current one.
     * Does not return backtrace for the code outside the root coroutine (which would usually be the coroutine handling the request).
     * The arguments are the same like
     * @param int $options
     * @param int $limit
     * @return array
     * @throws RunTimeException
     */
    public static function getFullBacktrace( ?int $cid = NULL, int $options = DEBUG_BACKTRACE_PROVIDE_OBJECT, int $limit = 0) : array
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
     * @uses self::getFullBacktrace()
     * @return array
     * @throws RunTimeException
     */
    public static function getSimpleBacktrace() : array
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
     * @return array
     * @throws RunTimeException
     */
    public static function getParentCoroutines(?int $cid = NULL) : array
    {
        $ret = [];

        $current_cid = $cid ?? parent::getcid();
        do {
            if (isset(self::$coroutines_ids[$current_cid])) {
                $current_cid = isset(self::$coroutines_ids[$current_cid]['..']['.']) ? self::$coroutines_ids[$current_cid]['..']['.'] : NULL;
                if ($current_cid) {
                    $ret[] = $current_cid;
                } else {
                    break;
                }
            } else {
                //break;
                //throw new \RuntimeException(sprintf('The coroutine ID %s was not found in the tree of coroutines. This means that the coroutine %s was not created by using %s::%s().', $current_cid, $coroutine_id, __CLASS__, 'create'));
                throw new RunTimeException(sprintf(t::_('The coroutine ID %s was not found in the tree of coroutines. This means that the coroutine %s was not created by using %s::%s().'), $current_cid, $current_cid, __CLASS__, 'create'));
            }
        } while($current_cid);

        return $ret;
    }

    /**
     * Returns the ID of the root coroutine for the current coroutine or the provided coroutine $cid.
     * If no $cid is provided returns the root coroutine of the current coroutine.
     * If this is in Swoole Server context the root coroutine would be the coroutine started by the worker to serve the request.
     * @uses self::getParentCoroutines()
     * @param int|null $cid
     * @return int
     */
    public static function getRootCoroutine(?int $cid = NULL) : int
    {
        $cid = $cid ?? parent::getcid();
        $parent_cids = self::getParentCoroutines($cid);
        $ret = count($parent_cids) ? $parent_cids[count($parent_cids) - 1] : parent::getcid();

        return $ret;
    }

    /**
     * Returns the context of the root coroutine of the current coroutine or of the coroutine provided in $cid.
     * If this is in Swoole Server context the root coroutine would be the coroutine started by the worker to serve the request.
     * @uses self::getRootCoroutine()
     * @param int|null $cid
     * @return \Swoole\Coroutine\Context
     */
    public static function getRootCoroutineContext(?int $cid = NULL) : \Swoole\Coroutine\Context
    {
        $root_cid = self::getRootCoroutine($cid);
        return parent::getContext($root_cid);
    }


    /**
     * Returns the count of all subroutines of the given $cid.
     * The provided $cid usually is a root coroutine.
     * @return int
     */
    public static function getTotalSubCoroutinesCount(?int $cid = NULL) : int
    {
        $cid = $cid ?? parent::getcid();
        $Function = function(int $cid) use (&$Function) : int
        {
            $ret = self::getSubCoroutinesCount($cid);
            foreach (self::getSubCoroutines($cid) as $sub_coroutine_id) {

                $ret += $Function($sub_coroutine_id);
            }
            return $ret;
        };
        return $Function($cid);
    }

    /**
     * @param int|null $cid
     * @return int
     * @throws RunTimeException
     */
    public static function getSubCoroutinesCount(?int $cid = NULL) : int
    {
        $cid = $cid ?? parent::getcid();
        $ret = count(self::getSubCoroutines($cid));
        return $ret;
    }

    /**
     * Returns an indexed array with the IDs of the subcoroutines created from the provided $cid
     * @param int|null $cid
     * @return array
     */
    public static function getSubCoroutines(?int $cid = NULL) : array
    {
        $cid = $cid ?? parent::getcid();

        if (!array_key_exists($cid, self::$coroutines_ids)) {
            throw new RunTimeException(sprintf(t::_('The coroutine ID %s was not found in the tree of coroutines. This means that the coroutine %s was not created by using %s::%s().'), $cid, $cid, __CLASS__, 'create'));
        }
        $ret = [];
        $sub_data = self::$coroutines_ids[$cid];
        //unset($sub_data['.']);
        //unset($sub_data['..']);
        foreach ($sub_data as $key=>$sub_coroutine) {
            if (is_int($key)) {
                $ret[] = $sub_coroutine['.'];
            } else {
                //the nonint keys are not subroutines but contain other data
            }
        }
        return $ret;
    }

    /**
     * Awaits for all subcoroutines of the current coroutine to end.
     * Blocks the current coroutines until all child coroutines finish.
     * @throws RunTimeException If the subcoroutines do not finish before the given timeout
     * @param int $timeout
     *
     */
    public static function awaitSubCoroutines(?int $timeout = NULL) : void
    {
        if ($timeout === NULL) {
            $timeout = self::CONFIG_RUNTIME['max_subcoroutine_exec_time'];
        }
        $cid = parent::getcid();
        if (!array_key_exists($cid, self::$coroutines_ids)) {
            throw new RunTimeException(sprintf(t::_('The coroutine ID %s was not found in the tree of coroutines. This means that the coroutine %s was not created by using %s::%s().'), $cid, $cid, __CLASS__, 'create'));
        }
        if (isset(self::$coroutines_ids[$cid]['sub_awaited'])) {
            //the subcoroutines are already finished - do not try again to pop() again as this will block and fail (if there is timeout)
            return;
        }
        $chan = self::getCoroutineChannel($cid);

        $subcoroutines_count = self::getSubCoroutinesCount($cid);
        $subcoorutines_arr = self::getSubCoroutines($cid);
        $subcoroutines_completed_arr = [];
        for ($aa = 0 ; $aa < $subcoroutines_count ; $aa++) {
            $ret = $chan->pop($timeout);

            if ($ret === FALSE) {
                $subcoroutines_unfinished = array_diff($subcoorutines_arr, $subcoroutines_completed_arr);
                $unfinished_message_arr = [];
                foreach ($subcoroutines_unfinished as $unfinished_cid) {
                    $backtrace_str = print_r(parent::getBacktrace($unfinished_cid, DEBUG_BACKTRACE_IGNORE_ARGS), TRUE);
                    $unfinished_message_arr[] = sprintf(t::_('subcoroutine ID %s : %s'), $unfinished_cid, $backtrace_str);
                }
                $unfinished_message_str = sprintf(t::_('Unfinished subcoroutines: %s'), PHP_EOL.implode(PHP_EOL,$unfinished_message_arr) );
                throw new RunTimeException(sprintf(t::_('The timeout of %s seconds was reached. %s'), $timeout, $unfinished_message_str));
            } else {
                //the coroutine finished successfully
            }
            $subcoroutines_completed_arr[] = $ret;//the pop returns the subcoroutine ID
        }
        self::$coroutines_ids[$cid]['sub_awaited'] = TRUE;
    }

    public static function getWorkerId() : int
    {
        return self::$worker_id;
    }

    private static function getParentCoroutineChannel(?int $cid = NULL) : \Swoole\Coroutine\Channel
    {
        $cid = $cid ?? parent::getcid();
        $parent_coroutine_id = self::getParentCoroutines($cid)[0];
        $chan = self::getCoroutineChannel($parent_coroutine_id);
        return $chan;
    }

    /**
     * @param int|null $cid
     * @return \Swoole\Coroutine\Channel
     * @throws RunTimeException
     */
    private static function getCoroutineChannel(?int $cid = NULL) : \Swoole\Coroutine\Channel
    {
        $cid = $cid ?? parent::getcid();
        if (!array_key_exists($cid, self::$coroutines_ids)) {
            throw new RunTimeException(sprintf(t::_('The coroutine ID %s was not found in the tree of coroutines. This means that the coroutine %s was not created by using %s::%s().'), $cid, $cid, __CLASS__, 'create'));
        }
        $ret = self::$coroutines_ids[$cid]['chan'];
        return $ret;
    }

    /**
     * @param int|null $cid
     * @return \Swoole\Coroutine\Channel
     */
    private static function getRootCoroutineChannel(?int $cid = NULL) : \Swoole\Coroutine\Channel
    {
        $cid = $cid ?? parent::getcid();
        $root_coroutine_id = self::getRootCoroutine($cid);
        $ret = self::$coroutines_ids[$root_coroutine_id]['chan'];
        return $ret;
    }
}