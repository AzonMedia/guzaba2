<?php

namespace Guzaba2\Database\Exceptions;

use Guzaba2\Database\PdoStatement;

class DeadlockException extends DatabaseTransientException
{
    /**
     * DeadlockException constructor.
     * @param PdoStatement|null $pdoStatement
     * @param $sqlstate
     * @param $errorcode
     * @param $errormsg
     * @param $query
     * @param $params
     * @param string $debugdata
     * @param null $previous_exception
     */
    public function __construct(?PdoStatement$pdoStatement, $sqlstate, $errorcode, $errormsg, $query, $params, $debugdata='', $previous_exception=null)
    {
        parent::__construct($pdoStatement, $sqlstate, $errorcode, $errormsg, $query, $params, $debugdata, $previous_exception);
        //if a deadlock exception occurs we need to disable the exception throwing in pdoDriver::rollback() if the savepoint doesnt exist
        if ($this->pdoStatement) {
            $this->pdoStatement->get_connection()->get_driver()->suppress_savepoint_errors();
        }
    }

    public function __destruct()
    {
        parent::__destruct();
        //when the deadlock exception is destroyed (this means caught and goes out of scope) we can reeneable the exception throwing in pdoDriver.
        //this is needed because a new transaction can be attempted
        if ($this->pdoStatement) {
            $this->pdoStatement->get_connection()->get_driver()->enable_savepoint_errors();
        }
    }
}
