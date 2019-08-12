<?php

namespace Guzaba2\Lock\Backends;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Translator\Translator as t;

/**
 * Class SwooleTable
 * Keeps lock information in Swoole\Table
 * Because of the nature of Swoole\Table the instance needs to be created BEFORE the server is started. This way the same Swoole\Table will be shared between the workers.
 * @package Guzaba2\Lock\Backends
 */
class SwooleTable extends Base
{
    protected $SwooleTable;

    public function __construct()
    {
        parent::__construct();
        if (Coroutine::inCoroutine()) {
            throw new RunTimeException(sprintf(t::_('Instances from %s need to be created before the swoole server is started. This instance is created in a coroutine whcih suggests it is being created inside the request (or other) handler of the server.'), __CLASS__));
        }

        $this->SwooleTable = new \Swoole\Table(100);
        //the key will be the class name with the index
        //the data consists of last modified microtime, worker id, coroutine id
        $this->SwooleTable->column('updated_microtime', \Swoole\Table::TYPE_FLOAT);
        $this->SwooleTable->column('updated_from_worker_id', \Swoole\Table::TYPE_INT);
        $this->SwooleTable->column('updated_from_coroutine_id', \Swoole\Table::TYPE_INT);
        $this->SwooleTable->create();
    }
}
