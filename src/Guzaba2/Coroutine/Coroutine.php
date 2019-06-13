<?php

namespace Guzaba2\Coroutine;

use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Execution\CoroutineExecution;

abstract class Coroutine extends \Swoole\Coroutine
{

    private static $coroutines_ids = [];

    public static function init() : void
    {

        $current_cid = parent::getcid();
        if (!isset(self::$coroutines_ids[$current_cid])) {
            self::$coroutines_ids[$current_cid] = ['.' => $current_cid, '..' => NULL];
        }

    }

    public static function end() : void
    {

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
//        self::$coroutines_ids[$current_cid] = NULL;
//        unset(self::$coroutines_ids[$current_cid]);
        //print_r(self::$coroutines_ids);
    }

    //public static function create( callable $callable, array $options = []) {
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

            $CoroutineExecution->destroy();
        };
        parent::create($WrapperFunction, $params);

        return $new_cid;
    }

    public static function getBacktrace($cid = NULL, $options = NULL, $limit = NULL) : array
    {
        $current_cid = parent::getcid();
    }

    public static function getParentCoroutines() : array
    {
        $ret = [];

        $current_cid = parent::getcid();
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
                throw new RunTimeException(sprintf(t::_('The coroutine ID %s was not found in the tree of coroutines. This means that the coroutine %s was not created by using %s::%s().'), $current_cid, $coroutine_id, __CLASS__, 'create'));
            }
        } while($current_cid);

        return $ret;
    }

    /**
     * Returns the ID of the root coroutine for the current coroutine.
     * @return int
     */
    public static function getRootCoroutine() : int
    {
        $parent_cids = self::getParentCoroutines();
        $ret = count($parent_cids) ? $parent_cids[count($parent_cids) - 1] : parent::getcid();

        return $ret;
    }
}