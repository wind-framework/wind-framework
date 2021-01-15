<?php

namespace Wind\Redis;

use Amp\Deferred;
use Amp\Success;
use Amp\Promise;
use Wind\Base\Config;
use Workerman\Redis\Client;
use Workerman\Redis\Exception;

use function Amp\call;

/**
 * Redis 协程客户端
 * 
 * Strings methods
 * @method Promise append($key, $value) int
 * @method Promise bitCount($key) int
 * @method Promise decrBy($key, $value) int
 * @method Promise get($key) string|bool
 * @method Promise getBit($key, $offset) int
 * @method Promise getRange($key, $start, $end) string
 * @method Promise getSet($key, $value) string
 * @method Promise incrBy($key, $value) int
 * @method Promise incrByFloat($key, $value) float
 * @method Promise mGet(array $keys) array
 * @method Promise mSet(array $keys)
 * @method Promise mSetNx(array $keys)
 * @method Promise getMultiple(array $keys) array
 * @method Promise set($key, $value) bool
 * @method Promise setBit($key, $offset, $value) bool
 * @method Promise setEx($key, $ttl, $value) bool
 * @method Promise pSetEx($key, $ttl, $value) bool
 * @method Promise setNx($key, $value) bool
 * @method Promise setRange($key, $offset, $value) string
 * @method Promise strLen($key) int
 * @method Promise incr($key) int
 * @method Promise decr($key) int
 * Keys methods
 * @method Promise del(...$keys) int
 * @method Promise unlink(...$keys) int
 * @method Promise dump($key) false|string
 * @method Promise exists(...$keys) int
 * @method Promise expire($key, $ttl) bool
 * @method Promise pexpire($key, $ttl) bool
 * @method Promise expireAt($key, $timestamp) bool
 * @method Promise pexpireAt($key, $timestamp) bool
 * @method Promise keys($pattern) array
 * @method Promise scan($it) bool|array
 * @method Promise migrate($host, $port, $keys, $dbIndex, $timeout, $copy = false, $replace = false) void
 * @method Promise move($key, $dbIndex) bool
 * @method Promise object($information, $key) string|int|bool
 * @method Promise persist($key) bool
 * @method Promise randomKey() string
 * @method Promise rename($srcKey, $dstKey) bool
 * @method Promise renameNx($srcKey, $dstKey) bool
 * @method Promise type($key) string
 * @method Promise ttl($key) int
 * @method Promise pttl($key) int
 * @method Promise restore($key, $ttl, $value) void
 * Hashes methods
 * @method Promise hSet($key, $hashKey, $value) false|int
 * @method Promise hSetNx($key, $hashKey, $value) bool
 * @method Promise hGet($key, $hashKey) false|string
 * @method Promise hMSet($key, array $array) array
 * @method Promise hMGet($key, array $array) array
 * @method Promise hGetAll($key) array
 * @method Promise hLen($key) false|int
 * @method Promise hDel($key, ...$hashKeys) false|int
 * @method Promise hKeys($key) array
 * @method Promise hVals($key) array
 * @method Promise hExists($key, $hashKey) bool
 * @method Promise hIncrBy($key, $hashKey, $value) int
 * @method Promise hIncrByFloat($key, $hashKey, $value) float
 * @method Promise hScan($key, $iterator, $pattern = '', $count = 0) array
 * @method Promise hStrLen($key, $hashKey) int
 * Lists methods
 * @method Promise blPop($keys, $timeout) array
 * @method Promise brPop($keys, $timeout) array
 * @method Promise bRPopLPush($srcKey, $dstKey, $timeout) false|string
 * @method Promise lIndex($key, $index) false|string
 * @method Promise lInsert($key, $position, $pivot, $value) int
 * @method Promise lPop($key) false|string
 * @method Promise lPush($key, ...$entries) false|int
 * @method Promise lPushx($key, $value) false|int
 * @method Promise lRange($key, $start, $end) array
 * @method Promise lRem($key, $value, $count) false|int
 * @method Promise lSet($key, $index, $value) bool
 * @method Promise lTrim($key, $start, $end) false|array
 * @method Promise rPop($key) false|string
 * @method Promise rPopLPush($srcKey, $dstKey) false|string
 * @method Promise rPush($key, ...$entries) false|int
 * @method Promise rPushX($key, $value) false|int
 * @method Promise lLen($key) false|int
 * Sets methods
 * @method Promise sAdd($key, $value) int
 * @method Promise sCard($key) int
 * @method Promise sDiff($keys) array
 * @method Promise sDiffStore($dst, $keys) false|int
 * @method Promise sInter($keys) false|array
 * @method Promise sInterStore($dst, $keys) false|int
 * @method Promise sIsMember($key, $member) bool
 * @method Promise sMembers($key) array
 * @method Promise sMove($src, $dst, $member) bool
 * @method Promise sPop($key, $count = 0) false|string|array
 * @method Promise sRandMember($key, $count = 0) false|string|array
 * @method Promise sRem($key, ...$members) int
 * @method Promise sUnion(...$keys) array
 * @method Promise sUnionStore($dst, ...$keys) false|int
 * @method Promise sScan($key, $iterator, $pattern = '', $count = 0) false|array
 * Sorted sets methods
 * @method Promise bzPopMin($keys, $timeout) array
 * @method Promise bzPopMax($keys, $timeout) array
 * @method Promise zAdd($key, $score, $value) int
 * @method Promise zCard($key) int
 * @method Promise zCount($key, $start, $end) int
 * @method Promise zIncrBy($key, $value, $member) double
 * @method Promise zinterstore($keyOutput, $arrayZSetKeys, $arrayWeights = [], $aggregateFunction = '') int
 * @method Promise zPopMin($key, $count) array
 * @method Promise zPopMax($key, $count) array
 * @method Promise zRange($key, $start, $end, $withScores = false) array
 * @method Promise zRangeByScore($key, $start, $end, $options = []) array
 * @method Promise zRevRangeByScore($key, $start, $end, $options = []) array
 * @method Promise zRangeByLex($key, $min, $max, $offset = 0, $limit = 0) array
 * @method Promise zRank($key, $member) int
 * @method Promise zRevRank($key, $member) int
 * @method Promise zRem($key, ...$members) int
 * @method Promise zRemRangeByRank($key, $start, $end) int
 * @method Promise zRemRangeByScore($key, $start, $end) int
 * @method Promise zRevRange($key, $start, $end, $withScores = false) array
 * @method Promise zScore($key, $member) double
 * @method Promise zunionstore($keyOutput, $arrayZSetKeys, $arrayWeights = [], $aggregateFunction = '') int
 * @method Promise zScan($key, $iterator, $pattern = '', $count = 0) false|array
 * @method Promise sort($key, $options) Promise
 * HyperLogLogs methods
 * @method Promise pfAdd($key, $values) int
 * @method Promise pfCount($keys) int
 * @method Promise pfMerge($dstKey, $srcKeys) bool
 * Geocoding methods
 * @method Promise geoAdd($key, $longitude, $latitude, $member, ...$items) int
 * @method Promise geoHash($key, ...$members) array
 * @method Promise geoPos($key, ...$members) array
 * @method Promise geoDist($key, $members, $unit = '') double
 * @method Promise geoRadius($key, $longitude, $latitude, $radius, $unit, $options = []) int|array
 * @method Promise geoRadiusByMember($key, $member, $radius, $units, $options = []) array
 * Streams methods
 * @method Promise xAck($stream, $group, $arrMessages) int
 * @method Promise xAdd($strKey, $strId, $arrMessage, $iMaxLen = 0, $booApproximate = false) string
 * @method Promise xClaim($strKey, $strGroup, $strConsumer, $minIdleTime, $arrIds, $arrOptions = []) array
 * @method Promise xDel($strKey, $arrIds) int
 * @method Promise xGroup($command, $strKey, $strGroup, $strMsgId, $booMKStream = null)
 * @method Promise xInfo($command, $strStream, $strGroup = null)
 * @method Promise xLen($stream) int
 * @method Promise xPending($strStream, $strGroup, $strStart = 0, $strEnd = 0, $iCount = 0, $strConsumer = null) array
 * @method Promise xRange($strStream, $strStart, $strEnd, $iCount = 0) array
 * @method Promise xRead($arrStreams, $iCount = 0, $iBlock = null) array
 * @method Promise xReadGroup($strGroup, $strConsumer, $arrStreams, $iCount = 0, $iBlock = null) array
 * @method Promise xRevRange($strStream, $strEnd, $strStart, $iCount = 0) array
 * @method Promise xTrim($strStream, $iMaxLen, $booApproximate = null) int
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
 * @method Promise getLastError() null|string
 * @method Promise clearLastError() bool
 * @method Promise _prefix($value)
 * @method Promise _serialize($value)
 * @method Promise _unserialize($value)
 * Introspection methods
 * @method Promise isConnected() bool
 * @method Promise getHost()
 * @method Promise getPort()
 * @method Promise getDbNum() false|int
 * @method Promise getTimeout() false|double
 * @method Promise getReadTimeout()
 * @method Promise getPersistentID()
 * @method Promise getAuth()
 * @method Promise select($db)
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
