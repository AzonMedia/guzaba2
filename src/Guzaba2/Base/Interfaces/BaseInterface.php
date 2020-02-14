<?php
declare(strict_types=1);

namespace Guzaba2\Base\Interfaces;

use Guzaba2\Base\Base;
use Guzaba2\Base\Traits\BaseTrait;

/**
 * Interface BaseInterface
 * @package Guzaba2\Base\Interfaces
 * Combines all interfaces implemented by the Base class into one.
 * This interface represents what a Guzaba2 base class should be.
 * It is to be used by classes that come from another hierarchy but still need to integrate tightly with the framework classes
 * @see Base
 * @see BaseTrait
 */
interface BaseInterface extends ConfigInterface, ObjectInternalIdInterface, UsesServicesInterface, ContextAwareInterface
{

}