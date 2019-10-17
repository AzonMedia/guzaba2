<?php


namespace Guzaba2\Event;

use Azonmedia\Utilities\GeneralUtil;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;

class Events extends Base
{
    private $callbacks = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function add_callback(ObjectInternalIdInterface $Subject, string $event_name, callable $callback) : bool
    {
        $callback_hash = GeneralUtil::get_callable_hash($callback);
        $subject_unique_id = $Subject->get_object_internal_id();
        if (isset($callbacks[$subject_unique_id][$event_name][$callback_hash])) {
            if ($this->callbacks[$subject_unique_id][$event_name][$callback_hash] === $callback) {
                //it is already added
                return FALSE;
            } else {
                throw new LogicException(sprintf(t::_('There is already added callback which is different from the provided one.')));
            }
        }
        $this->callbacks[$subject_unique_id][$event_name][$callback_hash] = $callback;
        return TRUE;
    }

    public function remove_callback(ObjectInternalIdInterface $Subject, string $event_name = '', ?callable $callback) : void
    {
        $subject_unique_id = $Subject->get_object_internal_id();
        if ($callback) { //unset only this callback
            $callback_hash = GeneralUtil::get_callable_hash($callback);
            unset($this->callbacks[$subject_unique_id][$event_name][$callback_hash]);
        } elseif ($event_name) { //unset all callbacks for this event
            unset($this->callbacks[$subject_unique_id][$event_name]);
        } else { //unset all callbacks for the given subject
            unset($this->callbacks[$subject_unique_id]);
        }
    }

    public function get_callbacks(ObjectInternalIdInterface $Subject, string $event_name = '') : array
    {
        $subject_unique_id = $Subject->get_object_internal_id();
        if ($event_name) {
            return $this->callbacks[$subject_unique_id][$event_name] ?? [];
        }
        return $this->callbacks[$subject_unique_id] ?? [];
    }

    public function fire_event(ObjectInternalIdInterface $Subject, string $event_name = '') : Event
    {
        return new Event($this, $Subject, $event_name);
    }
}
