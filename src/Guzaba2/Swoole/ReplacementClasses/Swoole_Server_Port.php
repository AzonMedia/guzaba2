<?php
namespace Swoole\Server;
class Port
{
    private $onConnect;
    private $onReceive;
    private $onClose;
    private $onPacket;
    private $onBufferFull;
    private $onBufferEmpty;
    private $onRequest;
    private $onHandShake;
    private $onOpen;
    private $onMessage;
    public $host;
    public $port;
    public $type;
    public $sock = -1;
    public $setting;
    public $connections;
    private function __construct( ) { }

    public function __destruct( ) { }

    public function set( array $settings) { }

    public function on( $event_name, callable $callback) { }

    public function getCallback( $event_name) { }

}
