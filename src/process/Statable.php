<?php

namespace Wind\Process;

use Wind\Base\Channel;

/**
 * Wind Framework Statable Process
 */
trait Statable
{

    /**
     * Undocumented function
     *
     * @return void
     */
    public function onGetState()
    {
        //消费进程状态上报
        $channel = di()->get(Channel::class);

        $channel->on('wind.stat.get', function ($tick) use ($channel) {
            $data = $this->getState();
            $data['pid'] = posix_getpid();
            $channel->publish('wind.stat.report.'.$tick['id'], $data);
        });
    }

    public abstract function getState();

}
