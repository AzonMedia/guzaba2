<?php

namespace Guzaba2\Orm\Store\Sql;

use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Store\Database;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Store\NullStore;

class Mysql extends Database
{

    /**
     * @var StoreInterface|null
     */
    protected $FallbackStore;

    protected $connection_class;

    public function __construct(?StoreInterface $FallbackStore = NULL, ?string $connection_class = NULL)
    {
        parent::__construct();
        $this->FallbackStore = $FallbackStore ?? new NullStore();
        $this->connection_class = $connection_class;
    }

    public function get_record_structure(string $class) : array
    {
        $ret = $this->get_record_storage_structure($class);
        return $ret;
    }

    public function get_record_storage_structure(string $class) : array
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

    }

    public function &get_data_pointer( string $class, string $lookup_index) : array
    {

        //$Connection = self::ConnectionFactory()->get_connection($this->connection_class);



        //$Connection->free();

        $pointer =& $this->FallbackStore->get_data_pointer($class, $lookup_index);

        return $pointer;
    }

    //private function
}