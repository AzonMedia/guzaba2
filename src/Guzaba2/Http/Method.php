<?php
declare(strict_types=1);

namespace Guzaba2\Http;


use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Translator\Translator as t;

class Method extends \Azonmedia\Http\Method
{
    public static function validate_method(int $method): void
    {
        if (!$method) {
            throw new InvalidArgumentException(sprintf(t::_('No method argument is provided.')));
        }
        if (!isset(self::METHODS_MAP[$method])) {
            throw new InvalidArgumentException(sprintf(t::_('The provided method %1$s is not a valid method constant. The method constants can be found in %2$s.'), $method, \Azonmedia\Http\Method::class));
        }
    }
}