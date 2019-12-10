<?php
declare(strict_types=1);


namespace Guzaba2\Orm;

//NOT USED
class ActiveRecordCollection implements \Iterator
{

    protected array $record_data = [];

    private /* scalar */ $position;

    public function __construct(array $record_data)
    {
        if(!count($record_data))
        $this->record_data = $record_data;

        //$this->position = array_key_first($record_data[0])
    }

    public function current() /* mixed */
    {
        // TODO: Implement current() method.

    }

    public function key() /* scalar */
    {
        // TODO: Implement key() method.
    }

    public function next() : void
    {
        // TODO: Implement next() method.
    }

    public function rewind() : void
    {
        // TODO: Implement rewind() method.
    }

    public function valid() : bool
    {
        // TODO: Implement valid() method.
    }
}