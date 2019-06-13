<?php

namespace Guzaba2\Coroutine;

use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Execution\CoroutineExecution;

/**
 * Class Coroutine
 * Extends \co and overrides the create() methods. This is needed as this class keeps a hierarchy of the created coroutines.
 * Also currently
 * @package Guzaba2\Coroutine
 */
abstract class Coroutine extends \Swoole\Coroutine
{

    public static $coroutines_ids = [];

    /**
     * This is the maximum number of allowed coroutines within a root (request) coroutine.
     * The hierarchy of the creation of the coroutines is of no importance in related to this limit.
     */
    public const MAX_ALLOWED_COROUTINES = 20;

    /**
     * An initialization method that should be always called at the very beginning of the execution of the root coroutine (usually this is the end of the request handler).
     */
    public static function init() : void
    {

        $current_cid = parent::getcid();
        if (!isset(self::$coroutines_ids[$current_cid])) {
            //we need only one channel and it will be associated with the root coroutine and then shared with all the subcoroutines
            self::$coroutines_ids[$current_cid] = ['.' => $current_cid, '..' => NULL, 'chan' => new \Swoole\Coroutine\Channel(self::MAX_ALLOWED_COROUTINES)];
        }

    }

    /**
     * A cleanup method that should be always called at the end of the execution of the root coroutine (usually this is the end of the request handler).
     */
    public static function end() : void
    {

        //block until all coroutines started by the root (request) coroutine are over
        $total_coroutines = self::getTotalSubCoroutinesCount(self::getRootCoroutine());
        $chan = self::getRootCoroutineChannel();
        for ($aa=0 ; $aa<$total_coroutines ; $aa++) {
            $chan->pop();//this is blocking and until all pop() it should wait
        }

        $current_cid = parent::getcid();
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
        $Function($current_cid);
    }

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

    /**
     * A wrapper for creating coroutines.
     * This wrapper should be always used instead of calling directly \co::create() as this wrapper keeps track of the coroutines hierarchy.
     * @param $callable
     * @param mixed ...$params
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

        print 'IN CREATE'.PHP_EOL;
        print_r(self::$coroutines_ids);
        */

        //this will not be needed if init() is called
        $current_cid = parent::getcid();
        if (!isset(self::$coroutines_ids[$current_cid])) {
            self::$coroutines_ids[$current_cid] = ['.' => $current_cid, '..' => NULL];
        }

        //print self::getTotalSubCoroutinesCount(self::getRootCoroutine()).PHP_EOL;
        //print_r(self::$coroutines_ids);
        //print Coroutine::getTotalSubCoroutinesCount(Coroutine::getRootCoroutine()).'*'.PHP_EOL;
        if (self::getTotalSubCoroutinesCount(self::getRootCoroutine()) === self::MAX_ALLOWED_COROUTINES) {
            throw new RunTimeException(sprintf(t::_('The maximum allowed number %s of coroutines per request is reached.'), self::MAX_ALLOWED_COROUTINES));
        }


//        $new_cid = parent::create($callable, $params);
//        self::$coroutines_ids[$new_cid] = ['.' => &$new_cid , '..' => &self::$coroutines_ids[$current_cid] ];
//        self::$coroutines_ids[$current_cid][] =& self::$coroutines_ids[$new_cid];
//
//        print 'IN CREATE '.$new_cid.PHP_EOL;
        //print_r(self::$coroutines_ids);

        //cant use $new_cid = parent::create() because $new_id is obtained at a too later stage
        //so instead the callable is wrapped in another callable in which wrapper we obtain the new $cid and process it before the actual callable is executed
        $new_cid = 0;
        $WrapperFunction = function() use ($callable, &$new_cid, $current_cid) : void
        {
            $new_cid = parent::getcid();
            self::$coroutines_ids[$new_cid] = ['.' => &$new_cid , '..' => &self::$coroutines_ids[$current_cid] ];
            self::$coroutines_ids[$current_cid][] =& self::$coroutines_ids[$new_cid];

            $CoroutineExecution = CoroutineExecution::get_instance();

            $callable();

            $chan = self::getRootCoroutineChannel($new_cid);
            $chan->push($new_cid);

            $CoroutineExecution->destroy();
        };
        parent::create($WrapperFunction, $params);


        return $new_cid;
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
        //print_r($parent_cids);
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
        //print_r($parent_cids);
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
     * Returns the ID of the root coroutine for the provided coroutine.
     * If no $cid is provided returns the root coroutine of the current coroutine.
     * @uses self::getParentCoroutines()
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