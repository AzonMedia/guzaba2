<?php
namespace Swoole\Coroutine;
class Client
{
    public $errCode;
    public $errMsg;
    public $fd = -1;
    private $socket;
    public $type;
    public $setting;
    public $connected;
    public const MSG_OOB = 1;
    public const MSG_PEEK = 2;
    public const MSG_DONTWAIT = 64;
    public const MSG_WAITALL = 256;
    public function __construct( $type) { }

    public function __destruct( ) { }

    public function set( array $settings) { }

    public function connect( $host, $port, $timeout, $sock_flag) { }

    public function recv( $timeout) { }

    public function peek( $length) { }

    public function send( $data) { }

    public function sendfile( $filename, $offset, $length) { }

    public function sendto( $address, $port, $data) { }

    public function recvfrom( $length, &$address, &$port) { }

    public function isConnected( ) { }

    public function getsockname( ) { }

    public function getpeername( ) { }

    public function close( ) { }

    public function exportSocket( ) { }

}
