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

    /**
     * To be used for callbacks added before entering coroutine context
     * Once coroutine context is entered the Context->Callbacks will be used and any previously added callbacks to $this->Callbacks will be ignored.
     * @var Callbacks
     */
    private Callbacks $Callbacks;

    /**
     * Events constructor.
     * Initializes the Callbacks for the non-coroutine context.
     */
    public function __construct()
    {
        $this->Callbacks = new Callbacks();
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
        $Callbacks = $this->get_callbacks();
        $callback_hash = GeneralUtil::get_callable_hash($callback);
        $subject_unique_id = $Subject->get_object_internal_id();
        if (Coroutine::inCoroutine()) {
            $Callbacks = Coroutine::getContext()->{Callbacks::class};
        } else {
            $Callbacks = Coroutine::getContext()->{Callbacks::class};
        }

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

    /**
     * @param ObjectInternalIdInterface $Subject
     * @param string $event_name
     * @param callable|null $callback
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     */
    public function remove_object_callback(ObjectInternalIdInterface $Subject, string $event_name, ?callable $callback) : void
    {
        $Callbacks = $this->get_callbacks();
        $subject_unique_id = $Subject->get_object_internal_id();
        if ($callback) { //unset only this callback
            $callback_hash = GeneralUtil::get_callable_hash($callback);
            unset($Callbacks->object_callbacks[$subject_unique_id][$event_name][$callback_hash]);
        } elseif ($event_name) { //unset all object_callbacks for this event
            unset($Callbacks->object_callbacks[$subject_unique_id][$event_name]);
        } else { //unset all object_callbacks for the given subject
            unset($Callbacks->object_callbacks[$subject_unique_id]);
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
        $Callbacks = $this->get_callbacks();
        $subject_unique_id = $Subject->get_object_internal_id();
        if ($event_name) {
            $event_callbacks = $Callbacks->object_callbacks[$subject_unique_id][$event_name] ?? [];
            $all_events_callbacks = $Callbacks->object_callbacks[$subject_unique_id]['*'] ?? [];
            return array_merge($event_callbacks, $all_events_callbacks);
        }
        return $Callbacks->object_callbacks[$subject_unique_id] ?? [];
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
        $Callbacks = $this->get_callbacks();
        $callback_hash = GeneralUtil::get_callable_hash($callback);
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

    /**
     * @param string $class
     * @param string $event_name
     * @param callable|null $callback
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     */
    public function remove_class_callback(string $class, string $event_name, ?callable $callback) : void
    {
        $Callbacks = $this->get_callbacks();
        if ($callback) { //unset only this callback
            $callback_hash = GeneralUtil::get_callable_hash($callback);
            unset($Callbacks->class_callbacks[$class][$event_name][$callback_hash]);
        } elseif ($event_name) { //unset all object_callbacks for this event
            unset($Callbacks->class_callbacks[$class][$event_name]);
        } else { //unset all object_callbacks for the given class
            unset($Callbacks->class_callbacks[$class]);
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
        $Callbacks = $this->get_callbacks();
        if ($event_name) {
            $event_callbacks = $Callbacks->class_callbacks[$class][$event_name] ?? [];
            $all_events_callbacks = $Callbacks->class_callbacks[$class]['*'] ?? [];
            return array_merge($event_callbacks, $all_events_callbacks);
        }

        return $Callbacks->class_callbacks[$class] ?? [];
    }

    /**
     * @return Callbacks
     * @throws \Guzaba2\Base\Exceptions\RunTimeException
     */
    private function get_callbacks() : Callbacks
    {
        if (Coroutine::inCoroutine()) {
            $Context = Coroutine::getContext();
            if (empty($Context->{Callbacks::class})) {
                $Context->{Callbacks::class} = new Callbacks($this->Callbacks->object_callbacks, $this->Callbacks->class_callbacks);
            }
            $Callbacks = $Context->{Callbacks::class};
        } else {
            $Callbacks = $this->Callbacks;
        }

        return $Callbacks;
    }
}
