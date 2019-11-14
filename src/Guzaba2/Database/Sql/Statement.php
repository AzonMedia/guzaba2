<?php

namespace Guzaba2\Database\Sql;

use Guzaba2\Base\Base;
use Guzaba2\Database\Interfaces\StatementInterface;

abstract class Statement extends Base implements StatementInterface
{
    protected $params = [];

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
}
