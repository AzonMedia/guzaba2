<?php
namespace Swoole;

class ArrayObject implements \ArrayAccess, \Serializable, \Countable, \Iterator, \Traversable
{
    protected $array;
    public function __construct(array $array = [])
    {
    }

    public function isEmpty() : bool
    {
    }

    public function count() : int
    {
    }

    public function current()
    {
    }

    public function key()
    {
    }

    public function valid() : bool
    {
    }

    public function rewind()
    {
    }

    public function next()
    {
    }

    public function get($key)
    {
    }

    public function set($key, $value) : self
    {
    }

    public function delete($key) : self
    {
    }

    public function remove($value, bool $strict = true, bool $loop = false) : self
    {
    }

    public function clear() : self
    {
    }

    public function offsetGet($key)
    {
    }

    public function offsetSet($key, $value)
    {
    }

    public function offsetUnset($key)
    {
    }

    public function offsetExists($key)
    {
    }

    public function exists($key) : bool
    {
    }

    public function contains($value, bool $strict = true) : bool
    {
    }

    public function indexOf($value, bool $strict = true)
    {
    }

    public function lastIndexOf($value, bool $strict = true)
    {
    }

    public function search($needle, $strict = true)
    {
    }

    public function join(string $glue = '') : \Swoole\StringObject
    {
    }

    public function serialize() : \Swoole\StringObject
    {
    }

    public function unserialize($string) : self
    {
    }

    public function sum()
    {
    }

    public function product()
    {
    }

    public function push($value)
    {
    }

    public function pushBack($value)
    {
    }

    public function insert(int $offset, $value) : self
    {
    }

    public function pop()
    {
    }

    public function popFront()
    {
    }

    public function slice($offset, ?int $length = NULL, bool $preserve_keys = false) : self
    {
    }

    public function randomGet()
    {
    }

    public function each(callable $fn) : self
    {
    }

    public function map(callable $fn) : self
    {
    }

    public function reduce(callable $fn)
    {
    }

    public function keys(?int $search_value = NULL, $strict = false) : self
    {
    }

    public function values() : self
    {
    }

    public function column($column_key, $index) : self
    {
    }

    public function unique(int $sort_flags = Swoole\SORT_STRING) : self
    {
    }

    public function reverse(bool $preserve_keys = false) : self
    {
    }

    public function chunk(int $size, bool $preserve_keys = false) : self
    {
    }

    public function flip() : self
    {
    }

    public function filter(callable $fn, int $flag = 0) : self
    {
    }

    public function multiSort(int $sort_order = Swoole\SORT_ASC, int $sort_flags = Swoole\SORT_REGULAR) : self
    {
    }

    public function asort(int $sort_flags = Swoole\SORT_REGULAR) : self
    {
    }

    public function arsort(int $sort_flags = Swoole\SORT_REGULAR) : self
    {
    }

    public function krsort(int $sort_flags = Swoole\SORT_REGULAR) : self
    {
    }

    public function ksort(int $sort_flags = Swoole\SORT_REGULAR) : self
    {
    }

    public function natcasesort() : self
    {
    }

    public function natsort() : self
    {
    }

    public function rsort(int $sort_flags = Swoole\SORT_REGULAR) : self
    {
    }

    public function shuffle() : self
    {
    }

    public function sort(int $sort_flags = Swoole\SORT_REGULAR) : self
    {
    }

    public function uasort(callable $value_compare_func) : self
    {
    }

    public function uksort(callable $value_compare_func) : self
    {
    }

    public function usort(callable $value_compare_func) : self
    {
    }

    public function __toArray() : array
    {
    }

    protected static function detectType($value)
    {
    }

    protected static function detectStringType(string $value) : \Swoole\StringObject
    {
    }

    protected static function detectArrayType(array $value) : self
    {
    }
}
