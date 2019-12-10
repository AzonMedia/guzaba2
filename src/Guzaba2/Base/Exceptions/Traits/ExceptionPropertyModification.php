<?php
declare(strict_types=1);

namespace Guzaba2\Base\Exceptions\Traits;

trait ExceptionPropertyModification
{

    /**
     * Prepends the provided backtrace to the backtrace of the Exception.
     * This is used when an exception in a subcoroutine is caught in the create() wrapper.
     * There the backtrace where originally this coroutine was created is prepended.
     * @param array $backtrace
     */

    public function prependTrace(array $backtrace) : void
    {
        $this_backtrace = $this->getTrace();
        $combined_trace = array_merge($this_backtrace, $backtrace);
        $this->setTrace($combined_trace);
    }

    /**
     * Allows the trace of the exception to be overriden.
     *
     * It uses Reflection to make the private property $trace accessible
     *
     * This is needed because on certain cases we cant have a traceException created and then thrown if/when needed.
     * If this is done in @see framework\orm\classes\destroyedInstance::__construct() it triggers bug: @see https://bugs.php.net/bug.php?id=76047
     *
     * Because of this instead of creating an exception there we just store the backtrace as given by debug_backtrace() in an array and then if/when needed to throw an excetpnio (because a destroyedInstance has been accessed) a new traceException will be created, its properties updated and then set as a previous exception
     */
    public function setTrace(array $backtrace) : void
    {
        $this->setProperty('trace', $backtrace);
    }

    public function setFile(string $file) : void
    {
        $this->setProperty('file', $file);
    }

    public function setLine(int $line) : void
    {
        $this->setProperty('line', $line);
    }

    public function setCode(int $code) : void
    {
        $this->setProperty('code', $code);
    }

    public function setMessage(string $message) : void
    {
        $this->setProperty('message', $message);
    }


    /**
     *
     * It uses Reflection to make the private property $previous accessible
     *
     */
    private function setProperty(string $property_name, /* mixed */ $property_value) : void
    {
        // $reflection = new \ReflectionClass($this);
        // while( ! $reflection->hasProperty($property_name) ) {
        //     $reflection = $reflection->getParentClass();
        // }
        // $prop = $reflection->getProperty($property_name);
        // $prop->setAccessible(true);
        // $prop->setValue($this, $property_value);
        // $prop->setAccessible(false);
        self::setPropertyStatic($this, $property_name, $property_value);
    }


    //////////////////////////////////////////
    /// STATIC METHODS
    //////////////////////////////////////////

    public static function prependTraceStatic(\Throwable $Exception, array $backtrace) : void
    {
        $this_backtrace = $Exception->getTrace();
        $combined_trace = array_merge($this_backtrace, $backtrace);
        self::setTraceStatic($Exception, $backtrace);
    }

    public static function setTraceStatic(\Throwable $Exception, array $backtrace) : void
    {
        self::setPropertyStatic($Exception, 'trace', $backtrace);
    }

    public static function setFileStatic(\Throwable $Exception, string $file) : void
    {
        self::setPropertyStatic($Exception, 'file', $file);
    }

    public static function setLineStatic(\Throwable $Exception, int $line) : void
    {
        self::setPropertyStatic($Exception, 'line', $line);
    }

    public static function setCodeStatic(\Throwable $Exception, int $code) : void
    {
        self::setPropertyStatic($Exception, 'code', $code);
    }

    public static function setMessageStatic(\Throwable $Exception, string $message) : void
    {
        self::setPropertyStatic($Exception, 'message', $message);
    }

    private static function setPropertyStatic(\Throwable $exception, string $property_name, /* mixed */ $property_value) : void
    {
        $reflection = new \ReflectionClass($exception);
        while (! $reflection->hasProperty($property_name)) {
            $reflection = $reflection->getParentClass();
        }
        $prop = $reflection->getProperty($property_name);
        $prop->setAccessible(true);
        $prop->setValue($exception, $property_value);
        $prop->setAccessible(false);
    }
}
