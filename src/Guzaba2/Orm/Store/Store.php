<?php


namespace Guzaba2\Orm\Store;


use Guzaba2\Base\Base;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;

class Store extends Base
    implements StoreInterface
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function throw_not_found_exception(string $class, string $lookup_index) : void
    {
        throw new RecordNotFoundException(sprintf(t::_('Record of class %s with lookup index %s does not exist.'), $class, $lookup_index));
    }

    protected function throw_unknown_record_type_exception(string $class) : void
    {
        throw new UnknownRecordTypeException(sprintf(t::_('The ORM Store %s has no knowledge for record of class %s.'), get_class($this), $class));
    }
}