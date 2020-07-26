<?php

declare(strict_types=1);

namespace Guzaba2\Event;

use Azonmedia\Utilities\GeneralUtil;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;

/**
 * Class Callbacks
 * @package Guzaba2\Event
 * Contains the object & class callbacks on per coroutine basis.
 */
class Callbacks extends Base
{
    public array $object_callbacks = [];

    public array $class_callbacks = [];

    /**
     * Callbacks constructor.
     * The object & class callbacks may be preinitializaed if arguments are provided.
     * This may happen when there are callbacks added in non-coroutine context and these need to be passed to each coroutine context.
     * @param array $object_callbacks
     * @param array $class_callbacks
     */
    public function __construct(array $object_callbacks = [], array $class_callbacks = [])
    {
        $this->object_callbacks = $object_callbacks;
        $this->class_callbacks = $class_callbacks;
    }
}
