<?php


namespace Guzaba2\Database\Nosql\Redis;

use Guzaba2\Base\Base;
use Guzaba2\Database\Connection;
use Guzaba2\Database\ConnectionFactory;
use Guzaba2\Database\Exceptions\ConnectionException;
use Guzaba2\Database\Exceptions\QueryException;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Database\Interfaces\StatementInterface;
use Guzaba2\Kernel\Exceptions\ErrorException;
use Guzaba2\Translator\Translator as t;

/**
 * Class ConnectionCoroutine
 * @package Guzaba2\Database\Nosql\Redis
 *
 * Methods pinting to \Swoole\Coroutine\Redis
 *
 * @method __destruct()
 * @method connect($host, $port, $serialize)
 * @method getAuth()
 * @method getDBNum()
 * @method getOptions()
 * @method setOptions($options)
 * @method getDefer()
 * @method setDefer($defer)
 * @method recv()
 * @method request(array $params)
 * @method set($key, $value, $timeout, $opt)
 * @method setBit($key, $offset, $value)
 * @method setEx($key, $expire, $value)
 * @method psetEx($key, $expire, $value)
 * @method lSet($key, $index, $value)
 * @method get($key)
 * @method mGet($keys)
 * @method del($key, $other_keys)
 * @method hDel($key, $member, $other_members)
 * @method hSet($key, $member, $value)
 * @method hMSet($key, $pairs)
 * @method hSetNx($key, $member, $value)
 * @method delete($key, $other_keys)
 * @method mSet($pairs)
 * @method mSetNx($pairs)
 * @method getKeys($pattern)
 * @method keys($pattern)
 * @method exists($key, $other_keys)
 * @method type($key)
 * @method strLen($key)
 * @method lPop($key)
 * @method blPop($key, $timeout_or_key, $extra_args)
 * @method rPop($key)
 * @method brPop($key, $timeout_or_key, $extra_args)
 * @method bRPopLPush($src, $dst, $timeout)
 * @method lSize($key)
 * @method lLen($key)
 * @method sSize($key)
 * @method scard($key)
 * @method sPop($key)
 * @method sMembers($key)
 * @method sGetMembers($key)
 * @method sRandMember($key, $count)
 * @method persist($key)
 * @method ttl($key)
 * @method pttl($key)
 * @method zCard($key)
 * @method zSize($key)
 * @method hLen($key)
 * @method hKeys($key)
 * @method hVals($key)
 * @method hGetAll($key)
 * @method debug($key)
 * @method restore($ttl, $key, $value)
 * @method dump($key)
 * @method renameKey($key, $newkey)
 * @method rename($key, $newkey)
 * @method renameNx($key, $newkey)
 * @method rpoplpush($src, $dst)
 * @method randomKey()
 * @method pfadd($key, $elements)
 * @method pfcount($key)
 * @method pfmerge($dstkey, $keys)
 * @method ping()
 * @method auth($password)
 * @method unwatch()
 * @method watch($key, $other_keys)
 * @method save()
 * @method bgSave()
 * @method lastSave()
 * @method flushDB()
 * @method flushAll()
 * @method dbSize()
 * @method bgrewriteaof()
 * @method time()
 * @method role()
 * @method setRange($key, $offset, $value)
 * @method setNx($key, $value)
 * @method getSet($key, $value)
 * @method append($key, $value)
 * @method lPushx($key, $value)
 * @method lPush($key, $value)
 * @method rPush($key, $value)
 * @method rPushx($key, $value)
 * @method sContains($key, $value)
 * @method sismember($key, $value)
 * @method zScore($key, $member)
 * @method zRank($key, $member)
 * @method zRevRank($key, $member)
 * @method hGet($key, $member)
 * @method hMGet($key, $keys)
 * @method hExists($key, $member)
 * @method publish($channel, $message)
 * @method zIncrBy($key, $value, $member)
 * @method zAdd($key, $score, $value)
 * @method zPopMin($key, $count)
 * @method zPopMax($key, $count)
 * @method bzPopMin($key, $timeout_or_key, $extra_args)
 * @method bzPopMax($key, $timeout_or_key, $extra_args)
 * @method zDeleteRangeByScore($key, $min, $max)
 * @method zRemRangeByScore($key, $min, $max)
 * @method zCount($key, $min, $max)
 * @method zRange($key, $start, $end, $scores)
 * @method zRevRange($key, $start, $end, $scores)
 * @method zRangeByScore($key, $start, $end, $options)
 * @method zRevRangeByScore($key, $start, $end, $options)
 * @method zRangeByLex($key, $min, $max, $offset, $limit)
 * @method zRevRangeByLex($key, $min, $max, $offset, $limit)
 * @method zInter($key, $keys, $weights, $aggregate)
 * @method zinterstore($key, $keys, $weights, $aggregate)
 * @method zUnion($key, $keys, $weights, $aggregate)
 * @method zunionstore($key, $keys, $weights, $aggregate)
 * @method incrBy($key, $value)
 * @method hIncrBy($key, $member, $value)
 * @method incr($key)
 * @method decrBy($key, $value)
 * @method decr($key)
 * @method getBit($key, $offset)
 * @method lInsert($key, $position, $pivot, $value)
 * @method lGet($key, $index)
 * @method lIndex($key, $integer)
 * @method setTimeout($key, $timeout)
 * @method expire($key, $integer)
 * @method pexpire($key, $timestamp)
 * @method expireAt($key, $timestamp)
 * @method pexpireAt($key, $timestamp)
 * @method move($key, $dbindex)
 * @method select($dbindex)
 * @method getRange($key, $start, $end)
 * @method listTrim($key, $start, $stop)
 * @method ltrim($key, $start, $stop)
 * @method lGetRange($key, $start, $end)
 * @method lRange($key, $start, $end)
 * @method lRem($key, $value, $count)
 * @method lRemove($key, $value, $count)
 * @method zDeleteRangeByRank($key, $start, $end)
 * @method zRemRangeByRank($key, $min, $max)
 * @method incrByFloat($key, $value)
 * @method hIncrByFloat($key, $member, $value)
 * @method bitCount($key)
 * @method bitOp($operation, $ret_key, $key, $other_keys)
 * @method sAdd($key, $value)
 * @method sMove($src, $dst, $value)
 * @method sDiff($key, $other_keys)
 * @method sDiffStore($dst, $key, $other_keys)
 * @method sUnion($key, $other_keys)
 * @method sUnionStore($dst, $key, $other_keys)
 * @method sInter($key, $other_keys)
 * @method sInterStore($dst, $key, $other_keys)
 * @method sRemove($key, $value)
 * @method srem($key, $value)
 * @method zDelete($key, $member, $other_members)
 * @method zRemove($key, $member, $other_members)
 * @method zRem($key, $member, $other_members)
 * @method pSubscribe($patterns)
 * @method subscribe($channels)
 * @method unsubscribe($channels)
 * @method pUnSubscribe($patterns)
 * @method multi()
 * @method exec()
 * @method eval($script, $args, $num_keys)
 * @method evalSha($script_sha, $args, $num_keys)
 * @method script($cmd, $args)
 */
