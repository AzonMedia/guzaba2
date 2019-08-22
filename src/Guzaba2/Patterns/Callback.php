<?php
declare(strict_types=1);
/*
 * Guzaba Framework 2
 * http://framework2.guzaba.org
 *
 * This source file is subject to the BSD license that is bundled with this
 * package in the file LICENSE.txt and available also at:
 * http://www.opensource.org/licenses/bsd-license.php
 *
 */

/**
 * @category    Guzaba Framework 2
 * @package        Patterns
 * @subpackage    Overloading
 * @copyright    Copyright (c) Guzaba Ltd - http://guzaba.com
 * @license        http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author        Vesselin Kenashkov <vesko@webstudiobulgaria.com>
 */

namespace Guzaba2\Patterns;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\BaseException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Base\TraceInfoObject;
use Guzaba2\Kernel\ExecutionContext;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Translator\Translator as t;

/**
 * This has own implementation of serializing a closure but you can check:
 * @see https://github.com/jeremeamia/super_closure
 * @see https://github.com/opis/closure
 *
 * @uses https://github.com/jeremeamia/FunctionParser
 *
 * Class callback (instead of callable as this is a reserved word since 5.4)
 * This callback
 */
class Callback extends Base
{
    protected $callable;

    /**
     * @todo check if used
     * @var ExecutionContext
     */
    protected $executionContext;

    /**
     * The source code of the callable as string. This is needed because the callable/closure can not be serialized
     * @var string
     */
    protected $callable_source;

    /**
     * Any properties listed here will not be serialzed by the base class
     * @var array
     */
    protected static $properties_not_to_be_serialized = [
        'callable',
    ];

    /**
     * This keeps a special type of exception (this is not a real error exception!) for the purpose to know where this code was defined.
     * @var TraceInfoObject
     */
    protected $trace_info;

    /**
     * The callable argument is not typehisted as it must accept an array (class/object, method name) but the callable typehint failes with that
     * @param callable $callable This is the actual callable that will be executed
     * @param bool $preserve_context Should the current context be preserved (current user, granted privileges etc.. @see ExecutionContext )
     * @param TraceInfoObject $trace_info This is an exception that preserves where it was created - the purpose of this is to know where this callable is defined & added in the code. This is very useful in case there is an error. If not provided then the constructor will create one and preserve it.
     * @throws RunTimeException
     */
    //callable typehint treats the array as callable too but only if the provided array is actually a valid callback!
    public function __construct(/* callable */ $callable, bool $preserve_context = TRUE, ?TraceInfoObject $trace_info = NULL)
    {
        if (is_array($callable)) {
            if (!isset($callable[0])) {
                Kernel::logtofile('EXECUTE_CALLABLE_DEBUG_3', 'the provided array as callable has no key 0.');
            } elseif (!is_object($callable[0])) {
                Kernel::logtofile('EXECUTE_CALLABLE_DEBUG_4', 'the provided array as callable has its 0 key as non object but ' . gettype($callable[0]) . '.');
            }
        } elseif (!is_callable($callable)) {
            throw new RunTimeException(sprintf(t::_('The provided argument to %s is not of type callable.'), __CLASS__));
        }

        if ($trace_info) {
            $this->trace_info = $trace_info;
        } else {
            $this->trace_info = new TraceInfoObject(sprintf(t::_('WHERE THE CALLBACK WAS CREATED AND QUEUED.')));
        }

        $this->callable = $callable;

        if ($preserve_context) {
            //$this->executionContext = new ExecutionContext($callable);
            //$this->callable = new ExecutionContext($this);//we need to provide the callback object as it will serialize property the closure
            //$this->executionContext = new ExecutionContext($callable);
            $this->executionContext = new ExecutionContext($this);
        }


        if (is_array($this->callable)) {
            if ($this->callable[0] instanceof ActiveRecord) {
                //the reference counter needs to be incremented because by the time the callable is executed the instance may no longer exist.
                //then this reference needs t obe destroyed
                //but this will happen not when the callable is executed (__invoke()) as it may be needed to be invoked multiple times, but when this instance is destroyed
                //another reason to be in _before_destruct is that the callback may actually never get executed (for example if it is a transaction callback)
                $this->callable[0]->get_reference();
            }
        }

        parent::__construct();
    }

    protected function _before_destruct()
    {
        if (is_array($this->callable)) {
            if ($this->callable[0] instanceof ActiveRecord) {
                $this->callable[0]->destroy_reference();
            }
        }
    }

    /**
     * Returns the @trace_info parameter provided to the constructor
     * @see TraceInfoObject
     *
     * @author vesko@azonmedia.com
     * @since 0.7.1
     * @created 19.02.2018
     */
    public function getTraceInfo(): ?TraceInfoObject
    {
        return $this->trace_info;
    }

