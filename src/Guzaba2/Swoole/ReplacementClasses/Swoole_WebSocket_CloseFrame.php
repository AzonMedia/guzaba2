<?php
namespace Swoole\WebSocket;
class CloseFrame extends \Swoole\WebSocket\Frame
{
    public $fd;
    public $data;
    public $finish = true;
    public $opcode = 8;
    public $code = 1000;
    public $reason;
    public function __toString( ) { }

    public static function pack( $data, $opcode, $finish, $mask) { }

    public static function unpack( $data) { }

}
