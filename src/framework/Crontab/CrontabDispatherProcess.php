<?php
/**
 * 定时任务分发进程
 * 
 * 使用定时任务时需将此进程添加到自定义进程配置中启动才能生效。
 * 定时任务会分发到 TaskWorker 中执行，所以执行定时任务也务必需要启用 TaskWorker 进程。
 */
namespace Framework\Crontab;

use Workerman\Worker;
use Cron\CronExpression;
use Framework\Task\Task;
use Workerman\Lib\Timer;
use Framework\Base\Config;
use Framework\Process\Process;

class CrontabDispatherProcess extends Process
{

    public $name = 'CronDispatcher';

    /**
     * @var CronExpression[]
     */
    private $crons = [];

    public function run()
    {
        $tabs = di()->get(Config::class)->get('crontab', []);

        foreach ($tabs as $k => $set) {
            if (!$set['enable']) {
                continue;
            }

            $cron = CronExpression::factory($set['rule']);
            $cron->execute = $set['execute'];
            $cron->name = $k;
            $this->crons[] = $cron;
        }

        foreach ($this->crons as $i => $cron) {
            $this->check($cron);
        }
    }

    /**
     *
     * @param CronExpression $cron
     * @param boolean $nextRun
     * @return void
     */
    public function check($cron, $run=false)
    {
        if ($run) {
            Task::execute($cron->execute)->onResolve(function($e, $result) use ($cron) {
                /* @var \Exception $e */
                if ($e) {
                    Worker::log("[Crontab] {$cron->name} has ".get_class($e).': '.$e->getMessage()."\n".$e->getTraceAsString());
                } else {
                    Worker::log("[Crontab] {$cron->name} run successfuly!");
                }
            });
        }

        $now = time();
        $nextTime = $cron->getNextRunDate()->getTimestamp();
        $offset = $nextTime - $now;
        Timer::add($offset, [$this, 'check'], [$cron, true], false);
        Worker::log("[Crontab] {$cron->name} will run after $offset seconds.");
    }

}
