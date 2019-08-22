<?php
declare(strict_types=1);
/*
 * Guzaba Framework
 * http://framework.guzaba.org
 *
 * This source file is subject to the BSD license that is bundled with this
 * package in the file LICENSE.txt and available also at:
 * http://www.opensource.org/licenses/bsd-license.php
 *
 */

/**
 * @category    Guzaba Framework
 * @package        Database
 * @subpackage    PDO
 * @copyright    Copyright (c) Guzaba Ltd - http://guzaba.com
 * @license        http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author        Vesselin Kenashkov <vesko@webstudiobulgaria.com>
 */

namespace org\guzaba\framework\database\classes;

use Azonmedia\Utilities\ArrayUtil;
use Guzaba2\Database\PdoStatement;
use Guzaba2\Database\Transaction;
use Guzaba2\Patterns\Singleton;
use Guzaba2\Transaction\TransactionManager;

/**
 * Class QueryCache
 * @package org\guzaba\framework\database\classes
 * @todo refactor and re-implement
 */
final class QueryCache extends Singleton
{
    protected const CACHE_TTL = 3600;
    protected const CACHE_QUERIES_CONTAINING_RAND = false;
    protected const CACHE_RESULT_MATRICES_UP_TO_ELEMENTS = 10000;

    /**
     * @param string $table
     * @param int $add_time
     * @todo fix cache method
     */
    public function update_table_modification_microtime(string $table, int $add_time = 0): void
    {
        $table_key = 'table_' . $table;
        $microtime = $add_time + microtime(true);
        self::cache()->cache_value($table_key, $microtime);//no TTL for the table update time - the tables are a limited amount and small data is cached
    }

    public function get_table_modification_microtime(string $table): float
    {
        $table_key = 'table_' . $table;
        $table_updated_microtime = (float)self::cache()->get_value($table_key);

        return $table_updated_microtime;
    }

    public function update_tables_modification_microtime(string $sql, int $add_time = 0): void
    {
        $tables = self::get_tables_from_sql($sql);
        //print $sql.PHP_EOL;

        foreach ($tables as $table) {
            self::update_table_modification_microtime($table, $add_time);
        }
    }

    public function add_cached_data(string $sql, array $params, \Countable $query_data, ?int $found_rows = NULL): void
    {
        if (preg_match('/RAND\s*\(\)/i', $sql) && !self::CACHE_QUERIES_CONTAINING_RAND) {
            return;
        }

        $rows = count($query_data);
        if (isset($query_data[0])) {
            $columns = count($query_data[0]);
        }
        if ($rows && $columns && $rows * $columns > self::CACHE_RESULT_MATRICES_UP_TO_ELEMENTS) {
            return;//the result set is too big to be cached
        }

        $cache = framework\services\classes\services::cache();

        $query_key = 'query_' . md5($sql) . '|' . md5(ArrayUtil::array_as_string($params));
        $query_data = [
            'cached_microtime' => microtime(true),
            'data' => $query_data,
            'found_rows' => $found_rows,
        ];

        $cache->cache_value($query_key, $query_data, self::CACHE_TTL);
    }

    public function get_cached_data(string $sql, array $params): ?iterable
    {
        $ret = NULL;
        $cache_enabled = TRUE;

        if (TransactionManager::getCurrentTransaction(Transaction::class)) {
            //there is a current transaction
            if (!PdoStatement::ENABLE_SELECT_CACHING_DURING_TRANSACTION) {
                //the caching of select queries during transaction is disabled
                return $ret;
            }
        }

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
        if (preg_match('/RAND\s*\(\)/i', $sql)) {
            $cache_enabled = FALSE;
        }

        if ($cache_enabled) {
            $query_key = 'query_' . md5($sql) . '|' . md5(ArrayUtil::array_as_string($params));
            $query_data = self::cache()->get_value($query_key);//contains cached microtime

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

    public static function get_instance(): \Guzaba2\Patterns\Interfaces\SingletonInterface
    {
        // TODO: Implement get_instance() method.
    }

    public static function get_instances(): array
    {
        // TODO: Implement get_instances() method.
    }

    public function destroy(): void
    {
        // TODO: Implement destroy() method.
    }
}
