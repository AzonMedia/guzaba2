<?php
declare(strict_types=1);
/*
 * Guzaba Framework
 * http://framework.guzaba.org
 *
 * This source file is subject to the BSD license that is bundled with this
 * package in the file LICENSE.txt and available also at:
 * http://www.opensource.org/licenses/bsd-license.php
 */

/**
 * Description of statement
 * @category    Guzaba Framework
 * @package        Database
 * @subpackage    Absraction
 * @copyright    Copyright (c) Guzaba Ltd - http://guzaba.com
 * @license        http://www.opensource.org/licenses/bsd-license.php BSD License
 * @author        Vesselin Kenashkov <vesko@webstudiobulgaria.com>
 */

namespace Guzaba2\Database;

use Guzaba2\Database\Interfaces\StatementInterface;
use Guzaba2\Object\GenericObject;

abstract class Statement extends GenericObject implements StatementInterface
{
    /**
     * @var string
     */
    protected $sql;

    /**
     *
     * @var Connection
     */
    protected $connection;

    /**
     * Statement constructor.
     * @param Connection $connection
     * @param string $sql
     */
    public function __construct(Connection $connection, string $sql)
    {
        parent::__construct();
        $this->connection = $connection;
        $this->sql = $sql;
        //$this->prepare();//this will call pdoStatement::prepare() which does nothing... it is already prepared
    }

    /**
     * Returns the SQL for the statement.
     *
     * @return string
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * @todo check if needed
     * @var array
     */
    protected static $services = [
        'execution_profile' => '',
    ];

    /**
     * @param string $param
     * @param mixed $value
     */
    abstract public function bindParam($param, &$value);

    /**
     * @param string $column
     * @param mixed $value
     */
    abstract public function bindColumn(string $column, &$value);

    /**
     * Returns the sql query with replaced placeholders
     * @return string
     */
    abstract public function getQueryString();

    abstract public function getParams();

    abstract public function debugDumpParams();

    /**
     * @param string $column_name
     * @return mixed
     */
    abstract public function fetchRow(string $column_name = '');

    abstract public function fetchAll();

    abstract public function execute($params = null);

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->sql;
    }

    public function &__invoke()
    { //PHP7.1
        $args = func_get_args();
        $ret = call_user_func_array($this->execute, $args);
        return $ret;
    }

    protected function debug_format_sql($sql, $error_line = null): string
    {
        $str = '';
        $sql = trim($sql);//used to remove empty new line at the beginning (and at the end)
        $sql_arr = explode(PHP_EOL, $sql);

        for ($aa = 0; $aa < count($sql_arr); $aa++) {
            if ($error_line === ($aa + 1)) {
                $str .= '<span style="color: red;">' . ($aa + 1) . '</span>' . "\t" . $sql_arr[$aa] . PHP_EOL;
            } else {
                $str .= ($aa + 1) . "\t" . $sql_arr[$aa] . PHP_EOL;
            }
        }
        return $str;
    }
}
