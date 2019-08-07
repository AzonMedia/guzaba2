<?php
declare(strict_types=1);
/*
 * Guzaba Framework
 * http://framework.guzaba.org
 *
 * This source file is subject to the BSD license that is bundled with this
 * package in the file LICENSE.txt and available also at:
 * http://www.opensource.org/licenses/bsd-license.php
 *
 */

/**
 * @category    Guzaba Framework
 * @package        Patterns
 * @subpackage    Structure
 * @copyright    Copyright (c) Guzaba Ltd - http://guzaba.com
 * @license        http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author        Vesselin Kenashkov <vesko@webstudiobulgaria.com>
 */

namespace Guzaba2\Patterns\Interfaces;

/**
 * Classes that wrap around another class must implement this interface. They must provide overloading for methods and properties and forward all these calls to the wrapped class. The __invoke magic method is an exception and is not required to be implemented.
 */
interface Wrapper
{
    public function __get(string $property);
    public function __set(string $property, $value) : void;
    public function __isset(string $property) : bool;
    public function __unset(string $property) : void;
    public function __call(string $method, array $args);
    public static function __callStatic(string $method, array $args);
}