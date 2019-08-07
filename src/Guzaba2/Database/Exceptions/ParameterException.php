<?php


namespace Guzaba2\Database\Exceptions;

class ParameterException extends DatabaseException
{
    /**
     * @var mixed
     */
    protected $parameter_name;

    /**
     * @var string
     */
    protected $query;

    /**
     * @var array
     */
    protected $params;

    public function __construct($parameter_name, $errormsg, string $query, array $params, $previous_exception = null)
    {
        parent::__construct($errormsg, 0, $previous_exception);
        $this->parameter_name = $parameter_name;

        $this->query = $query;
        $this->params = $params;
    }

    /**
     * @return mixed
     */
    public function getParameterName()
    {
        return $this->parameter_name;
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
}
