<?php

namespace Guzaba2\Orm;

use Azonmedia\Reflection\ReflectionClass;

use Guzaba2\Kernel\Kernel;
use Guzaba2\Object\GenericObject;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Store\Memory;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Translator\Translator as t;



use Guzaba2\Orm\Traits\ActiveRecordOverloading;
use Guzaba2\Orm\Traits\ActiveRecordSave;
use Guzaba2\Orm\Traits\ActiveRecordLoad;
use Guzaba2\Orm\Traits\ActiveRecordStructure;

//use Guzaba2\Orm\Traits\ActiveRecordValidation;
//use Guzaba2\Orm\Traits\ActiveRecordDynamicProperties;
//use Guzaba2\Orm\Traits\ActiveRecordDelete;


class ActiveRecord extends GenericObject implements ActiveRecordInterface
{
    const PROPERTIES_TO_LINK = ['is_new_flag', 'was_new_flag', 'data'];


    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'ConnectionFactory',
            'OrmStore',
        ]
    ];

    protected const CONFIG_RUNTIME = [];

    //for the porpose of splitting and organising the methods (as this class would become too big) traits are used
    use ActiveRecordOverloading;
    use ActiveRecordSave;
    //use ActiveRecordLoad;
    use ActiveRecordStructure;

    /**
     * @var StoreInterface
     */
    protected $Store;

    /**
     * @var bool
     */
    protected $is_new_flag = FALSE;

    /**
     * @var bool
     */
    protected $was_new_flag = FALSE;

    /**
     * @var bool
     */
    protected $is_modified_flag = FALSE;

    /**
     * @var array
     */
    protected $record_data = [];

    /**
     * @var array
     */
    protected $record_modified_data = [];

    /**
     * @var array
     */
    protected $meta_data = [];

    /**
     * @var mixed
     */
    //protected $index;

    /**
     * @var bool
     */
    protected $disable_property_hooks_flag = FALSE;

    protected $disable_method_hooks_flag = FALSE;

    protected $validation_is_disabled_flag = FALSE;

    /**
     * Contains the unified record structure for this class.
     * @see StoreInterface::UNIFIED_COLUMNS_STRUCTURE
     * While in Swoole/coroutine context static variables shouldnt be used here it is acceptable as this structure is not expected to change ever during runtime once it is assigned.
     * @var array
     */
    protected static $columns_data = [];

    /**
     * Indexed array containing the
     * @var array
     */
    protected static $primary_index_columns = [];


    //public function __construct(StoreInterface $Store)

    /**
     * ActiveRecord constructor.
     * @param $index
     * @param StoreInterface|null $Store
     * @throws \ReflectionException
     * @throws RunTimeException
     */
    public function __construct(/* mixed*/ $index, ?StoreInterface $Store = NULL)
    {
        parent::__construct();

        if (!isset(static::CONFIG_RUNTIME['main_table'])) {
            throw new RunTimeException(sprintf(t::_('ActiveRecord class %s does not have "main_table" entry in its CONFIG_RUNTIME.'), get_called_class()));
        }

        if ($Store) {
            $this->Store = $Store;
        } else {
            $this->Store = static::OrmStore();//use the default service
        }

        if (empty(self::$columns_data)) {
            $unified_columns_data = $this->Store->get_unified_columns_data(get_class($this));
            //Kernel::dump($unified_columns_data);
            foreach ($unified_columns_data as $column_datum) {
                self::$columns_data[$column_datum['name']] = $column_datum;
            }
        }
        if (empty(self::$primary_index_columns)) {
            foreach (self::$columns_data as $column_name=>$column_data) {
                if (!empty($column_data['primary'])) {
                    self::$primary_index_columns[] = $column_name;
                }
            }
        }

        if ($index) {
            $pointer =& $this->Store->get_data_pointer(get_class($this), $index);
            $this->record_data =& $pointer['data'];
            $this->meta_data =& $pointer['meta'];
        } else {
            $this->record_data = $this->Store::get_record_structure(self::$columns_data);
        }



        //all properties defined in this class must be references to the store in MemoryCache
        //if new properties are defined these will be contained in this instance, instead of being referenced in the Store
        //the Store contains only the ORM properties
//        $RClass = new ReflectionClass($this);
//        $properties = $RClass->getOwnDynamicProperties();
//        foreach ($properties as $RProperty) {
//            if (array_key_exists($RProperty->name, $pointer)) {
//                $this->{$RProperty->name} =& $pointer[$RProperty->name];
//            }
//        }

        //do not link these - these will stay separate for each instance
//        foreach (self::PROPERTIES_TO_LINK as $property_name) {
//            if (array_key_exists($property_name, $pointer)) {
//                $this->{$property_name} =& $pointer[$property_name];
//            }
//        }
    }

    /**
     * Returns the primary index for the object.
     * Returns an array if the primary index is from multiple columns.
     */
    public function get_index() /* mixed */
    {
        $primary_index_columns = self::$primary_index_columns;
        if (count($primary_index_columns) === 1) {
            $ret = $this->record_data[$primary_index_columns[0]];
        } else {
            foreach ($primary_index_columns as $primary_index_column) {
                $ret[] = $this->record_data[$primary_index_column];
            }
        }
        return $ret;
    }

    public static function get_primary_index_columns() : array
    {
        return self::$primary_index_columns;
    }

    public static function get_main_table() : string
    {
        return static::CONFIG_RUNTIME['main_table'];
    }

    public function save() : ActiveRecord
    {
    }
}
