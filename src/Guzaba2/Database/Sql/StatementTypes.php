<?php

declare(strict_types=1);

namespace Guzaba2\Database\Sql;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Database\Exceptions\SQLParsingException;
use Guzaba2\Translator\Translator as t;

/**
 * This is a helper class. It is only statically accessible. No instances can be created
 */
abstract class StatementTypes extends Base
{


    //public const STATEMENT_TYPE_EXPLAIN = ??;

    //data query language commands
    public const STATEMENT_TYPE_SELECT = 101;

    //data modification language commands
    public const STATEMENT_TYPE_INSERT = 201;
    public const STATEMENT_TYPE_UPDATE = 202;
    public const STATEMENT_TYPE_REPLACE = 203;
    public const STATEMENT_TYPE_DELETE = 204;

    //data definition language commands
    public const STATEMENT_TYPE_CREATE = 301;
    public const STATEMENT_TYPE_ALTER = 302;
    public const STATEMENT_TYPE_DROP = 303;
    public const STATEMENT_TYPE_COMMENT = 304;//not supported by MySQL
    public const STATEMENT_TYPE_RENAME = 305;
    public const STATEMENT_TYPE_TRUNCATE = 306;

    //data control language commands
    public const STATEMENT_TYPE_GRANT = 401;
    public const STATEMENT_TYPE_REVOKE = 402;

    //data administration language commands
    public const STATEMENT_TYPE_OPTIMIZE = 501;
    public const STATEMENT_TYPE_ANALYZE = 502;
    public const STATEMENT_TYPE_FLUSH = 503;
    public const STATEMENT_TYPE_RESET = 504;
    public const STATEMENT_TYPE_KILL = 505;
    public const STATEMENT_TYPE_SHOW = 506;
    public const STATEMENT_TYPE_SET = 507;

    //transaction control language commands
    public const STATEMENT_TYPE_BEGIN = 601;
    public const STATEMENT_TYPE_COMMIT = 602;
    public const STATEMENT_TYPE_ROLLBACK = 603;
    public const STATEMENT_TYPE_SAVEPOINT = 604;
    public const STATEMENT_TYPE_RELEASE = 605;


    /**
     * This map contains the mapping between the statement type and the statement command
     *
     */
    public const STATEMENT_TYPE_MAP = [
        //data query language commands
        self::STATEMENT_TYPE_SELECT => 'SELECT',

        //data modification language commands
        self::STATEMENT_TYPE_INSERT => 'INSERT',
        self::STATEMENT_TYPE_UPDATE => 'UPDATE',
        self::STATEMENT_TYPE_REPLACE => 'REPLACE',
        self::STATEMENT_TYPE_DELETE => 'DELETE',

        //data definition language commands
        self::STATEMENT_TYPE_CREATE => 'CREATE',
        self::STATEMENT_TYPE_ALTER => 'ALTER',
        self::STATEMENT_TYPE_DROP => 'DROP',
        self::STATEMENT_TYPE_COMMENT => 'COMMENT',//not supported by MySQL
        self::STATEMENT_TYPE_RENAME => 'RENAME',
        self::STATEMENT_TYPE_TRUNCATE => 'TRUNCATE',

        //data control language commands
        self::STATEMENT_TYPE_GRANT => 'GRANT',
        self::STATEMENT_TYPE_REVOKE => 'REVOKE',

        //data administration language commands
        self::STATEMENT_TYPE_OPTIMIZE => 'OPTIMIZE',
        self::STATEMENT_TYPE_ANALYZE => 'ANALYZE',
        self::STATEMENT_TYPE_FLUSH => 'FLUSH',
        self::STATEMENT_TYPE_RESET => 'RESET',
        self::STATEMENT_TYPE_KILL => 'KILL',
        self::STATEMENT_TYPE_SHOW => 'SHOW',
        self::STATEMENT_TYPE_SET => 'SET',

        //transaction control language commands
        self::STATEMENT_TYPE_BEGIN => 'BEGIN',
        self::STATEMENT_TYPE_COMMIT => 'COMMIT',
        self::STATEMENT_TYPE_ROLLBACK => 'ROLLBACK',
        self::STATEMENT_TYPE_SAVEPOINT => 'SAVEPOINT',
        self::STATEMENT_TYPE_RELEASE => 'RELEASE',

    ];

    /**
     * Data Query Language commands
     *
     */
    public const STATEMENT_GROUP_DQL = 1;

    /**
     * Data Manipulation Language commands
     *
     */
    public const STATEMENT_GROUP_DML = 2;

    /**
     * Data Definition Language commands
     *
     */
    public const STATEMENT_GROUP_DDL = 3;

