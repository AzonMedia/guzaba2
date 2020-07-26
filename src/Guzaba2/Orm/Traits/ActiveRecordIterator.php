<?php

declare(strict_types=1);

namespace Guzaba2\Orm\Traits;

/**
 * Trait ActiveRecordIterator
 * @package Guzaba2\Orm\Traits
 * This is a slow way to iterate over the properties of the object. It also has the side effect of copying locally the data from the meta & record pointers.
 * A much faster way is to use as_array().
 * This iterator is provided for the sole purpose to allow a single ActiveRecord instance to be passed as the iterable $struct argument to the structured response.
 */
trait ActiveRecordIterator
{

    private array $record_and_meta_data = [];

    private function init_record_and_meta_data(): void
    {
        if (empty($this->record_and_meta_data)) {
            $this->record_and_meta_data = array_merge($this->record_data, $this->meta_data);
        }
    }

    public function rewind()
    {
        $this->init_record_and_meta_data();
        reset($this->record_and_meta_data);
    }

    public function current()
    {
        $this->init_record_and_meta_data();
        return current($this->record_and_meta_data);
    }

    public function key()
    {
        $this->init_record_and_meta_data();
        return key($this->record_and_meta_data);
    }

    public function next()
    {
        $this->init_record_and_meta_data();
        next($this->record_and_meta_data);
    }

    public function valid()
    {
        $this->init_record_and_meta_data();
        return $this->current() !== false;
    }
}
