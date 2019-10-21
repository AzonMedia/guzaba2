<?php

namespace Guzaba2\Event;

use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;
use Guzaba2\Coroutine\Coroutine;

/**
 * Class Event
 * Does not extend base as this would put additional load for creating the unique object ID and this is not needed.
 * @package Guzaba2\Event
 */
class Event
{
    private $Subject;

    protected $event_name;

    public function __construct(ObjectInternalIdInterface $Subject, string $event_name)
    {
        $this->Subject = $Subject;
        $this->event_name = $event_name;
        $Events = Coroutine::getContext()->Events;
        $class_callbacks = $Events->get_class_callbacks(get_class($Subject), $event_name);
        $this->execute_callbacks($class_callbacks);
        $object_callbacks = $Events->get_object_callbacks($Subject, $event_name);
        $this->execute_callbacks($object_callbacks);
    }

    public function get_subject() : ObjectInternalIdInterface
    {
        return $this->Subject;
    }

    public function get_event_name() : string
    {
        return $this->event_name;
    }

    public function __destruct()
    {
        $this->Subject = NULL;
    }

    private function execute_callbacks(array $callbacks) : int
    {
        foreach ($callbacks as $callback) {
            $callback($this);
        }
        return count($callbacks);
    }
}
