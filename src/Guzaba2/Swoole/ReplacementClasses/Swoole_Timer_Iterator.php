<?php
namespace Swoole\Timer;
class Iterator extends \ArrayIterator implements \Countable, \Serializable, \SeekableIterator, \ArrayAccess, \Traversable, \Iterator
{
    public const STD_PROP_LIST = 1;
    public const ARRAY_AS_PROPS = 2;
    public function __construct( $array, $ar_flags) { }

    public function offsetExists( $index) { }

    public function offsetGet( $index) { }

    public function offsetSet( $index, $newval) { }

    public function offsetUnset( $index) { }

    public function append( $value) { }

    public function getArrayCopy( ) { }

    public function count( ) { }

    public function getFlags( ) { }

    public function setFlags( $flags) { }

    public function asort( ) { }

    public function ksort( ) { }

    public function uasort( $cmp_function) { }

    public function uksort( $cmp_function) { }

    public function natsort( ) { }

    public function natcasesort( ) { }

    public function unserialize( $serialized) { }

    public function serialize( ) { }

    public function rewind( ) { }

    public function current( ) { }

    public function key( ) { }

    public function next( ) { }

    public function valid( ) { }

    public function seek( $position) { }

}
