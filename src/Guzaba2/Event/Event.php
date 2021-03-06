<?php

declare(strict_types=1);

namespace Guzaba2\Event;

use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\InvalidReturnValueException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Base\Interfaces\ConfigInterface;
use Guzaba2\Base\Interfaces\ObjectInternalIdInterface;
use Guzaba2\Base\Traits\SupportsConfig;
use Guzaba2\Base\Traits\UsesServices;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Event\Interfaces\EventInterface;
use Guzaba2\Translator\Translator as t;

/**
 * Class Event
 * Does not extend base as this would put additional load for creating the unique object ID and this is not needed.
 * @package Guzaba2\Event
 *
 * A _before_ACTION event must return NULL or indexed array with the arguments similar to the one provided
 * A _after_ACTION event must return NULL or a return value similar to the one provided
 */
class Event implements ConfigInterface, EventInterface
{
    use SupportsConfig;
    use UsesServices;

    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'Events'
        ],
    ];

    protected const CONFIG_RUNTIME = [];

    private $Subject;

    protected string $event_name;

    protected array $arguments = [];

    protected /* mixed */ $return_value = null;

    protected ?EventInterface $PreviousEvent = null;

    protected array $callbacks = [];

    protected /* mixed */ $event_return = null;

    /**
     * Event constructor.
     * @param ObjectInternalIdInterface $Subject
     * @param string $event_name
     * @param array $arguments Optionally arguments can be provided. These can be the arguments that were provided to the method or custom set of arguments.
     * @param null $return_value
     * @param Event|null $PreviousEvent
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public function __construct(ObjectInternalIdInterface $Subject, string $event_name, array $arguments = [], /* mixed*/ $return_value = null, Event $PreviousEvent = null)
    {
        $this->Subject = $Subject;
        $this->event_name = $event_name;
        //it makes sense to allow for both as even in the _after_ event the arguments may be needed
//        if (count($arguments) && $return_value !== null) {
//            throw new InvalidArgumentException(sprintf(t::_('An event can have $arguments or $return_value argument but not both.')));
//        }
        $this->arguments = $arguments;
        $this->return_value = $return_value;
        $this->PreviousEvent = $PreviousEvent;
        
        if ($PreviousEvent) {
            $this->callbacks = $PreviousEvent->get_callbacks();
        } else {
            $Events = self::get_service('Events');
            $this->callbacks = array_merge($Events->get_class_callbacks(get_class($Subject), $event_name), $Events->get_object_callbacks($Subject, $event_name));
        }

        $this->event_return = $this->execute_callbacks($this->callbacks);
    }

    public function get_previous_event(): ?EventInterface
    {
        return $this->PreviousEvent;
    }

    public function get_subject(): ObjectInternalIdInterface
    {
        return $this->Subject;
    }

    public function get_event_name(): string
    {
        return $this->event_name;
    }
    
    public function get_arguments(): array
    {
        return $this->arguments;
    }

    public function get_return_value() /* mixed */
    {
        return $this->return_value;
    }

    public function with_arguments(array $arguments): self
    {
        return new self($this->get_subject(), $this->get_event_name(), $arguments, null, $this);
    }

    public function with_return_value(/* mixed */ $return_value): self
    {
        return new self($this->get_subject(), $this->get_event_name(), [], $return_value, $this);
    }

    /**
     * Returns the value returned from the execution of all callbacks.
     * This should be ?array for _before_event or a single mixed value for _after_event.
     * @return array|null
     */
    public function get_event_return() /* mixed */
    {
        return $this->event_return;
    }

    public function __destruct()
    {
        $this->Subject = null;
    }

    public function is_before_event(): bool
    {
        $ret = false;
        if (count($this->arguments)) {
            $ret = true;
        } elseif (strpos($this->event_name, '_before_') === 0) {
            $ret = true;
        }
        return $ret;
    }

    public function is_after_event(): bool
    {
        $ret = false;
        if ($this->return_value !== null) {
            $ret = true;
        } elseif (strpos($this->event_name, '_after_') === 0) {
            $ret = true;
        }
        return $ret;
    }

    private function get_callbacks(): array
    {
        return $this->callbacks;
    }

    /**
     * @param array $callbacks
     * @return array|null
     * @throws InvalidReturnValueException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    private function execute_callbacks(array $callbacks) /* mixed */
    {

        $ret = null;
        //set default value (in case there are no more callbacks)
        if ($this->is_before_event()) {
            $ret = $this->arguments;
        } elseif ($this->is_after_event()) {
            $ret = $this->return_value;
        }

        while (count($this->callbacks)) {
            $callback = array_pop($this->callbacks);
            $ret = $callback($this);

            if ($this->is_before_event() && $ret) {
                $ret = $this->with_arguments($ret)->get_event_return();
                if (!is_array($ret) && !is_null($ret)) {
                    throw new InvalidReturnValueException(sprintf(t::_('A _before_ACTION event must return an array with arguments or NULL. The returned type is %s.'), gettype($ret)));
                }
                if (is_array($ret) && count($ret) !== count($this->arguments)) {
                    throw new InvalidReturnValueException(sprintf(t::_('A _before_ACTION event must return the same number of arguments as the provided ones. The event returned %s arguments while the expected number is %s.'), count($ret), count($this->arguments)));
                }
                break;//break this loop - a new loop will be started by the new event
            } elseif ($this->is_after_event()) {
                $ret = $this->with_return_value($ret)->get_event_return();
                break;
            } else {
                //proceed to the next callback on the same event
            }
        }

        return $ret;
    }

//    private function execute_callbacks(array $callbacks) : int
//    {
//        foreach ($callbacks as $callback) {
//            $callback($this, $this->arguments);
//        }
//        return count($callbacks);
//    }
}
