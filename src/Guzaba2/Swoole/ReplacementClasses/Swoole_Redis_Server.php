<?php
namespace Swoole\Redis;
class Server extends \Swoole\Server
{
    public $setting;
    public $connections;
    public $host;
    public $port;
    public $type;
    public $mode;
    public $ports;
    public $master_pid;
    public $manager_pid;
    public $worker_id = -1;
    public $taskworker;
    public $worker_pid;
    public const NIL = 1;
    public const ERROR = 0;
    public const STATUS = 2;
    public const INT = 3;
    public const STRING = 4;
    public const SET = 5;
    public const MAP = 6;
    public function start( ) { }

    public function setHandler( $command, callable $callback) { }

    public function getHandler( $command) { }

    public static function format( $type, $value) { }

    public function __construct( $host, $port, $mode, $sock_type) { }

    public function __destruct( ) { }

    public function listen( $host, $port, $sock_type) { }

    public function addlistener( $host, $port, $sock_type) { }

    public function on( $event_name, callable $callback) { }

    public function getCallback( $event_name) { }

    public function set( array $settings) { }

    public function send( $fd, $send_data, $server_socket) { }

    public function sendto( $ip, $port, $send_data, $server_socket) { }

    public function sendwait( $conn_fd, $send_data) { }

    public function exists( $fd) { }

    public function exist( $fd) { }

    public function protect( $fd, $is_protected) { }

    public function sendfile( $conn_fd, $filename, $offset, $length) { }

    public function close( $fd, $reset) { }

    public function confirm( $fd) { }

    public function pause( $fd) { }

    public function resume( $fd) { }

    public function task( $data, $worker_id, ?callable $finish_callback) { }

    public function taskwait( $data, $timeout, $worker_id) { }

    public function taskWaitMulti( array $tasks, $timeout) { }

    public function taskCo( array $tasks, $timeout) { }

    public function finish( $data) { }

    public function reload( ) { }

    public function shutdown( ) { }

    public function stop( $worker_id) { }

    public function getLastError( ) { }

    public function heartbeat( $reactor_id) { }

    public function getClientInfo( $fd, $reactor_id) { }

    public function getClientList( $start_fd, $find_count) { }

    public function connection_info( $fd, $reactor_id) { }

    public function connection_list( $start_fd, $find_count) { }

    public function sendMessage( $message, $dst_worker_id) { }

    public function addProcess( \swoole_process $process) { }

    public function stats( ) { }

    public function bind( $fd, $uid) { }

    public function after( $ms, callable $callback) { }

    public function tick( $ms, callable $callback) { }

    public function clearTimer( ) { }

    public function defer( callable $callback) { }

}