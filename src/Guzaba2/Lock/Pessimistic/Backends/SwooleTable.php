<?php

namespace Guzaba2\Lock\Backends;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Lock\Pessimistic\Interfaces\LockInterface;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Translator\Translator as t;

/**
 * Class SwooleTable
 * Keeps lock information in Swoole\Table
 * Because of the nature of Swoole\Table the instance needs to be created BEFORE the server is started. This way the same Swoole\Table will be shared between the workers.
 *
 *
 *
 * @package Guzaba2\Lock\Backends
 */
class SwooleTable extends Base
{

    protected const CONFIG_DEFAULTS = [
        'max_rows'              => 10000,//max simultaneous locks - throws an exception if reached
        'wait_time'             => 60,
        'hold_time'             => 60,
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * @var \Swoole\Table
     */
    protected $SwooleTable;

    public const DATA_STRUCT = [
        'lock_obtained_microtime'               => 'float',
        'lock_expiration_time'                  => 'float',//after microtime it expires
        'lock_level'                            => 'int',
        'lock_from_worker_id'                   => 'int',
        'lock_from_coroutine_id'                => 'int',
    ];

    /**
     * @see https://en.wikipedia.org/wiki/Distributed_lock_manager
     */

    /**
     * Null (NL). Indicates interest in the resource, but does not prevent other processes from locking it. It has the advantage that the resource and its lock value block are preserved, even when no processes are locking it.
     */
    public const LOCK_NL = 1;//NULL

    /**
     * Concurrent Read (CR). Indicates a desire to read (but not update) the resource. It allows other processes to read or update the resource, but prevents others from having exclusive access to it. This is usually employed on high-level resources, in order that more restrictive locks can be obtained on subordinate resources.
     */
    public const LOCK_CR = 2;//CONCURRENT READ

    /**
     * Concurrent Write (CW). Indicates a desire to read and update the resource. It also allows other processes to read or update the resource, but prevents others from having exclusive access to it. This is also usually employed on high-level resources, in order that more restrictive locks can be obtained on subordinate resources.
     */
    public const LOCK_CW = 4;//CONCURRENT WRITE

    /**
     * Protected Read (PR). This is the traditional share lock, which indicates a desire to read the resource but prevents other from updating it. Others can however also read the resource.
     */
    public const LOCK_PR = 8;//PROTECTED READ

    /**
     * Protected Write (PW). This is the traditional update lock, which indicates a desire to read and update the resource and prevents others from updating it. Others with Concurrent Read access can however read the resource.
     */
    public const LOCK_PW = 16;//PROTECTED WRITE

    /**
     * Exclusive (EX). This is the traditional exclusive lock which allows read and update access to the resource, and prevents others from having any access to it.
     */
    public const LOCK_EX = 32;//EXCLUSIVE LOCK


    public const LOCK_LEVELS = [
        self::LOCK_NL   => 'NULL',
        self::LOCK_CR   => 'CONCURRENT READ',
        self::LOCK_CW   => 'CONCURRENT WRITE',
        self::LOCK_PR   => 'PROTECTED READ',
        self::LOCK_PW   => 'PROTECTED WRITE',
        self::LOCK_EX   => 'EXCLUSIVE LOCK',
    ];


    public function __construct()
    {
        parent::__construct();
        if (Coroutine::inCoroutine()) {
            throw new RunTimeException(sprintf(t::_('Instances from %s need to be created before the swoole server is started. This instance is created in a coroutine whcih suggests it is being created inside the request (or other) handler of the server.'), __CLASS__));
        }

        $this->SwooleTable = new \Swoole\Table(static::CONFIG_RUNTIME['max_rows']);
        foreach (self::DATA_STRUCT as $key=>$php_type) {
            $this->SwooleTable->column($key, \Guzaba2\Swoole\Table::TYPES_MAP[$php_type]);
        }
        $this->SwooleTable->create();
    }

    /**
     * Destroys the SwooleTable
     */
    public function __destruct()
    {
        $this->SwooleTable->destroy();
        $this->SwooleTable = NULL;
        parent::__destruct(); // TODO: Change the autogenerated stub
    }

    public function acquire_lock(string $key, int $lock_level, int $wait_time = self::CONFIG_RUNTIME['wait_time'], $hold_time = self::CONFIG_RUNTIME['hold_time']) : LockInterface
    {
        $worker_id = Coroutine::getWorkerId();
        $coroutine_id = Coroutine::getCid();
        //as multiple locks can exist on a key these need to be looped over
        //$existing_lock = $this->SwooleTable->get();
//        $existing_locks = [];
//        //foreach (self::LOCK_LEVELS as $key_level => $value) {
//        //speed improvement - look first for the more restrictive locks
//        foreach(array_reverse(self::LOCK_LEVELS) as $key_level => $value) {
//            $lock_key = $key.'_'.$key_level;
//            $lock_record = $this->SwooleTable->get($lock_key);
//            if ($lock_record) {
//                $existing_locks[] = $lock_record;
//            }
//        }
        //TODO validate can the requested lock be obtained based on the current locks
        //
        //currently only  LOCK_PW will be obtained (LOCK_CR wont be needed)
        //the check is fairly simple for now - does any lock exist
        $stop = TRUE;
        do {
            $existing_lock = $this->SwooleTable->get($key);
            if (!$existing_lock) {
                $stop = TRUE;
            }
        } while(!$stop);

        //once the lock has been obtained - do another check in the table is this execution (worker_id + coroutine_id) holding the current lock
        //as the operation of obtaining the lock is not atomic and it is possible another thread to obtain a lock immediately after it become available

    }


}