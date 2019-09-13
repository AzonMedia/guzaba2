<?php
namespace Swoole\Coroutine\Http;
final class Server
{
    public $fd = -1;
    public $host;
    public $port = -1;
    public $ssl;
    public $settings;
    public $errCode;
    public $errMsg;
    public function __construct( $host, $port, $ssl, $reuse_port) { }

    public function __destruct( ) { }

    public function set( array $settings) { }

    public function handle( $pattern, callable $callback) { }

    public function onAccept( ) { }

    public function start( ) { }

    public function shutdown( ) { }

}
