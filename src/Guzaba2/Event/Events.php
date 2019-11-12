<?php


namespace Guzaba2\Event;

use Azonmedia\Utilities\GeneralUtil;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Event\Interfaces\EventInterface;
use Guzaba2\Event\Interfaces\EventsInterface;

/**
 * Class Events
 * TODO - add protection from recursion
 * @package Guzaba2\Event
 */
class Events extends Base implements EventsInterface
{

    
    public static function create_event(ObjectInternalIdInterface $Subject, string $event_name) : EventInterface
    {
        return new Event($Subject, $event_name);
    }

    public function add_object_callback(ObjectInternalIdInterface $Subject, string $event_name, callable $callback) : bool
    {
        $this->initialize_callbacks();
        $callback_hash = GeneralUtil::get_callable_hash($callback);
        $subject_unique_id = $Subject->get_object_internal_id();
        $Callbacks = Coroutine::getContext()->{Callbacks::class};
        if (isset($Callbacks->object_callbacks[$subject_unique_id][$event_name][$callback_hash])) {
            if ($Callbacks->object_callbacks[$subject_unique_id][$event_name][$callback_hash] === $callback) {
                //it is already added
                return FALSE;
            } else {
                throw new LogicException(sprintf(t::_('There is already added callback which is different from the provided one.')));
            }
        }
        $Callbacks->object_callbacks[$subject_unique_id][$event_name][$callback_hash] = $callback;
        return TRUE;
    }

    public function remove_object_callback(ObjectInternalIdInterface $Subject, string $event_name, ?callable $callback) : void
    {
        $this->initialize_callbacks();
        $subject_unique_id = $Subject->get_object_internal_id();
        $Callbacks = Coroutine::getContext()->{Callbacks::class};
        if ($callback) { //unset only this callback
            $callback_hash = GeneralUtil::get_callable_hash($callback);
            unset($Callbacks->object_callbacks[$subject_unique_id][$event_name][$callback_hash]);
        } elseif ($event_name) { //unset all object_callbacks for this event
            unset($Callbacks->object_callbacks[$subject_unique_id][$event_name]);
        } else { //unset all object_callbacks for the given subject
            unset($Callbacks->object_callbacks[$subject_unique_id]);
        }
    }

    public function get_object_callbacks(ObjectInternalIdInterface $Subject, string $event_name = '') : array
    {
        $this->initialize_callbacks();
        $subject_unique_id = $Subject->get_object_internal_id();
        $Callbacks = Coroutine::getContext()->{Callbacks::class};
        if ($event_name) {
            $event_callbacks = $Callbacks->object_callbacks[$subject_unique_id][$event_name] ?? [];
            $all_events_callbacks = $Callbacks->object_callbacks[$subject_unique_id]['*'] ?? [];
            return array_merge($event_callbacks, $all_events_callbacks);
        }
        return $Callbacks->object_callbacks[$subject_unique_id] ?? [];
    }

    public function add_class_callback(string $class, string $event_name, callable $callback) : bool
    {
        $this->initialize_callbacks();
        $callback_hash = GeneralUtil::get_callable_hash($callback);
        $Callbacks = Coroutine::getContext()->{Callbacks::class};
        if (isset($Callbacks->class_callbacks[$class][$event_name][$callback_hash])) {
            if ($Callbacks->class_callbacks[$class][$event_name][$callback_hash] === $callback) {
                //it is already added
                return FALSE;
            } else {
                throw new LogicException(sprintf(t::_('There is already added callback which is different from the provided one.')));
            }
        }
        $Callbacks->class_callbacks[$class][$event_name][$callback_hash] = $callback;
        return TRUE;
    }

    public function remove_class_callback(string $class, string $event_name, ?callable $callback) : void
    {
        $this->initialize_callbacks();
        $Callbacks = Coroutine::getContext()->{Callbacks::class};
        if ($callback) { //unset only this callback
            $callback_hash = GeneralUtil::get_callable_hash($callback);
            unset($Callbacks->class_callbacks[$class][$event_name][$callback_hash]);
        } elseif ($event_name) { //unset all object_callbacks for this event
            unset($Callbacks->class_callbacks[$class][$event_name]);
        } else { //unset all object_callbacks for the given class
            unset($Callbacks->class_callbacks[$class]);
        }
    }

    public function get_class_callbacks(string $class, string $event_name = '') : array
    {
        $this->initialize_callbacks();
        $Callbacks = Coroutine::getContext()->{Callbacks::class};
        if ($event_name) {
            $event_callbacks = $Callbacks->class_callbacks[$class][$event_name] ?? [];
            $all_events_callbacks = $Callbacks->class_callbacks[$class]['*'] ?? [];
            return array_merge($event_callbacks, $all_events_callbacks);
        }

        return $Callbacks->class_callbacks[$class] ?? [];
    }

    private function initialize_callbacks() : void
    {
        $Context = Coroutine::getContext();
        if (empty($Context->{Callbacks::class})) {
            $Context->{Callbacks::class} = new Callbacks();
        }
    }
}
