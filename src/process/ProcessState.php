<?php

namespace Wind\Process;

use Amp\DeferredFuture;
use DI\DependencyException;
use DI\NotFoundException;
use Throwable;
use Wind\Base\Channel;
use Wind\Utils\StrUtil;
use Workerman\Timer;

use function Amp\await;

class ProcessState
{

    protected static $stateCount = 0;

    public static function addStateCount($num)
    {
        self::$stateCount += $num;
    }

    /**
     * Get Process State Data
     *
     * @param int $timeout
     * @return array
     */
    public static function get($timeout=5)
    {
        $stats = [];
        $defer = new DeferredFuture();

        $channel = di()->get(Channel::class);

        $id = StrUtil::randomString(16);
        $countDown = self::$stateCount;
        $event = 'wind.stat.report.'.$id;

        //超时设置
        $timerId = Timer::add($timeout, function() use (&$countDown, $event, $defer, &$response, $channel) {
            if ($countDown > 0) {
                $channel->unsubscribe($event);
                $defer->complete($response);
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
                $defer->complete($stats);
            }
        });

        $channel->publish('wind.stat.get', ['id'=>$id]);

        return $defer->getFuture()->await();
    }

}
