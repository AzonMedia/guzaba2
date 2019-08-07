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

    /*
    public function get_record_structure(string $class) : array
    {
        $ret = [];
        $unified_columns_data_arr = $this->get_unified_columns_data($class);
        foreach ($unified_columns_data_arr as $column_data_arr) {
            $ret[$column_data_arr['name']] = $column_data_arr['default_value'];
        }
        return $ret;
    }
    */

    /**
     * Returns a unified structure
     * @param string $class
     * @return array
     */
    public function get_unified_columns_data(string $class) : array
    {
        $storage_structure_arr = $this->get_storage_columns_data($class);

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

    /**
     * Returns the backend storage structure
     * @param string $class
     * @return array
     */
    public function get_storage_columns_data(string $class) : array
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
        $s->table_name = $Connection::get_tprefix().$class::get_main_table();

        $ret = $s->execute()->fetchAll();

        if (!count($ret)) {
            //look for the next storage
            $ret = $this->FallbackStore->get_record_structure($class);
            //needs to update the local storage...meaning creating a table...
            //TODO - either cache the structure or create...
            //not implemented
        }

        return $ret;
    }

    public function add_instance(ActiveRecordInterface $ActiveRecord) : string
    {
        $Connection = self::ConnectionFactory()->get_connection($this->connection_class, $CR);
        //save data to DB
    }

    public function &get_data_pointer(string $class, string $lookup_index) : array
    {

        //initialization
        $record_data = $this->get_record_structure($this->get_unified_columns_data($class));

        //lookup in DB
        if ($lookup_index) {
            $Connection = self::ConnectionFactory()->get_connection($this->connection_class, $CR);
            //pull data from DB
            //set the data to $record_data['data']
            //set the meta data to $record_data['meta'];
        }


        return $record_data;
    }

    //private function
}