    /**
     * Data Control Language commands
     *
     */
    public const STATEMENT_GROUP_DCL = 4;

    /**
     * Data Administration Language commands
     *
     */
    public const STATEMENT_GROUP_DAL = 5;

    /**
     * MemoryTransaction Control Language commands
     *
     */
    public const STATEMENT_GROUP_TCL = 6;

    /**
     * This map contains the mapping between the statement type and statement group
     *
     */
    public const STATEMENT_TYPE_GROUP_MAP = [

        //data query language commands
        self::STATEMENT_TYPE_SELECT => self::STATEMENT_GROUP_DQL,

        //data modification language commands
        self::STATEMENT_TYPE_INSERT => self::STATEMENT_GROUP_DML,
        self::STATEMENT_TYPE_UPDATE => self::STATEMENT_GROUP_DML,
        self::STATEMENT_TYPE_REPLACE => self::STATEMENT_GROUP_DML,
        self::STATEMENT_TYPE_DELETE => self::STATEMENT_GROUP_DML,

        //data definition language commands
        self::STATEMENT_TYPE_CREATE => self::STATEMENT_GROUP_DDL,
        self::STATEMENT_TYPE_ALTER => self::STATEMENT_GROUP_DDL,
        self::STATEMENT_TYPE_DROP => self::STATEMENT_GROUP_DDL,
        self::STATEMENT_TYPE_COMMENT => self::STATEMENT_GROUP_DDL,//not supported by MySQL
        self::STATEMENT_TYPE_RENAME => self::STATEMENT_GROUP_DDL,
        self::STATEMENT_TYPE_TRUNCATE => self::STATEMENT_GROUP_DDL,

        //data control language commands
        self::STATEMENT_TYPE_GRANT => self::STATEMENT_GROUP_DCL,
        self::STATEMENT_TYPE_REVOKE => self::STATEMENT_GROUP_DCL,

        //data administration language commands
        self::STATEMENT_TYPE_OPTIMIZE => self::STATEMENT_GROUP_DAL,
        self::STATEMENT_TYPE_ANALYZE => self::STATEMENT_GROUP_DAL,
        self::STATEMENT_TYPE_FLUSH => self::STATEMENT_GROUP_DAL,
        self::STATEMENT_TYPE_RESET => self::STATEMENT_GROUP_DAL,
        self::STATEMENT_TYPE_KILL => self::STATEMENT_GROUP_DAL,
        self::STATEMENT_TYPE_SHOW => self::STATEMENT_GROUP_DAL,
        self::STATEMENT_TYPE_SET => self::STATEMENT_GROUP_DAL,// is it of this type?

        //transaction control language commands
        self::STATEMENT_TYPE_BEGIN => self::STATEMENT_GROUP_TCL,
        self::STATEMENT_TYPE_COMMIT => self::STATEMENT_GROUP_TCL,
        self::STATEMENT_TYPE_ROLLBACK => self::STATEMENT_GROUP_TCL,
        self::STATEMENT_TYPE_SAVEPOINT => self::STATEMENT_GROUP_TCL,
        self::STATEMENT_TYPE_RELEASE => self::STATEMENT_GROUP_TCL,

    ];

    public const STATEMENT_GROUP_MAP = [
        self::STATEMENT_GROUP_DQL => 'DQL',
        self::STATEMENT_GROUP_DML => 'DML',
        self::STATEMENT_GROUP_DDL => 'DDL',
        self::STATEMENT_GROUP_DCL => 'DCL',
        self::STATEMENT_GROUP_DAL => 'DAL',
        self::STATEMENT_GROUP_TCL => 'TCL',
    ];

    /**
     * StatementTypes constructor.
     * @throws RunTimeException
     */
    public function __construct()
    {
        throw new RunTimeException(sprintf(t::_('No instances of class "%s" can be created.'), get_class($this)));
    }


