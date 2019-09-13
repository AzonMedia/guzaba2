<?php
namespace Swoole;
class Buffer
{
    public $capacity = 128;
    public $length;
    public function __construct( $size) { }

    public function __destruct( ) { }

    public function __toString( ) { }

    public function substr( $offset, $length, $remove) { }

    public function write( $offset, $data) { }

    public function read( $offset, $length) { }

    public function append( $data) { }

    public function expand( $size) { }

    public function recycle( ) { }

    public function clear( ) { }

}
