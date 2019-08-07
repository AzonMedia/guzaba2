<?php


namespace Guzaba2\Database\Sql\Mysql;

use Guzaba2\Base\Base;
use Guzaba2\Database\Interfaces\StatementInterface;

class StatementMysqli extends Base implements StatementInterface
{

    /**
     * @var \mysqli_stmt
     */
    protected $NativeStatement;

    public function __construct(\mysqli_stmt $NativeStatement)
    {
        parent::__construct();
        $this->NativeStatement = $NativeStatement;
    }
}
