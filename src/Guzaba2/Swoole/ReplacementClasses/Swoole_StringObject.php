<?php
namespace Swoole;

class StringObject
{
    protected $string;

    public function __construct(string $string = '')
    {
        $new_string = $string;
    }

    public function length() : int
    {
    }

    public function indexOf(string $needle, int $offset = 0)
    {
    }

    public function lastIndexOf(string $needle, int $offset = 0)
    {
    }

    public function pos(string $needle, int $offset = 0)
    {
    }

    public function rpos(string $needle, int $offset = 0)
    {
    }

    public function ipos(string $needle)
    {
    }

    public function lower() : self
    {
    }

    public function upper() : self
    {
    }

    public function trim() : self
    {
    }

    public function lrim() : self
    {
    }

    public function rtrim() : self
    {
    }

    public function substr(int $offset, $length) : self
    {
    }

    public function repeat($n)
    {
    }

    public function replace(string $search, string $replace, &$count = NULL) : self
    {
    }

    public function startsWith(string $needle) : bool
    {
    }

    public function contains(string $subString) : bool
    {
    }

    public function endsWith(string $needle) : bool
    {
    }

    public function split(string $delimiter, int $limit = Swoole\PHP_INT_MAX) : \Swoole\ArrayObject
    {
    }

    public function char(int $index) : string
    {
    }

    public function chunkSplit(int $chunkLength = 1, string $chunkEnd = '') : self
    {
    }

    public function chunk($splitLength = 1) : \Swoole\ArrayObject
    {
    }

    public function toString()
    {
    }

    public function __toString() : string
    {
    }

    protected static function detectArrayType(array $value) : \Swoole\ArrayObject
    {
    }
}
