<?php
declare(strict_types=1);

namespace Guzaba2\Database\Exceptions;

use Guzaba2\Database\PdoStatement;
use Guzaba2\Database\Interfaces\StatementInterface;

class QueryException extends DatabaseException
{

    /**
     * The pdoStatement on which this error occurred
     * @var PdoStatement
     */
    protected $pdoStatement;

    /**
     *
     * @var string SQLSTATE error code (a five characters alphanumeric identifier defined in the ANSI SQL standard).
     */
    protected $sqlstate;

    /**
     *
     * @var string Driver specific error code.
     */
    protected $errorcode;

    /**
     *
     * @var string The specific query that was executed.
     */
    protected $query;

    /**
     * The params from pdostatements as they were bound.
     * @var array
     */
    protected $params;

    /**
     *
     * @var string Additional debug data regarding the bound parameters.
     */
    protected $debugdata;

    /**
     * The data for the first three argument is supposed to be obtained from \PDOStatement::errorInfo()
     * The executed query can be obtained from \PDOStatement::queryString
     * The extra debug data is spupposed to be obtained from \PDOStatement::debugDumpParams
     * @param StatementInterface|null $statementInterface
     * @param string $sqlstate SQLSTATE error code (a five characters alphanumeric identifier defined in the ANSI SQL standard).
     * @param string $errorcode Driver specific error code.
     * @param string $errormsg Driver specific error message.
     * @param string $query The specific query that was executed.
     * @param array $params The bound parameters (this is much more useful than the debugData)
     * @param string $debugdata Additional debug data regarding the bound parameters.
     * @param null $previous_exception
     */
    public function __construct(?StatementInterface $statementInterface, $sqlstate, $errorcode, $errormsg, $query, $params, $debugdata = '', $previous_exception = null)
    {
        //this exception should be thrown only from org\guzaba\framework\database\classes\pdostatement::execute()
        $trace_arr = debug_backtrace();
        $last_call = $trace_arr[1];//0 is this constructor
        //if ($last_call['function']!='execute'&&$last_call['class']!=framework\database\classes\pdoStatement::_class) {
        //    throw new framework\base\exceptions\runTimeException(sprintf(t::_('CODE ERROR!!! "%s" should be thrown only by "%s". It was thrown in file %s on line %s. Please correct the code!'),get_class($this),org\guzaba\framework\database\classes\pdoStatement::_class.'::execute()',$this->getFile(),$this->getLine()));
        //}

        parent::__construct($errormsg, 0, $previous_exception);

        $this->pdoStatement = $statementInterface;
        $this->sqlstate = $sqlstate;
        $this->errorcode = $errorcode;
        $this->query = $query;
        $this->params = $params;
        $this->debugdata = $debugdata;
    }

    public function getPdoStatement(): ?PdoStatement
    {
        return $this->pdoStatement();
    }

    /**
     *
     * @return string SQLSTATE error code (a five characters alphanumeric identifier defined in the ANSI SQL standard).
     */
    public function getSqlState()
    {
        return $this->sqlstate;
    }

    /**
     *
     * @return string Driver specific error code.
     */
    public function getErrorCode()
    {
        return $this->errorcode;
    }

    /**
     *
     * @return string The specific query that was executed.
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Returns all the params that were bound.
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     *
     * @return string Additional debug data regarding the bound parameters.
     */
    public function getDebugData() : string
    {
        return $this->debugdata;
    }
}
