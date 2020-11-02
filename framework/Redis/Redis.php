<?php

namespace Framework\Redis;

use Amp\Deferred;
use Amp\Success;
use Amp\Promise;
use Framework\Base\Config;
use Workerman\Redis\Client;
use Workerman\Redis\Exception;

use function Amp\call;

/**
 * Redis 协程客户端
 * 
 * Strings methods
 * @method Promise<int> append($key, $value)
 * @method Promise<int> bitCount($key)
 * @method Promise<int> decrBy($key, $value)
 * @method Promise<string|bool> get($key)
 * @method Promise<int> getBit($key, $offset)
 * @method Promise<string> getRange($key, $start, $end)
 * @method Promise<string> getSet($key, $value)
 * @method Promise<int> incrBy($key, $value)
 * @method Promise<float> incrByFloat($key, $value)
 * @method Promise<array> mGet(array $keys)
 * @method Promise<array> getMultiple(array $keys)
 * @method Promise<bool> setBit($key, $offset, $value)
 * @method Promise<bool> setEx($key, $ttl, $value)
 * @method Promise<bool> pSetEx($key, $ttl, $value)
 * @method Promise<bool> setNx($key, $value)
 * @method Promise<string> setRange($key, $offset, $value)
 * @method Promise<int> strLen($key)
 * Keys methods
 * @method Promise<int> del(...$keys)
 * @method Promise<int> unlink(...$keys)
 * @method Promise<false|string> dump($key)
 * @method Promise<int> exists(...$keys)
 * @method Promise<bool> expire($key, $ttl)
 * @method Promise<bool> pexpire($key, $ttl)
 * @method Promise<bool> expireAt($key, $timestamp)
 * @method Promise<bool> pexpireAt($key, $timestamp)
 * @method Promise<array> keys($pattern)
 * @method Promise<bool|array> scan($it)
 * @method Promise<void> migrate($host, $port, $keys, $dbIndex, $timeout, $copy = false, $replace = false)
 * @method Promise<bool> move($key, $dbIndex)
 * @method Promise<string|int|bool> object($information, $key)
 * @method Promise<bool> persist($key)
 * @method Promise<string> randomKey()
 * @method Promise<bool> rename($srcKey, $dstKey)
 * @method Promise<bool> renameNx($srcKey, $dstKey)
 * @method Promise<string> type($key)
 * @method Promise<int> ttl($key)
 * @method Promise<int> pttl($key)
 * @method Promise<void> restore($key, $ttl, $value)
 * Hashes methods
 * @method Promise<false|int> hSet($key, $hashKey, $value)
 * @method Promise<bool> hSetNx($key, $hashKey, $value)
 * @method Promise<false|string> hGet($key, $hashKey)
 * @method Promise<false|int> hLen($key)
 * @method Promise<false|int> hDel($key, ...$hashKeys)
 * @method Promise<array> hKeys($key)
 * @method Promise<array> hVals($key)
 * @method Promise<bool> hExists($key, $hashKey)
 * @method Promise<int> hIncrBy($key, $hashKey, $value)
 * @method Promise<float> hIncrByFloat($key, $hashKey, $value)
 * @method Promise<array> hScan($key, $iterator, $pattern = '', $count = 0)
 * @method Promise<int> hStrLen($key, $hashKey)
 * Lists methods
 * @method Promise<array> blPop($keys, $timeout)
 * @method Promise<array> brPop($keys, $timeout)
 * @method Promise<false|string> bRPopLPush($srcKey, $dstKey, $timeout)
 * @method Promise<false|string> lIndex($key, $index)
 * @method Promise<int> lInsert($key, $position, $pivot, $value)
 * @method Promise<false|string> lPop($key)
 * @method Promise<false|int> lPush($key, ...$entries)
 * @method Promise<false|int> lPushx($key, $value)
 * @method Promise<array> lRange($key, $start, $end)
 * @method Promise<false|int> lRem($key, $value, $count)
 * @method Promise<bool> lSet($key, $index, $value)
 * @method Promise<false|array> lTrim($key, $start, $end)
 * @method Promise<false|string> rPop($key)
 * @method Promise<false|string> rPopLPush($srcKey, $dstKey)
 * @method Promise<false|int> rPush($key, ...$entries)
 * @method Promise<false|int> rPushX($key, $value)
 * @method Promise<false|int> lLen($key)
 * Sets methods
 * @method Promise<int> sAdd($key, $value)
 * @method Promise<int> sCard($key)
 * @method Promise<array> sDiff($keys)
 * @method Promise<false|int> sDiffStore($dst, $keys)
 * @method Promise<false|array> sInter($keys)
 * @method Promise<false|int> sInterStore($dst, $keys)
 * @method Promise<bool> sIsMember($key, $member)
 * @method Promise<array> sMembers($key)
 * @method Promise<bool> sMove($src, $dst, $member)
 * @method Promise<false|string|array> sPop($key, $count = 0)
 * @method Promise<false|string|array> sRandMember($key, $count = 0)
 * @method Promise<int> sRem($key, ...$members)
 * @method Promise<array> sUnion(...$keys)
 * @method Promise<false|int> sUnionStore($dst, ...$keys)
 * @method Promise<false|array> sScan($key, $iterator, $pattern = '', $count = 0)
 * Sorted sets methods
 * @method Promise<array> bzPopMin($keys, $timeout)
 * @method Promise<array> bzPopMax($keys, $timeout)
 * @method Promise<int> zAdd($key, $score, $value)
 * @method Promise<int> zCard($key)
 * @method Promise<int> zCount($key, $start, $end)
 * @method Promise<double> zIncrBy($key, $value, $member)
 * @method Promise<int> zinterstore($keyOutput, $arrayZSetKeys, $arrayWeights = [], $aggregateFunction = '')
 * @method Promise<array> zPopMin($key, $count)
 * @method Promise<array> zPopMax($key, $count)
 * @method Promise<array> zRange($key, $start, $end, $withScores = false)
 * @method Promise<array> zRangeByScore($key, $start, $end, $options = [])
 * @method Promise<array> zRevRangeByScore($key, $start, $end, $options = [])
 * @method Promise<array> zRangeByLex($key, $min, $max, $offset = 0, $limit = 0)
 * @method Promise<int> zRank($key, $member)
 * @method Promise<int> zRevRank($key, $member)
 * @method Promise<int> zRem($key, ...$members)
 * @method Promise<int> zRemRangeByRank($key, $start, $end)
 * @method Promise<int> zRemRangeByScore($key, $start, $end)
 * @method Promise<array> zRevRange($key, $start, $end, $withScores = false)
 * @method Promise<double> zScore($key, $member)
 * @method Promise<int> zunionstore($keyOutput, $arrayZSetKeys, $arrayWeights = [], $aggregateFunction = '')
 * @method Promise<false|array> zScan($key, $iterator, $pattern = '', $count = 0)
 * HyperLogLogs methods
 * @method Promise<int> pfAdd($key, $values)
 * @method Promise<int> pfCount($keys)
 * @method Promise<bool> pfMerge($dstKey, $srcKeys)
 * Geocoding methods
 * @method Promise<int> geoAdd($key, $longitude, $latitude, $member, ...$items)
 * @method Promise<array> geoHash($key, ...$members)
 * @method Promise<array> geoPos($key, ...$members)
 * @method Promise<double> geoDist($key, $members, $unit = '')
 * @method Promise<int|array> geoRadius($key, $longitude, $latitude, $radius, $unit, $options = [])
 * @method Promise<array> geoRadiusByMember($key, $member, $radius, $units, $options = [])
 * Streams methods
 * @method Promise<int> xAck($stream, $group, $arrMessages)
 * @method Promise<string> xAdd($strKey, $strId, $arrMessage, $iMaxLen = 0, $booApproximate = false)
 * @method Promise<array> xClaim($strKey, $strGroup, $strConsumer, $minIdleTime, $arrIds, $arrOptions = [])
 * @method Promise<int> xDel($strKey, $arrIds)
 * @method Promise xGroup($command, $strKey, $strGroup, $strMsgId, $booMKStream = null)
 * @method Promise xInfo($command, $strStream, $strGroup = null)
 * @method Promise<int> xLen($stream)
 * @method Promise<array> xPending($strStream, $strGroup, $strStart = 0, $strEnd = 0, $iCount = 0, $strConsumer = null)
 * @method Promise<array> xRange($strStream, $strStart, $strEnd, $iCount = 0)
 * @method Promise<array> xRead($arrStreams, $iCount = 0, $iBlock = null)
 * @method Promise<array> xReadGroup($strGroup, $strConsumer, $arrStreams, $iCount = 0, $iBlock = null)
 * @method Promise<array> xRevRange($strStream, $strEnd, $strStart, $iCount = 0)
 * @method Promise<int> xTrim($strStream, $iMaxLen, $booApproximate = null)
 * Pub/sub methods
 * @method Promise publish($channel, $message)
 * @method Promise pubSub($keyword, $argument = null)
 * Generic methods
 * @method Promise rawCommand(...$commandAndArgs)
 * Transactions methods
 * @method \Redis multi()
 * @method Promise exec()
 * @method Promise discard()
 * @method Promise watch($keys)
 * @method Promise unwatch($keys)
 * Scripting methods
 * @method Promise eval($script, $args = [], $numKeys = 0)
 * @method Promise evalSha($sha, $args = [], $numKeys = 0)
 * @method Promise script($command, ...$scripts)
 * @method Promise client(...$args)
 * @method Promise<null|string> getLastError()
 * @method Promise<bool> clearLastError()
 * @method Promise _prefix($value)
 * @method Promise _serialize($value)
 * @method Promise _unserialize($value)
 * Introspection methods
 * @method Promise<bool> isConnected()
 * @method Promise getHost()
 * @method Promise getPort()
 * @method Promise<false|int> getDbNum()
 * @method Promise<false|double> getTimeout()
 * @method Promise getReadTimeout()
 * @method Promise getPersistentID()
 * @method Promise getAuth()
 */
