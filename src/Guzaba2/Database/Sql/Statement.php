<?php
declare(strict_types=1);

namespace Guzaba2\Database\Sql;

use Guzaba2\Base\Base;
use Guzaba2\Database\Interfaces\StatementInterface;
use Guzaba2\Database\Sql\StatementTypes;

abstract class Statement extends Base implements StatementInterface
{

    protected const CONFIG_DEFAULTS = [

        //lock the table cache for the specified amount of time on DML queries
        //when the DML query is over the time will be updated again
        'update_query_cache_lock_timeout'       => 120,//in seconds

        'services'      => [
            'QueryCache'//if set means enable caching
        ],
    ];

    protected const CONFIG_RUNTIME = [];

    protected array $params = [];

    protected bool $is_executed_flag = FALSE;

    protected bool $disable_sql_cache_flag = FALSE;


    /**
     * @var array
     */
    protected array $cached_query_data = [];

    public function __set(string $param, /* mixed */ $value) : void
    {
        $this->params[$param] = $value;
    }

    public function __get(string $param) /* mixed */
    {
        return $this->params[$param];
    }

    public function __isset(string $param) : bool
    {
        return array_key_exists($param, $this->params);
    }

    public function __unset(string $param) : void
    {
        unset($this->params[$param]);
    }

    public function get_params() : array
    {
        return $this->params;
    }

    public function is_executed() : bool
    {
        return $this->is_executed_flag;
    }

    public function is_sql_cache_disabled() : bool
    {
        return $this->disable_sql_cache_flag;
    }

    /**
     * Returns the statement type - SELECT, UPDATE,...
     * @see self::STATEMENT_TYPE_MAP
     * Returns NULL if the statement type is not recognized.
     * @return int|NULL
     */
    public function getStatementType() : ?int
    {
        $sql = $this->get_query();
        return StatementTypes::getStatementType($sql);
    }

    /**
     * Returns the statement type group - DDL, DML,...
     * @see self::STATEMENT_TYPE_GROUP_MAP
     * Returns NULL is the statement type or group are not recognized.
     * @return int|NULL
     */
    public function getStatementGroup() : ?int
    {
        $sql = $this->get_query();
        return StatementTypes::getStatementGroup($sql);
    }

    /**
     * Returns whether this is a Select statement
     * @return bool
     */
    public function isSelectStatement() : bool
    {
        $sql = $this->get_query();
        return StatementTypes::isSelectStatement($sql);
    }

    /**
     * Returns whether this is a Insert statement
     * @return bool
     */
    public function isInsertStatement() : bool
    {
        $sql = $this->get_query();
        return StatementTypes::isInsertStatement($sql);
    }

    /**
     * Returns whether this is a Replace statement
     * @return bool
     */
    public function isReplaceStatement() : bool
    {
        $sql = $this->get_query();
        return StatementTypes::isReplaceStatement($sql);
    }

    /**
     * Returns whether this is a Update statement
     * @return bool
     */
    public function isUpdateStatement() : bool
    {
        $sql = $this->get_query();
        return StatementTypes::isUpdateStatement($sql);
    }

    /**
     * Returns whether this is a Delete statement
     * @return bool
     */
    public function isDeleteStatement() : bool
    {
        $sql = $this->get_query();
        return StatementTypes::isDeleteStatement($sql);
    }

    /**
     * Returns whether this is a DQL statement
     * @return bool
     */
    public function isDQLStatement() : bool
    {
        $sql = $this->get_query();
        return StatementTypes::isDQLStatement($sql);
    }

    /**
     * Returns whether this is a DML statement
     * @return bool
     */
    public function isDMLStatement() : bool
    {
        $sql = $this->get_query();
        return StatementTypes::isDMLStatement($sql);
    }

    /**
     * Returns whether this is a DDL statement
     * @return bool
     */
    public function isDDLStatement() : bool
    {
        $sql = $this->get_query();
        return StatementTypes::isDDLStatement($sql);
    }

    /**
     * Returns whether this is a DCL statement
     * @return bool
     */
    public function isDCLStatement() : bool
    {
        $sql = $this->get_query();
        return StatementTypes::isDCLStatement($sql);
    }

    /**
     * Returns whether this is a DAL statement
     * @return bool
     */
    public function isDALStatement() : bool
    {
        $sql = $this->get_query();
        return StatementTypes::isDALStatement($sql);
    }

    /**
     * Returns whether this is a TCL statement
     * @return bool
     */
    public function isTCLStatement() : bool
    {
        $sql = $this->get_query();
        return StatementTypes::isTCLStatement($sql);
    }
}
