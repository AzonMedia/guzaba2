<?php

namespace Guzaba2\Orm;

use Azonmedia\Reflection\ReflectionClass;
use Guzaba2\Base\Base;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;

class ActiveRecord extends Base
{

    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'ConnectionFactory'
        ]
    ];

    protected const CONFIG_RUNTIME = [];


    /**
     * @var StoreInterface
     */
    protected static $Store;

    public $is_new_flag = FALSE;

    protected $was_new_flag = FALSE;

    public $data = [];

    protected $index;

    const PROPERTIES_TO_LINK = ['is_new_flag', 'was_new_flag', 'data'];



    public static function _initialize_class()
    {
        self::$Store = new \Guzaba2\Orm\Store\Memory();//move to DI

    }

    //public function __construct(StoreInterface $Store)
    public function __construct( /* mixed*/ $index)
    {
        parent::__construct();

        //$this->Store = $Store;


        $this->index = $index;
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

}