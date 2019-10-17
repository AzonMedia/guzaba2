<?php

namespace Guzaba2\Event;

use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;

/**
 * Class Event
 * Does not extend base as this would put additional load for creating the unique object ID and this is not needed.
 * @package Guzaba2\Event
 */
class Event
{
    private $Subject;

    protected $event_name;

    public function __construct(Events $Events, ObjectInternalIdInterface $Subject, string $event_name)
    {
        $this->Subject = $Subject;
        $this->event_name = $event_name;
        $callbacks = $Events->get_callbacks($Subject, $event_name);
        $this->execute_callbacks($callbacks);
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
