<?php

declare(strict_types=1);

namespace Guzaba2\Database\Nosql\Redis;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Database\Exceptions\SQLParsingException;
use Guzaba2\Translator\Translator as t;

/**
 * This is a helper class. It is only statically accessible. No instances can be created
 */
abstract class StatementTypes extends Base
{

    public const WRITE_COMMANDS = [
        'setOptions',
        'set',
        'setBit',
        'setEx',
        'psetEx',
        'lSet',
        'del',
        'hDel',
        'hSet',
        'hMSet',
        'hSetNx',
        'delete',
        'mSet',
        'mSetNx',
        'persist',
        'restore',
        'renameKey',
        'rename',
        'renameNx',
        'setRange',
        'setNx',
        'append',
        'lPushx',
        'lPush',
        'rPush',
        'rPushx',
        'zIncrBy',
        'zAdd',
        'zDeleteRangeByScore',
        'zRemRangeByScore',
        'incrBy',
        'hIncrBy',
        'incr',
        'decrBy',
        'decr',
        'lInsert',
        'expire',
        'pexpire',
        'expireAt',
        'pexpireAt',
        'move',
        'listTrim',
        'ltrim',
        'lRem',
        'lRemove',
        'zDeleteRangeByRank',
        'zRemRangeByRank',
        'incrByFloat',
        'hIncrByFloat',
        'bitOp',
        'sAdd',
        'sMove',
        'sRemove',
        'srem',
        'zDelete',
        'zRemove',
        'zRem',
        'lPop',
        'blPop',
        'rPop',
        'brPop',
        'bRPopLPush',
        'sPop',
        'rpoplpush',
        'zPopMin',
        'zPopMax',
        'bzPopMin',
        'bzPopMax',
    ];

    public const OPERATION_TYPES = [
        true => 'write',
        false => 'read',
    ];

    /**
     * StatementTypes constructor.
     * @throws RunTimeException
     */
    public function __construct()
    {
        throw new RunTimeException(sprintf(t::_('No instances of class "%s" can be created.'), get_class($this)));
    }

    /**
     * Returns the statement type - read | write
     * @param string $method_name
     * @return string
     */
    public static function get_statement_type(string $method_name): string
    {
        return self::OPERATION_TYPES[isset(self::WRITE_COMMANDS[$method_name])];
    }
}
