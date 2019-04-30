<?php


namespace Guzaba2\Http;


class Method
{
    const HTTP_CONNECT  = 1;
    const HTTP_DELETE   = 2;
    const HTTP_GET      = 4;
    const HTTP_HEAD     = 8;
    const HTTP_OPTIONS  = 16;
    const HTTP_PATCH    = 32;
    const HTTP_POST     = 64;
    const HTTP_PUT      = 128;
    const HTTP_TRACE    = 256;

    public const METHODS_MAP = [
        self::HTTP_CONNECT  => 'CONNECT',
        self::HTTP_DELETE   => 'DELETE',
        self::HTTP_GET      => 'GET',
        self::HTTP_HEAD     => 'HEAD',
        self::HTTP_OPTIONS  => 'OPTIONS',
        self::HTTP_PATCH    => 'PATCH',
        self::HTTP_POST     => 'POST',
        self::HTTP_PUT      => 'PUT',
        self::HTTP_TRACE    => 'TRACE',
    ];
}