abstract class ConnectionCoroutine extends Connection
{
    protected const CONFIG_DEFAULTS = [
        'host' => 'redis',
        'port' => '6379',
        'timeout' => 1.5,
        'password' => '',
        'database' => 0
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * @var \Swoole\Coroutine\Redis
     */
    protected $RedisCo;

    /**
     * ConnectionCoroutine constructor.
     * @throws ConnectionException
     */
    public function __construct()
    {
        parent::__construct();

        $this->RedisCo = new \Swoole\Coroutine\Redis(static::CONFIG_RUNTIME);
        $this->RedisCo->connect(static::CONFIG_RUNTIME['host'], static::CONFIG_RUNTIME['port']);

        if (static::CONFIG_RUNTIME['password']) {
            $this->RedisCo->auth(static::CONFIG_RUNTIME['password']);
        }

        if (! $this->RedisCo->connected) {
            throw new ConnectionException(sprintf(t::_('Connection of class %s to %s:%s could not be established due to error: [%s] %s .'), get_class($this), static::CONFIG_RUNTIME['host'], static::CONFIG_RUNTIME['port'], $this->RedisCo->errCode, $this->RedisCo->errMsg));
        }
    }

    /**
     * Forward method calls to the Swoole\Coroutine\Redis object
     *
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (!method_exists($this->RedisCo, $name)) {
            throw new \BadMethodCallException(sprintf(t::_('Method %s, dooesn\'t exist in class %s'), $name, get_class($this->RedisCo)));
        }

        return call_user_func_array([$this->RedisCo, $name], $arguments);
    }

    /**
     * Closes redis connection
     */
    public function close() : void
    {
        $this->RedisCo->close();
    }
}