    /**
     * This executes the callable with the executionContext if there is such
     * @throws \Throwable
     */
    public function __invoke()
    {

        //if ($this->callable instanceof ExecutionContext) {
        //executionContext will apply the context and execute again this callback::__invoke
        //(to the constructor of the executionContext we provide $this, not $callable, means we provide the callback object, not the callable that was provided to the callback objects constructor)
        //the reason for this is that we want all execution to be handled in this object (including closure serialization)
        //so to avoid recursion
        $args = func_get_args();


        try {
            if ($this->executionContext && !$this->executionContext->contextIsApplied()) {
                //$this->executionContext->execute();
                $ret = call_user_func_array([$this->executionContext, 'execute'], $args);
            } else { //either there is no context or it is already applied
                $ret = call_user_func_array($this->callable, $args);
            }
        } catch (\Throwable $exception) {
            BaseException::prependAsFirstExceptionStatic($exception, $this->getTraceInfo()->getAsException());
            throw $exception;
        }

        return $ret;
    }

    /**
     * This executes the callable
     */
    //public function executeCallable() {
    //}

    /**
     * Returns the callable no matter is the execution context preserved or not.
     * The returned callable type is not enforced because if it is an array this can not be always checked by PHP correctly and throws a type error
     * @return callable|array
     */
    public function getCallable() /* callable|array */
    {
        if ($this->callable instanceof ExecutionContext) {
            return $this->executionContext->getCallable();
        } else {
            return $this->callable;
        }
    }

    /**
     * Returns the execution context if there was such preserved when the callback object was created, NULL otherwise.
     * @return null|ExecutionContext
     */
    public function getExecutionContext(): ?ExecutionContext
    {
        if ($this->executionContext instanceof ExecutionContext) {
            return $this->executionContext;
        } else {
            return NULL;
        }
    }

    public function _get_added_properties()
    {
        return $this->_added_properties;
    }

    /*
    public function __sleep() {
        $this->prepare_for_serialization();
        parent::__sleep();
        return array('callable_source','executionContext');//we dont care about any of the parent class properties

    }

    public function __wakeup() {
        parent::__wakeup();
    }
    */

    /**
     * @throws \ReflectionException
     */
    protected function _before_serialize(): void
    {
        if ($this->callable instanceof ExecutionContext) {
            //executionContext will handle the serialization as needed by invoking this serialization (this is provided to the context)
        } else {
            if ($this->callable instanceof \Closure) {
                $this->prepare_for_serialization();
            //$this->callable is now in self::properties_not_to_be_serialized_cache
                //$this->callable = null;//we remove the callable if it is a closure instead of putting it in self::$properties_not_to_be_serialized because thiw would mean always to skip it. And we need to leave the callable if it is of any other type but Closure
            } else {
                //we can leave it as it is - only the closures cant be serialized.
                $this->callable_source = $this->callable;
            }
        }

        parent::_before_serialize();
    }

    protected function _after_unserialize(): void
    {
        if (is_string($this->callable_source)) {
            $this->callable = k::eval_code($this->callable_source);//this does not use php eval but instead includes the file from memory
        } else {
            $this->callable = $this->callable_source;
        }

        $this->callable_source = '';
    }

    /**
     * Converts the closure into source code / string that can be serialized
     *
     * @throws \ReflectionException
     */
    private function prepare_for_serialization(): void
    {
        if ($this->callable instanceof \closure) {
            $Rfunction = new \ReflectionFunction($this->callable);
            $parser = new \FunctionParser\FunctionParser($Rfunction);

            $source = '';
            $source = '$closure = ' . $parser->getCode() . ';' . PHP_EOL;
            //$source .= '$closure();';
            $source .= 'return $closure;';
            $context = $parser->getContext();

            foreach ($context as $key => $value) {
                if ($value instanceof ActiveRecordSingle) {
                    // TODO implement if needed ActiveRecordSingle
                    $class = get_class($value);
                    $index = $value->get_index();
                    if ($value->is_modified()) {
                        //the modifications will not be passed in the closure - instead a new object will be created (but by then the modifications may be saved)
                        //but we cant rely on this (usually this will be invoked in MODE_ON_COMMIT_AFTER_MASTER so they will be saved)
                        //but if just passed directly this will not be the case
                    }
                    $source = '$' . $key . ' = \\' . $class . '::get_instance(' . $index . ', $' . strtoupper($key) . ');' . PHP_EOL . $source;
                } elseif ($value instanceof TableGateway) {
                    // TODO implement if needed TableGateway
                    $class = get_class($value);
                    $source = '$' . $key . ' = \\' . $class . '::get_instance();' . PHP_EOL . $source;
                } else {
                    $source = '$' . $key . ' = ' . var_export($value, true) . ';' . PHP_EOL . $source;
                }
            }
            $source = Kernel::get_namespace_declarations($parser->getFileName()) . $source;

            $this->callable_source = $source;
            //TODO add support for parsing & serializing the arguments
        } //TODO add support for the other types of callbacks
    }
}