class Redis
{

    private $redis;
    private $connectPromise;

    public function __construct(Config $config)
    {
        $conf = $config->get('redis.default');

        $defer = new Deferred;
        $this->redis = new Client("redis://{$conf['host']}:{$conf['port']}", [], function($status, $redis) use ($defer, $conf) {
            if ($status) {
                if ($conf['auth'] || $conf['db']) {
                    call(function() use ($conf) {
                        if ($conf['auth']) {
                            $r = yield $this->auth($conf['auth']);
                            if (!$r) {
                                throw new Exception("Auth failed to redis {$conf['host']}:{$conf['port']}.");
                            }
                        }
                        if ($conf['db'] && $conf['db'] != 0) {
                            yield $this->select($conf['db']);
                        }
                    })->onResolve(function($e) use ($defer) {
                        $e ? $defer->fail($e) : $defer->resolve();
                    });
                } else {
                    $defer->resolve();
                }
            } else {
                $defer->fail(new Exception("Connected to redis server error."));
            }
        });
        $this->connectPromise = $defer->promise();
    }

    public function connect()
    {
        return $this->connectPromise;
    }

    public function close()
    {
        $this->redis->close();
        return new Success();
    }

    public function __call($name, $args)
    {
        $defer = new Deferred;

        $args[] = function($result) use ($defer) {
            $defer->resolve($result);
        };

        call_user_func_array([$this->redis, $name], $args);

        return $defer->promise();
    }

}
