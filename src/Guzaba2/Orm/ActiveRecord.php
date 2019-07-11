<?php

namespace Guzaba2\Orm;

use Azonmedia\Reflection\ReflectionClass;
use Guzaba2\Object\GenericObject;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Store\Memory as Memory;

class ActiveRecord extends GenericObject
{
    const PROPERTIES_TO_LINK = ['is_new_flag', 'was_new_flag', 'data'];

    /**
     * @var StoreInterface
     */
    protected static $Store;

    /**
     * @var bool
     */
    public $is_new_flag = FALSE;

    /**
     * @var bool
     */
    protected $was_new_flag = FALSE;

    /**
     * @var array
     */
    public $data = [];

    /**
     * @var mixed
     */
    protected $index;

    /**
     * @var bool
     */
    protected $disable_property_hooks_flag = FALSE;

    public static function _initialize_class(): void
    {
        self::$Store = new Memory();//move to DI
    }

    /**
     * ActiveRecord constructor.
     * @param $index
     * @throws \ReflectionException
     */
    public function __construct( /* mixed*/ $index)
    {
        parent::__construct();

        $this->index = $index;
        // TODO Get data pinter doesn't exist in store interface
        $pointer =& self::$Store->get_data_pointer(get_class($this), $this->index);

        //all properties defined in this class must be references to the store in MemoryCache
        $RClass = new ReflectionClass($this);
        $properties = $RClass->getOwnDynamicProperties();
        foreach ($properties as $RProperty) {
            if (array_key_exists($RProperty->name, $pointer)) {
                $this->{$RProperty->name} =& $pointer[$RProperty->name];
            }
        }

        foreach (self::PROPERTIES_TO_LINK as $property_name) {
            if (array_key_exists($property_name, $pointer)) {
                $this->{$property_name} =& $pointer[$property_name];
            }
        }

    }

    /**
     * Resets the properties of the object as provided in the array.
     * To be used only by the object\transaction
     * @param array $properties
     * @return void
     */
    public function _set_all_properties(array $properties): void
    {
        //we do not want to trigger the _before_set_propertyname hooks if there are such
        //the rollback must be transparent
        $this->disable_property_hooks();
        parent::_set_all_properties($properties);
        $this->enable_property_hooks();
    }

    public function disable_property_hooks(): void
    {
        $this->disable_property_hooks_flag = TRUE;
    }

    public function enable_property_hooks(): void
    {
        $this->disable_property_hooks_flag = FALSE;
    }

}