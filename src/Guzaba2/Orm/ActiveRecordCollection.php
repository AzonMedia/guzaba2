<?php

declare(strict_types=1);

namespace Guzaba2\Orm;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Translator\Translator as t;

class ActiveRecordCollection extends Base implements \Iterator, \Countable, \ArrayAccess
{

    private string $class;

    private int $count = 0;

    private array $objects = [];

    public function __construct(string $class, array $data)
    {

        if (!$class) {
            throw new InvalidArgumentException(sprintf(t::_('No $class argument provided.')));
        }
        if (!class_exists($class)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided $class argument "%1$s" does not exist.'), $class));
        }

        $primary_index = $class::get_primary_index_columns();
        if ($data) {
            foreach ($primary_index as $column_name) {
                if (!isset($data[0][$column_name])) {
                    throw new InvalidArgumentException(sprintf(t::_('The provided $data does not have the expected primary index column %1$s.'), $column_name));
                }
            }
        }

        $this->class = $class;
        foreach ($data as $record) {
            //if the provided $data has all the needed properties the boject could be instantiated without hitting the Store
            //TODO - implement ActiveRecord::get_from_record()
            $index = $class::get_index_from_data($record);
            $this->objects[] = new $class($index);
        }
        $this->count = count($this->objects);
    }

    public function get_class(): string
    {
        return $this->class;
    }

    public function first(): ?ActiveRecordInterface
    {
        return $this->objects ? $this->objects[0] : null ;
    }

    public function last(): ?ActiveRecordInterface
    {
        return $this->objects ? $this->objects[ count($this->objects) - 1] : null ;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function current() /* mixed */
    {
        return current($this->objects);
    }

    public function key() /* scalar */
    {
        return key($this->objects);
    }

    public function next(): void
    {
        next($this->objects);
    }

    public function rewind(): void
    {
        reset($this->objects);
    }

    public function valid(): bool
    {
        return $this->current() !== false;
    }


    public function offsetExists(/* mxied */$offset): bool
    {
        self::validate_offset($offset);
        return array_key_exists($offset, $this->objects);
    }

    public function offsetGet(/* mixed */ $offset) /* mixed */
    {
        self::validate_offset($offset);
        return $this->objects[$offset];
    }

    public function offsetSet(/* mixed */ $offset, /* mixed */ $value): void
    {
        self::validate_offset($offset);
        if (!is_object($value)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided $value is of type %1$s. Only objects (of class %s) are accepted.'), gettype($value), $this->get_class()));
        }
        if (!($value instanceof $this->class)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided $value is an object of class %s. Only objects of class %s are accepted.'), $this->get_class($value), $this->get_class()));
        }
        $this->objects[$offset] = $value;
    }

    public function offsetUnset(/* mixed */ $offset): void
    {
        //throw new RunTimeException(sprintf(t::_('It is not allowed to unset elements from an ActiveRecordCollection.')));
        self::validate_offset($offset);
        if (!$this->offsetExists($offset)) {
            throw new InvalidArgumentException(sprintf(t::_('The ActiveRecordCollection has no element %1$s.'), $offset));
        }
        unset($this->objects, $offset);
    }

    private static function validate_offset(/* mixed */ $offset): void
    {
        if (!is_int($offset)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided $offset is of type %1$s. Only integers are accepted.'), gettype($offset)));
        }
    }
}
