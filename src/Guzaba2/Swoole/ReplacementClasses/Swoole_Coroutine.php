<?php
namespace Swoole;

class Coroutine
{
    public static function create($callable, ...$params)
    {
    }

    public static function defer($callback)
    {
    }

    public static function set($options)
    {
    }

    public static function exists($cid)
    {
    }

    public static function yield()
    {
    }

    public static function suspend()
    {
    }

    public static function resume($cid)
    {
    }

    public static function stats()
    {
    }

    public static function getCid()
    {
    }

    public static function getuid()
    {
    }

    public static function getPcid($cid)
    {
    }

    public static function getContext($cid)
    {
    }

    public static function getBackTrace($cid, $options, $limit=0)
    {
    }

    public static function list()
    {
    }

    public static function listCoroutines()
    {
    }

    public static function enableScheduler()
    {
    }

    public static function disableScheduler()
    {
    }

    public static function gethostbyname($domain_name, $family, $timeout)
    {
    }

    public static function dnsLookup($domain_name, $timeout)
    {
    }

    public static function exec($command, $get_error_stream)
    {
    }

    public static function sleep($seconds)
    {
    }

    public static function fread($handle, $length)
    {
    }

    public static function fgets($handle)
    {
    }

    public static function fwrite($handle, $string, $length)
    {
    }

    public static function readFile($filename)
    {
    }

    public static function writeFile($filename, $data, $flags)
    {
    }

    public static function getaddrinfo($hostname, $family, $socktype, $protocol, $service, $timeout)
    {
    }

    public static function statvfs($path)
    {
    }
}
