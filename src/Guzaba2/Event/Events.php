<?php


namespace Guzaba2\Event;

use Azonmedia\Utilities\GeneralUtil;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;

/**
 * Class Events
 * TODO - add protection from recursion
 * @package Guzaba2\Event
 */
class Events extends Base
{
    private $object_callbacks = [];

    private $class_callbacks = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function add_object_callback(ObjectInternalIdInterface $Subject, string $event_name, callable $callback) : bool
    {
        $callback_hash = GeneralUtil::get_callable_hash($callback);
        $subject_unique_id = $Subject->get_object_internal_id();
        if (isset($this->object_callbacks[$subject_unique_id][$event_name][$callback_hash])) {
            if ($this->object_callbacks[$subject_unique_id][$event_name][$callback_hash] === $callback) {
                //it is already added
                return FALSE;
            } else {
                throw new LogicException(sprintf(t::_('There is already added callback which is different from the provided one.')));
            }
        }
        $this->object_callbacks[$subject_unique_id][$event_name][$callback_hash] = $callback;
        return TRUE;
    }

    public function remove_object_callback(ObjectInternalIdInterface $Subject, string $event_name = '', ?callable $callback) : void
    {
        $subject_unique_id = $Subject->get_object_internal_id();
        if ($callback) { //unset only this callback
            $callback_hash = GeneralUtil::get_callable_hash($callback);
            unset($this->object_callbacks[$subject_unique_id][$event_name][$callback_hash]);
        } elseif ($event_name) { //unset all object_callbacks for this event
            unset($this->object_callbacks[$subject_unique_id][$event_name]);
        } else { //unset all object_callbacks for the given subject
            unset($this->object_callbacks[$subject_unique_id]);
        }
    }

    public function get_object_callbacks(ObjectInternalIdInterface $Subject, string $event_name = '') : array
    {
        $subject_unique_id = $Subject->get_object_internal_id();
        if ($event_name) {
            return $this->object_callbacks[$subject_unique_id][$event_name] ?? [];
        }
        return $this->objectcallbacks[$subject_unique_id] ?? [];
    }

    public function add_class_callback(string $class, string $event_name, callable $callback) : bool
    {
        $callback_hash = GeneralUtil::get_callable_hash($callback);
        if (isset($this->class_callbacks[$class][$event_name][$callback_hash])) {
            if ($this->class_callbacks[$class][$event_name][$callback_hash] === $callback) {
                //it is already added
                return FALSE;
            } else {
                throw new LogicException(sprintf(t::_('There is already added callback which is different from the provided one.')));
            }
        }
        $this->class_callbacks[$class][$event_name][$callback_hash] = $callback;
        return TRUE;
    }

    public function remove_class_callback(string $class, string $event_name = '', ?callable $callback) : void
    {
        if ($callback) { //unset only this callback
            $callback_hash = GeneralUtil::get_callable_hash($callback);
            unset($this->class_callbacks[$class][$event_name][$callback_hash]);
        } elseif ($event_name) { //unset all object_callbacks for this event
            unset($this->class_callbacks[$class][$event_name]);
        } else { //unset all object_callbacks for the given class
            unset($this->class_callbacks[$class]);
        }
    }

    public function get_class_callbacks(string $class, string $event_name = '') : array
    {
        if ($event_name) {
            return $this->class_callbacks[$class][$event_name] ?? [];
        }

        return $this->class_callbacks[$class] ?? [];
    }

}
