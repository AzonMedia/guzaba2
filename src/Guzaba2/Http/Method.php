<?php
declare(strict_types=1);

namespace Guzaba2\Http;

use Guzaba2\Base\Exceptions\InvalidArgumentException;

/**
 * Class Method
 * @package Guzaba2\Http
 *
 * PRovides method constants as bitmask
 */
abstract class Method
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

    const HTTP_ALL = self::HTTP_CONNECT | self::HTTP_DELETE | self::HTTP_GET | self::HTTP_HEAD | self::HTTP_OPTIONS | self::HTTP_PATCH | self::HTTP_POST | self::HTTP_PUT | self::HTTP_TRACE ;
    const HTTP_GET_HEAD_OPT = self::HTTP_GET | self::HTTP_HEAD | self::HTTP_OPTIONS ;

    public const METHODS_MAP = [
        self::HTTP_CONNECT      => 'CONNECT',
        self::HTTP_DELETE       => 'DELETE',
        self::HTTP_GET          => 'GET',
        self::HTTP_HEAD         => 'HEAD',
        self::HTTP_OPTIONS      => 'OPTIONS',
        self::HTTP_PATCH        => 'PATCH',
        self::HTTP_POST         => 'POST',
        self::HTTP_PUT          => 'PUT',
        self::HTTP_TRACE        => 'TRACE',
    ];

    /**
     * Returns an array of ints=>method_name of the matched methods.
     * @param int $methods_mask
     * @return array
     */
    public static function get_methods(int $methods_mask): array
    {
        $ret = [];
        foreach (self::METHODS_MAP as $method_int => $method_name) {
            if ($methods_mask & $method_int) {
                $ret[$method_int] = $method_name;
            }
        }
        return $ret;
    }

    /**
     * Returns the method constant value from method as string.
     * @param string $method
     * @return int
     * @throws InvalidArgumentException
     */
    public static function get_method(string $method): int
    {
        $method = strtoupper($method);
        $int = array_search($method, self::METHODS_MAP, TRUE);
        if ($int === FALSE) {
            throw new InvalidArgumentException(sprintf(t::_('An invalid method %1s is provided.'), $method));
        }
        return $int;
    }

    /**
     * Checks is the provided method as string a valid method.
     * @param string $method
     * @return bool
     */
    public static function is_valid_method(string $method): bool
    {
        $method = strtoupper($method);
        $key = array_search($method, self::METHODS_MAP, TRUE);
        return $key === FALSE ? FALSE : TRUE;
    }
}
