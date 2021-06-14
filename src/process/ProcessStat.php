<?php

namespace Wind\Process;

use Amp\Deferred;
use Wind\Base\Channel;
use Wind\Utils\StrUtil;
use Workerman\Timer;

class ProcessStat
{

    protected static $statableCount = 0;

    public static function addStatableCount($num)
    {
        self::$statableCount += $num;
        echo self::$statableCount."\n";
    }

    public static function get($timeout=5)
    {
        $stats = [];
        $defer = new Deferred();

        $channel = di()->get(Channel::class);

        $id = StrUtil::randomString(16);
        $countDown = self::$statableCount;
        $event = 'wind.stat.report.'.$id;

        //超时设置
        $timerId = Timer::add($timeout, function() use (&$countDown, $event, $defer, &$response, $channel) {
            if ($countDown > 0) {
                $channel->unsubscribe($event);
                $defer->resolve($response);
            }
        }, [], false);

        $channel->on($event, function($data) use ($channel, &$countDown, &$stats, $event, $defer, $timerId) {
            if (isset($data['group'])) {
                $stats[$data['type']][$data['group']][$data['pid']] = $data['stat'];
            } else {
                $stats[$data['type']]['_'][$data['pid']] = $data['stat'];
            }

            if (--$countDown == 0) {
                Timer::del($timerId);
                $channel->unsubscribe($event);
                $defer->resolve($stats);
            }
        });

        $channel->publish('wind.stat.get', ['id'=>$id]);

        return $defer->promise();
    }

}
