<?php
namespace Swoole;
class Timer
{
    public static function set( array $settings) { }

    public static function tick( $ms, callable $callback, $params) { }

    public static function after( $ms, callable $callback, $params) { }

    public static function exists( $timer_id) { }

    public static function info( $timer_id) { }

    public static function stats( ) { }

    public static function list( ) { }

    public static function clear( $timer_id) { }

    public static function clearAll( ) { }

}
