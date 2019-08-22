<?php

namespace Guzaba2\Orm\Store\Sql;

use Azonmedia\Utilities\ArrayUtil;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Store\Database;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Store\NullStore;

use Guzaba2\Database\Sql\Mysql\Mysql as MysqlDB;

class Mysql extends Database
{

    /**
     * @var StoreInterface|null
     */
    protected $FallbackStore;

    protected $connection_class;

    public function __construct(StoreInterface $FallbackStore, ?string $connection_class = NULL)
    {
        parent::__construct();
        $this->FallbackStore = $FallbackStore ?? new NullStore();
        $this->connection_class = $connection_class;
    }

    /**
     * Returns a unified structure
     * @param string $class
     * @return array
     */
    public function get_unified_columns_data(string $class) : array
    {
        $storage_structure_arr = $this->get_storage_columns_data($class);

        return $this->unify_columns_data($storage_structure_arr);
    }
    
    
    /**
     * Returns a unified structure
     * @param string $class
     * @return array
     */
    public function get_unified_columns_data_by_table_name(string $table_name) : array
    {
        $storage_structure_arr = $this->get_storage_columns_data_by_table_name($table_name);

        return $this->unify_columns_data($storage_structure_arr);
    }
    
    /**
    * Returns the backend storage structure
    * @param string $table_name
    * @return array
    */
    
    public function get_storage_columns_data_by_table_name(string $table_name) : array
    {
        $Connection = self::ConnectionFactory()->get_connection($this->connection_class, $CR);

        $q = "
SELECT
    information_schema.columns.*
FROM
    information_schema.columns
WHERE
    table_schema = :table_schema
    AND table_name = :table_name
ORDER BY
    ordinal_position ASC
        ";
        $s = $Connection->prepare($q);
        $s->table_schema = $Connection::get_database();
        $s->table_name = $Connection::get_tprefix().$table_name;

        $ret = $s->execute()->fetchAll();
        
        return $ret;
    }

    /**
     * Returns the backend storage structure
     * @param string $class
     * @return array
     */
    public function get_storage_columns_data(string $class) : array
    {
        $ret = $this->get_storage_columns_data_by_table_name($class::get_main_table());

        if (!count($ret)) {
            //look for the next storage
            $ret = $this->FallbackStore->get_record_structure($class);
            //needs to update the local storage...meaning creating a table...
            //TODO - either cache the structure or create...
            //not implemented
        }

        return $ret;
    }
    
    /**
     *
     * @param array $storage_structure_arr
     * @return array
     */
    public function unify_columns_data(array $storage_structure_arr) : array
    {
        $ret = [];
        for ($aa=0; $aa<count($storage_structure_arr); $aa++) {
            $column_structure_arr = $storage_structure_arr[$aa];
            $ret[$aa] = [
                'name'                  => strtolower($column_structure_arr['COLUMN_NAME']),
                'native_type'           => $column_structure_arr['DATA_TYPE'],
                'php_type'              => MysqlDB::TYPES_MAP[$column_structure_arr['DATA_TYPE']],
                'size'                  => MysqlDB::get_column_size($column_structure_arr),
                'nullable'              => $column_structure_arr['IS_NULLABLE'] === 'YES',
                'column_id'             => (int) $column_structure_arr['ORDINAL_POSITION'],
                'primary'               => $column_structure_arr['COLUMN_KEY'] === 'PRI',
                'default_value'         => $column_structure_arr['COLUMN_DEFAULT'] === 'NULL' ? NULL : $column_structure_arr['COLUMN_DEFAULT'],
                'autoincrement'         => $column_structure_arr['EXTRA'] === 'auto_increment',
            ];
            settype($ret[$aa]['default_value'], $ret[$aa]['php_type']);
            
            ArrayUtil::validate_array($ret[$aa], parent::UNIFIED_COLUMNS_STRUCTURE);
        }
        
        return $ret;
    }

    public function add_instance(ActiveRecordInterface $ActiveRecord) : string
    {
        $Connection = self::ConnectionFactory()->get_connection($this->connection_class, $CR);
        //save data to DB
    }

