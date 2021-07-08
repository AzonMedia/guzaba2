<?php

declare(strict_types=1);

namespace Guzaba2\Database\Sql;

use Guzaba2\Base\Base;
use Guzaba2\Database\Interfaces\StatementInterface;
use Guzaba2\Database\Sql\StatementTypes;

/**
 * Class Statement
 * @package Guzaba2\Database\Sql
 */
abstract class Statement extends Base implements StatementInterface
{

    protected const CONFIG_DEFAULTS = [

        //lock the table cache for the specified amount of time on DML queries
        //when the DML query is over the time will be updated again
        'update_query_cache_lock_timeout'       => 120,//in seconds
        'slow_query_log_msec'                   => 50,//msec, log queries that execute in under X msec

        'services'      => [
            'QueryCache',//if set means enable caching
            'Apm',
        ],
    ];

    protected const CONFIG_RUNTIME = [];

    protected array $params = [];

    protected bool $is_executed_flag = false;

    protected bool $disable_sql_cache_flag = false;


    /**
     * @var array
     */
    protected array $cached_query_data = [];

    public function __set(string $param, /* mixed */ $value): void
    {
        $this->params[$param] = $value;
    }

    public function __get(string $param) /* mixed */
    {
        return $this->params[$param];
    }

    public function __isset(string $param): bool
    {
        return array_key_exists($param, $this->params);
    }

    public function __unset(string $param): void
    {
        unset($this->params[$param]);
    }

    public function get_params(): array
    {
        return $this->params;
    }

    public function is_executed(): bool
    {
        return $this->is_executed_flag;
    }

    public function is_sql_cache_disabled(): bool
    {
        return $this->disable_sql_cache_flag;
    }

    /**
     * Returns the statement type - SELECT, UPDATE,...
     * @see self::STATEMENT_TYPE_MAP
     * Returns NULL if the statement type is not recognized.
     * @return int|NULL
     */
    public function get_statement_type(): ?int
    {
        $sql = $this->get_query();
        return StatementTypes::get_statement_type($sql);
    }

    /**
     * Returns the statement type group - DDL, DML,...
     * @see self::STATEMENT_TYPE_GROUP_MAP
     * Returns NULL is the statement type or group are not recognized.
     * @return int|NULL
     */
    public function get_statement_group(): ?int
    {
        $sql = $this->get_query();
        return StatementTypes::get_statement_group($sql);
    }


    /**
     * Returns the statement group type as string ('DQL','DML')
     * @return string|null
     */
    public function get_statement_group_as_string(): ?string
    {
        $ret = null;
        $statement_group_type = $this->get_statement_group();
        if ($statement_group_type) {
            $ret = StatementTypes::STATEMENT_GROUP_MAP[$statement_group_type];
        }
        return $ret;
    }

    /**
     * Returns whether this is a Select statement
     * @return bool
     */
    public function is_select_statement(): bool
    {
        $sql = $this->get_query();
        return StatementTypes::is_select_statement($sql);
    }

    /**
     * Returns whether this is a Insert statement
     * @return bool
     */
    public function is_insert_statement(): bool
    {
        $sql = $this->get_query();
        return StatementTypes::is_insert_statement($sql);
    }

    /**
     * Returns whether this is a Replace statement
     * @return bool
     */
    public function is_replace_statement(): bool
    {
        $sql = $this->get_query();
        return StatementTypes::is_replace_statement($sql);
    }

    /**
     * Returns whether this is a Update statement
     * @return bool
     */
    public function is_update_statement(): bool
    {
        $sql = $this->get_query();
        return StatementTypes::is_update_statement($sql);
    }

    /**
     * Returns whether this is a Delete statement
     * @return bool
     */
    public function is_delete_statement(): bool
    {
        $sql = $this->get_query();
        return StatementTypes::is_delete_statement($sql);
    }

    /**
     * Returns whether this is a DQL statement
     * @return bool
     */
    public function is_dql_statement(): bool
    {
        $sql = $this->get_query();
        return StatementTypes::is_dql_statement($sql);
    }

    /**
     * Returns whether this is a DML statement
     * @return bool
     */
    public function is_dml_statement(): bool
    {
        $sql = $this->get_query();
        return StatementTypes::is_dml_statement($sql);
    }

    /**
     * Returns whether this is a DDL statement
     * @return bool
     */
    public function is_ddl_statement(): bool
    {
        $sql = $this->get_query();
        return StatementTypes::is_ddl_statement($sql);
    }

    /**
     * Returns whether this is a DCL statement
     * @return bool
     */
    public function is_dcl_statement(): bool
    {
        $sql = $this->get_query();
        return StatementTypes::is_dcl_statement($sql);
    }

    /**
     * Returns whether this is a DAL statement
     * @return bool
     */
    public function is_dal_statement(): bool
    {
        $sql = $this->get_query();
        return StatementTypes::is_dal_statement($sql);
    }

    /**
     * Returns whether this is a TCL statement
     * @return bool
     */
    public function is_tcl_statement(): bool
    {
        $sql = $this->get_query();
        return StatementTypes::is_tcl_statement($sql);
    }
}