    /**
     * Returns the statement type - SELECT, UPDATE,...
     * @param string $sql
     * @return int|NULL
     *
     * @throws SQLParsingException
     * @author vesko@azonmedia.com
     * @created 04.07.2018
     * @see self::STATEMENT_TYPE_MAP
     * Returns NULL if the statement type is not recognized.
     */
    public static function get_statement_type(string $sql): ?int
    {
        $ret = null;//unknown
        $sql = trim($sql);

        //if the first line begins with a comment - remove it (remove all lines starting with -- )
        if ($sql[0] === '-' && $sql[1] === '-') {
            $sql = trim(substr($sql, strpos($sql, PHP_EOL)));
        }

        //if the first character is ( check what is the next one)
        if ($sql[0] === '(') {
            $sql = trim(substr($sql, 1));
        }

        foreach (self::STATEMENT_TYPE_MAP as $statement_type => $statement_string) {
            if (stripos($sql, $statement_string) === 0) { // case insensitive to be on position 0
                $ret = $statement_type;
                break;
            }
        }
        if (!$ret) {
            $message = t::_('Unable to determine the SQL query type for query "%s".
Please update the parser StatementTypes::getStatementType() to recognize this query type or change the query so that is is recognizable.');
            throw new SQLParsingException(sprintf($message, $sql));
        }
        return $ret;
    }

    /**
     * Returns the statement type group - DDL, DML,...
     * @param string $sql
     * @return int|NULL
     *
     * @throws SQLParsingException
     * @author vesko@azonmedia.com
     * @created 04.07.2018
     * @see self::STATEMENT_TYPE_GROUP_MAP
     * Returns NULL is the statement type or group are not recognized.
     */
    public static function get_statement_group(string $sql): ?int
    {
        $ret = null;
        $this_statement_type = self::get_statement_type($sql);
        if ($this_statement_type !== null) {
            foreach (self::STATEMENT_TYPE_GROUP_MAP as $statement_type => $statement_group) {
                if ($this_statement_type === $statement_type) {
                    $ret = $statement_group;
                    break;
                }
            }
        }
        return $ret;
    }

    /**
     * Returns whether this is a Select statement
     * @param string $sql
     * @return bool
     *
     * @throws SQLParsingException
     */
    public static function is_select_statement(string $sql): bool
    {
        return self::get_statement_type($sql) === self::STATEMENT_TYPE_SELECT;
    }

    /**
     * Returns whether this is a Insert statement
     * @param string $sql
     * @return bool
     *
     * @throws SQLParsingException
     */
    public static function is_insert_statement(string $sql): bool
    {
        return self::get_statement_type($sql) === self::STATEMENT_TYPE_INSERT;
    }

    /**
     * Returns whether this is a Replace statement
     * @param string $sql
     * @return bool
     * @todo fix missing const
     *
     * @throws SQLParsingException
     */
    public static function is_replace_statement(string $sql): bool
    {
        return self::get_statement_type($sql) === self::STATEMENT_TYPE_REPLACE;
    }

    /**
     * Returns whether this is a Update statement
     * @param string $sql
     * @return bool
     *
     * @throws SQLParsingException
     */
    public static function is_update_statement(string $sql): bool
    {
        return self::get_statement_type($sql) === self::STATEMENT_TYPE_UPDATE;
    }

    /**
     * Returns whether this is a Delete statement
     * @param string $sql
     * @return bool
     *
     * @throws SQLParsingException
     */
    public static function is_delete_statement(string $sql): bool
    {
        return self::get_statement_type($sql) === self::STATEMENT_TYPE_DELETE;
    }

    /**
     * Returns whether this is a DQL statement
     * @param string $sql
     * @return bool
     *
     * @throws SQLParsingException
     */
    public static function is_dql_statement(string $sql): bool
    {
        return self::get_statement_group($sql) === self::STATEMENT_GROUP_DQL;
    }

    /**
     * Returns whether this is a DML statement
     * @param string $sql
     * @return bool
     *
     * @throws SQLParsingException
     */
    public static function is_dml_statement(string $sql): bool
    {
        return self::get_statement_group($sql) === self::STATEMENT_GROUP_DML;
    }

    /**
     * Returns whether this is a DDL statement
     * @param string $sql
     * @return bool
     *
     * @throws SQLParsingException
     */
    public static function is_ddl_statement(string $sql): bool
    {
        return self::get_statement_group($sql) === self::STATEMENT_GROUP_DDL;
    }

    /**
     * Returns whether this is a DCL statement
     * @param string $sql
     * @return bool
     *
     * @throws SQLParsingException
     */
    public static function is_dcl_statement(string $sql): bool
    {
        return self::get_statement_group($sql) === self::STATEMENT_GROUP_DCL;
    }

    /**
     * Returns whether this is a DAL statement
     * @param string $sql
     * @return bool
     *
     * @throws SQLParsingException
     */
    public static function is_dal_statement(string $sql): bool
    {
        return self::get_statement_group($sql) === self::STATEMENT_GROUP_DAL;
    }

    /**
     * Returns whether this is a TCL statement
     * @param string $sql
     * @return bool
     *
     * @throws SQLParsingException
     */
    public static function is_tcl_statement(string $sql): bool
    {
        return self::get_statement_group($sql) === self::STATEMENT_GROUP_TCL;
    }
}
