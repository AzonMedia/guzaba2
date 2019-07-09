<?php

namespace Guzaba2\Database;

use Guzaba2\Base\Base;
use Guzaba2\Database\Interfaces\ConnectionInterface;

abstract class Connection extends Base
implements ConnectionInterface
{

    //protect

    public function __destruct()
    {

        //print 'CONNECTION DESTRUCTION'.PHP_EOL;
        //ConnectionFactory::get_instance()->free_connection($this);
        //self::ConnectionFactory()->free_connection($this);
        $this->free();
        parent::__destruct();
    }

    /**
     * To be invoked when a connection is obtained.
     * @param int $cid
     */
    public function assign_to_coroutine(int $cid) : void
    {

    }

    /**
     * Frees this connection.
     * To be used if the connection is no longer used.
     * It is also used by the Guzaba\Coroutine\Coroutine at the end of coroutine execution to automatically free all connection that may have not be freed up by then (forgotten/hanging connections).
     */
    public function free() : void
    {
        self::ConnectionFactory()->free_connection($this);
    }

    /**
     *
     * @param array $queries Twodimensional indexed array containing 'query' and 'params' keys in the second dimension
     * @return array
     */
    public function execute_multiple_queries(array $queries) : array
    {

    }
}