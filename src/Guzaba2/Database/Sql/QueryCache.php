<?php
declare(strict_types=1);

namespace Guzaba2\Database\Sql;

use Azonmedia\Utilities\ArrayUtil;
use Guzaba2\Base\Base;
use Guzaba2\Cache\Interfaces\CacheInterface;
use Guzaba2\Cache\Interfaces\IntCacheInterface;
use Guzaba2\Cache\Interfaces\ProcessCacheInterface;
use Guzaba2\Database\PdoStatement;
use Guzaba2\Database\Transaction;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Patterns\Singleton;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Transaction\TransactionManager;
use Psr\Log\LogLevel;

/**
 * Class QueryCache
 * @package Guzaba2\Database
 */
class QueryCache extends Base
{
    //protected const CACHE_TTL = 3600;
    //protected const CACHE_QUERIES_CONTAINING_RAND = false;//never cache these
    //protected const CACHE_RESULT_MATRICES_UP_TO_ELEMENTS = 10000;

    protected const CONFIG_DEFAULTS = [
        'cache_queries_containing_rand'         => FALSE,
        'cache_result_matrices_up_to_elements'  => 10000,
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * A global cache used to store the microtime of the tables modifications
     * @var ProcessCacheInterface
     */
    private ProcessCacheInterface $TimeCache;

    /**
     * Used to store the query results
     * @var CacheInterface
     */
    private CacheInterface $Cache;

    public function __construct(ProcessCacheInterface $TimeCache, CacheInterface $Cache)
    {
        $this->TimeCache = $TimeCache;
        $this->Cache = $Cache;
    }

    /**
     * @param string $table
     * @param int $add_time
     * @todo fix cache method
     */
    public function update_table_modification_microtime(string $table, int $add_time = 0): void
    {
        $microtime = ($add_time + microtime(TRUE) ) * 1_000_000;
        $microtime = (int) $microtime;
        $this->TimeCache->set('table',$table, $microtime);//no TTL for the table update time - the tables are a limited amount and small data is cached
    }

    public function get_table_modification_microtime(string $table): ?int
    {
        return $this->TimeCache->get('table',$table);
    }

    public function update_tables_modification_microtime(string $sql, int $add_time = 0): void
    {
        $tables = self::get_tables_from_sql($sql);
        foreach ($tables as $table) {
            self::update_table_modification_microtime($table, $add_time);
        }
    }

    //public function add_cached_data(string $sql, array $params, \Countable $query_data, ?int $found_rows = NULL): void
    public function add_cached_data(string $sql, array $params, iterable $query_data): void
    {
        if (preg_match('/RAND\s*\(\)/i', $sql) && !self::CONFIG_RUNTIME['cache_queries_containing_rand']) {
            return;
        }

        $rows = count($query_data);
        if (isset($query_data[0])) {
            $columns = count($query_data[0]);
        }
        if ($rows && $columns && $rows * $columns > self::CONFIG_RUNTIME['cache_result_matrices_up_to_elements']) {
            return;//the result set is too big to be cached
        }

        //$query_key = 'query_' . md5($sql) . '|' . md5(ArrayUtil::array_as_string($params));
        $query_key =  md5($sql) . '|' . md5(ArrayUtil::array_as_string($params));
        $query_data = [
            'cached_microtime' => (int) microtime(true) * 1_000_000,
            'data' => $query_data,
            //'found_rows' => $found_rows,
        ];

        //$this->Cache->add($query_key, $query_data, self::CACHE_TTL);
        //$this->Cache->add('query', $query_key, $query_data, self::CACHE_TTL);
        $this->Cache->set('query', $query_key, $query_data);
    }

    public function get_cached_data(string $sql, array $params): ?iterable
    {
        $ret = NULL;
        $cache_enabled = TRUE;

//TODO - implement
//        if (TransactionManager::getCurrentTransaction(MemoryTransaction::class)) {
//            //there is a current transaction
//            if (!PdoStatement::ENABLE_SELECT_CACHING_DURING_TRANSACTION) {
//                //the caching of select queries during transaction is disabled
//                return $ret;
//            }
//        }

        //check is the result of the query already cached - if it is DO NOT execute the query - just set it as executed and later retrieve the cached value in fetch* methods
        //the cache invalidation will be based on parsinge the query and invalidating all queries that contain a table
        //this means that a key based on hash will not work... a way to query the cache not just based on key is needed

        //retrieve all tables from this query
        //but instead of using the PHPSQLParser which is too slow just use a simple regex

        $tables = self::get_tables_from_sql($sql);

        if (!count($tables)) {
            //no tabled were found - this query cant be cached
            $cache_enabled = FALSE;
        }

        //queries containing RAND() cant be cached either
        //if (stripos($sql,'rand()') !== FALSE) {
        if (preg_match('/RAND\s*\(\)/i', $sql) && !self::CONFIG_RUNTIME['cache_queries_containing_rand']) {
            $cache_enabled = FALSE;
        }

        if ($cache_enabled) {
            //$query_key = 'query_' . md5($sql) . '|' . md5(ArrayUtil::array_as_string($params));
            //$query_data = self::cache()->get_value($query_key);//contains cached microtime
            $query_key = md5($sql) . '|' . md5(ArrayUtil::array_as_string($params));
            $query_data = $this->Cache->get('query', $query_key);//contains cached microtime
            //check the invalidation times for each individual table
            //if the last time a table has been updated is after thi time then this cached data is no longer valid
            if ($query_data) {
                foreach ($tables as $table) {
                    //$table_key = 'table_'.$table;
                    //$table_updated_microtime = (int) self::cache()->get_value($table_key);
                    $table_updated_microtime = self::get_table_modification_microtime($table);
                    if (!isset($query_data['cached_microtime']) || $table_updated_microtime >= $query_data['cached_microtime']) {
                        $cache_enabled = FALSE;//there is a table that was updated after this query was cached
                        break;
                    }
                }
            }
        }

        if ($cache_enabled && $query_data) {
            $ret = $query_data;
            Kernel::log(sprintf(t::_('%1$s: The result of query "%2$s" was found in cache.'), __CLASS__, substr($sql, 0, 200).'...' ), LogLevel::DEBUG);
        }

        return $ret;
    }

    /**
     * Returns an array of the tables used in the provided $sql
     * @param string $sql
     * @return array
     */
    public static function get_tables_from_sql(string $sql): array
    {
        $tables = [];
        preg_match_all('/(FROM|JOIN|INTO|(?<!KEY )UPDATE)\s+`*guzaba_([_A-Z0-9]+)`*\s+/imu', $sql, $matches);
        foreach ($matches[2] as $match) {
            $tables[] = trim($match);
        }

        return $tables;
    }

    public function get_stats(): array
    {
        return $this->Cache->get_stats('query');
    }

    public function clear_cache(int $percentage = 100): int
    {
        return $this->Cache->clear_cache('query', $percentage);
    }
}
