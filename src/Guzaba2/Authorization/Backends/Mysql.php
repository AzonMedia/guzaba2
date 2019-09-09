<?php
declare(strict_types=1);

namespace Guzaba2\Authorization\Backends;

// use Azonmedia\Utilities\ArrayUtil;
// use Guzaba2\Base\Exceptions\RunTimeException;
// use Guzaba2\Database\Interfaces\ConnectionInterface;
// use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
// use Guzaba2\Orm\Store\NullStore;
// use Guzaba2\Database\Sql\Mysql\Mysql as MysqlDB;
use \Azonmedia\Glog\Application\MysqlConnection;
use Guzaba2\Orm\Store\Database;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Authorization\Backends\Interfaces\IpBlackListBackendInterface;

class Mysql extends Database implements IpBlackListBackendInterface
{
    protected const CONFIG_DEFAULTS = [
        'main_table'    => 'offending_ips',
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * @var
     */
    protected $connection_class;

    public function __construct(string $connection_class)
    {
        parent::__construct();

        $this->connection_class = $connection_class;
    }

    /**
     * Returns all blacklisted ips
     * @return array
     */
    public function getBlacklistedIps() : array
    {
        print \Co::getCid().PHP_EOL;
        //echo "getBlacklistedIps 1\n";
        //print_r(self::ConnectionFactory());
        return [];
        $Connection = self::ConnectionFactory()->get_connection($this->connection_class, $CR);
        //echo "getBlacklistedIps 2\n";

        $q = "
SELECT
    *
FROM
    {$Connection::get_tprefix()}{self::CONFIG_RUNTIME['main_table']}
        ";

        $s = $Connection->prepare($q);

        $ret = $s->execute()->fetchAllAsArray();
        
        return $ret;
    }


    public function get_fallback_store() : ?StoreInterface
    {
    }

    public function &get_data_pointer(string $class, array $lookup_index) : array
    {
    }

    public function update_record(ActiveRecordInterface $ActiveRecord) : void
    {
    }
}
