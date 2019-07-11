<?php
declare(strict_types=1);
/*
 * Guzaba Framework 2
 * http://framework2.guzaba.org
 *
 * This source file is subject to the BSD license that is bundled with this
 * package in the file LICENSE.txt and available also at:
 * http://www.opensource.org/licenses/bsd-license.php
 *
 */

/**
 * @category    Guzaba Framework 2
 * @package     Database
 * @subpackage  Overloading
 * @copyright   Copyright (c) Guzaba Ltd - http://guzaba.com
 * @license     http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author      Vesselin Kenashkov <vesko@webstudiobulgaria.com>
 */

namespace Guzaba2\Transaction;

/**
 * Carries context/properties assigned during runtime
 *
 */
class TransactionContext
{

    protected $context_properties = [];


    public function &__get(string $property) /* mixed */
    {
        return $this->context_properties[$property];
    }

    public function __set(string $property, /* mixed */ $value): void
    {
        $this->context_properties[$property] = $value;
    }

    public function __isset(string $property): bool
    {
        return array_key_exists($property, $this->context_properties);
    }

    public function __unset(string $property): void
    {
        unset($this->context_properties[$property]);
    }
}