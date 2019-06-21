<?php

namespace Guzaba2\Database;

use Guzaba2\Base\Base;
use Guzaba2\Database\Interfaces\ConnectionInterface;

abstract class Connection extends Base
implements ConnectionInterface
{
    public function __destruct()
    {
        parent::__destruct();
        //print 'CONNECTION DESTRUCTION'.PHP_EOL;
        ConnectionFactory::get_instance()->free_connection($this);
    }
}