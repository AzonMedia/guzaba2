<?php


namespace Guzaba2\Event;

use Azonmedia\Utilities\GeneralUtil;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Event\Interfaces\EventInterface;
use Guzaba2\Event\Interfaces\EventsInterface;
use Guzaba2\Kernel\Kernel;

/**
 * Class Events
 * TODO - add protection from recursion
 * @package Guzaba2\Event
 */
class Events extends Base implements EventsInterface, \Azonmedia\Di\Interfaces\CoroutineDependencyInterface
{

    private array $object_callbacks = [];

    private array $class_callbacks = [];
    
    private static array $non_coroutine_object_callbacks = [];

    private static array $non_coroutine_class_callbacks = [];

    public function __construct()
    {

        if (Coroutine::inCoroutine()) {
            //port any callbacks added before entering into coroutine mode here
            $this->object_callbacks = self::$non_coroutine_object_callbacks;
            $this->class_callbacks = self::$non_coroutine_class_callbacks;
        } else {
            //in non corotuutine mode point the dynamic properties to the static ones so any added callbacks get preserved
            $this->object_callbacks =& self::$non_coroutine_object_callbacks;
            $this->class_callbacks =& self::$non_coroutine_class_callbacks;
        }
    }

    /**
     * @param ObjectInternalIdInterface $Subject
     * @param string $event_name
     * @return EventInterface
     */
    public static function create_event(ObjectInternalIdInterface $Subject, string $event_name) : EventInterface
    {
        return new Event($Subject, $event_name);
    }

    /**
     * @param ObjectInternalIdInterface $Subject
     * @param string $event_name
     * @param callable $callback
     * @return bool
     * @throws LogicException
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     */
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

    /**
     * @param ObjectInternalIdInterface $Subject
     * @param string $event_name
     * @param callable|null $callback
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     */
    public function remove_object_callback(ObjectInternalIdInterface $Subject, string $event_name, ?callable $callback) : void
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

    /**
     * @param ObjectInternalIdInterface $Subject
     * @param string $event_name
     * @return array
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     */
    public function get_object_callbacks(ObjectInternalIdInterface $Subject, string $event_name = '') : array
    {
        $subject_unique_id = $Subject->get_object_internal_id();
        if ($event_name) {
            $event_callbacks = $this->object_callbacks[$subject_unique_id][$event_name] ?? [];
            $all_events_callbacks = $this->object_callbacks[$subject_unique_id]['*'] ?? [];
            return array_merge($event_callbacks, $all_events_callbacks);
        }
        return $this->object_callbacks[$subject_unique_id] ?? [];
    }

    /**
     * @param string $class
     * @param string $event_name
     * @param callable $callback
     * @return bool
     * @throws LogicException
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     */
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

    /**
     * @param string $class
     * @param string $event_name
     * @param callable|null $callback
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     */
    public function remove_class_callback(string $class, string $event_name, ?callable $callback) : void
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

    /**
     * @param string $class
     * @param string $event_name
     * @return array
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     */
    public function get_class_callbacks(string $class, string $event_name = '') : array
    {
        $event_callbacks = [];
        $classes = array_merge([$class], Kernel::get_class_all_parents($class));
        foreach ($classes as $class) {
            if ($event_name) {
                $event_callbacks = array_merge($event_callbacks, $this->class_callbacks[$class][$event_name] ?? [], $this->class_callbacks[$class]['*'] ?? []);
            } else {
                $event_callbacks = array_merge($event_callbacks, $this->class_callbacks[$class] ?? []);
            }
        }
        return $event_callbacks;
    }

}
