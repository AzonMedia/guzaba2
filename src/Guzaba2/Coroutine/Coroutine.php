<?php

namespace Guzaba2\Coroutine;

abstract class Coroutine extends \Swoole\Coroutine
{

    private static $coroutines_ids = [];

    public static function init() {
        $current_cid = parent::getcid();
        if (!isset(self::$coroutines_ids[$current_cid])) {
            self::$coroutines_ids[$current_cid] = ['.' => $current_cid, '..' => NULL];
        }
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

        $current_cid = parent::getcid();
        if (!isset(self::$coroutines_ids[$current_cid])) {
            self::$coroutines_ids[$current_cid] = ['.' => $current_cid, '..' => NULL];
        }
        $new_cid = 0;
        self::$coroutines_ids[$new_cid] = ['.' => &$new_cid , '..' => &self::$coroutines_ids[$current_cid] ];
        self::$coroutines_ids[$current_cid][] =& self::$coroutines_ids[$new_cid];
        $new_cid = parent::create($callable, $params);


        print 'IN CREATE'.PHP_EOL;
        print_r(self::$coroutines_ids);

        return $new_cid;
    }

    public static function getBacktrace($cid = NULL, $options = NULL, $limit = NULL) : array
    {
        $current_cid = parent::getcid();
    }

    public static function getParentCoroutines() : array
    {
        $ret = [];

        print 'IN GET'.PHP_EOL;
        do {
            $current_cid = parent::getcid();
            print $current_cid;
            print_r(self::$coroutines_ids);
            if (isset(self::$coroutines_ids[$current_cid])) {
                $current_cid = self::$coroutines_ids[$current_cid][',.']['.'] ?? NULL;
                if ($current_cid) {
                    $ret[] = $current_cid;
                } else {
                    break;
                }
            } else {
                break;
            }
        } while($current_cid);

        //print_r(self::$coroutines_ids);
        //$current_cid = parent::getcid();
        //print $current_cid;
        return $ret;
    }
}