    public function &get_data_pointer(string $class, array $lookup_index) : array
    {
        //initialization
        $record_data = $this->get_record_structure($this->get_unified_columns_data($class));
        
        //lookup in DB

        $Connection = self::ConnectionFactory()->get_connection($this->connection_class, $CR);

        //pull data from DB
        //set the data to $record_data['data']
        //set the meta data to $record_data['meta'];

        $j = [];//an array containing all the tables that need to be INNER JOINED
        //needs to be associative as we may join unwillingdfully multiple times the same table
        //the key is the name as which the join will be done and the value is the actual table name
        //so the join will look like JOIN key AS value
        $w = [];//array containing the where clauses
        $b = [];//associative array with the variables that must have values bound

        //we need to always join all the main tables
        //otherwise the loaded object will be mising properties
        //but these can be loaded on request
        //so if we
        
        $table_name = $class::get_main_table();
        
        //the main table must be always loaded
        $j[$class::get_main_table()] = $Connection::get_tprefix().$class::get_main_table();//if it gets assigned multiple times it will overwrite it
        //as it may happen the WHERE index provided to get_instance to be from other shards
        
        //if($this->is_ownership_table($table_name)){
            
        //}
        
        
        $main_index = $class::get_primary_index_columns();
        //$index = [$main_index[0] => $lookup_index];

        foreach ($lookup_index as $field_name=>$field_value) {
            if (!is_string($field_name)) {
                //perhaps get_instance was provided like this [1,2] instead of ['col1'=>1, 'col2'=>2]... The first notation may get supported in future by inspecting the columns and assume the order in which the primary index is provided to be correct and match it
                throw new framework\base\exceptions\runTimeException(sprintf(t::_('It seems wrong values were provided to object instance. The provided array must contain keys with the column names and values instead of just values. Please use new %s([\'col1\'=>1, \'col2\'=>2]) instead of new %s([1,2]).'), $class, $class, $class));
            }

            if (!array_key_exists($field_name, $record_data)) {
                throw new framework\base\exceptions\runTimeException(sprintf(t::_('A field named "%s" that does not exist is supplied to the constructor of an object of class "%s".'), $field_name, $class));
            }

            //TODO IVO add owners_table, meta table

            $j[$table_name] = $Connection::get_tprefix().$table_name;//if it gets assigned multiple times it will overwrite it
            //$w[] = "{$table_name}.{$field_name} {$this->db->equals($field_value)} :{$field_name}";
            //$b[$field_name] = $field_value;
            if (is_null($field_value)) {
                $w[] = "{$class::get_main_table()}.{$field_name} {$Connection::equals($field_value)} NULL";
            } else {
                $w[] = "{$class::get_main_table()}.{$field_name} {$Connection::equals($field_value)} :{$field_name}";
                $b[$field_name] = $field_value;
            }
        } //end foreach

        //here we join the tables and load only the data from the joined tables
        //this means that some tables / properties will not be loaded - these will be loaded on request
        //$j_str = implode(" INNER JOIN ", $j);//cant do this way as now we use keys
        //the key is the alias of the table, the value is the real full name of the table (including the prefix)
        $j_alias_arr = [];
        foreach ($j as $table_alias=>$full_table_name) {

            //and the class_id & object_id are moved to the WHERE CLAUSE
            if ($table_alias == $table_name) {
                //do not add ON clause - this is the table containing the primary index and the first shard
                $on_str = "";
            } elseif ($table_alias == 'ownership_table') {
                $on_arr = [];

                $on_arr[] = "ownership_table.class_id = :class_id";
                $b['class_id'] = static::_class_id;

                $w[] = "ownership_table.object_id = {$table_name}.{$main_index[0]}";//the ownership table does not support compound primary index

                $on_str = "ON ".implode(" AND ", $on_arr);
            } else {
                $on_arr = [];
                foreach ($main_index as $column_name) {
                    $on_arr[] = "{$table_alias}.{$column_name} = {$table_name}.{$column_name}";
                }
                $on_str = "ON ".implode(" AND ", $on_arr);
            }
            $j_alias_arr[] = "`{$full_table_name}` AS `{$table_alias}` {$on_str}";
            //$this->data_is_loaded_from_tables[] = $table_alias;
        }

        $j_str = implode(PHP_EOL."\t"."LEFT JOIN ", $j_alias_arr);//use LEFT JOIN as old record will have no data in the new shards
        unset($j, $j_alias_arr);
        $w_str = implode(" AND ", $w);
        unset($w);
        $q = "
SELECT 
*
FROM
{$j_str}
WHERE
{$w_str}
";

        $Statement = $Connection->prepare($q);
        $Statement->execute($b);
        $data = $Statement->fetchAll();
        
        if (count($data)) {
            //TODO meta data object onwenrs table, i will set it manuly until save() is finished
            $record_data['meta']['updated_microtime'] = time();
            //TODO IVO [0]
            $record_data['data'] = $data[0];
        } else {
            //TODO IVO may be should be moved in ActiveRecord
            throw new framework\orm\exceptions\missingRecordException(sprintf(t::_('The required object of class "%s" with index "%s" does not exist.'), $class, var_export($lookup_index, true)));
        }

        return $record_data;
    }
    //private function
}
