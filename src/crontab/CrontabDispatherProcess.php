<?php
/**
 * 定时任务分发进程
 * 
 * 使用定时任务时需将此进程添加到自定义进程配置中启动才能生效。
 * 定时任务会分发到 TaskWorker 中执行，所以执行定时任务也务必需要启用 TaskWorker 进程。
 */
namespace Wind\Crontab;

use Cron\CronExpression;
use Cron\FieldFactory;
use Wind\Base\Config;
use Wind\Process\Process;
use Wind\Task\Task;
use Psr\EventDispatcher\EventDispatcherInterface;
use Workerman\Lib\Timer;

class CrontabDispatherProcess extends Process
{

    public $name = 'CronDispatcher';

    /**
     * @var CronExpression[]
     */
    private $crons = [];

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function run()
    {
        $this->eventDispatcher = di()->get(EventDispatcherInterface::class);
        $tabs = di()->get(Config::class)->get('crontab', []);
        $fieldFactory = new FieldFactory();

        foreach ($tabs as $k => $set) {
            if (!$set['enable']) {
                continue;
            }

            $cron = new CronExpression($set['rule'], $fieldFactory);
            $this->crons[] = [$cron, $set['execute'], $k];
        }

        foreach ($this->crons as $i => $cron) {
            $this->check($i);
        }
    }

    /**
     *
     * @param int $index
     * @param boolean $run
     * @return void
     */
    public function check($index, $run=false)
    {
        /* @var $cron CronExpression */
        list($cron, $callable, $name) = $this->crons[$index];

        if ($run) {
            $this->eventDispatcher->dispatch(new CrontabEvent($name, CrontabEvent::TYPE_EXECUTE));
            Task::execute($callable)->onResolve(function($e, $result) use ($name) {
                /* @var \Exception $e */
                $event = new CrontabEvent($name, CrontabEvent::TYPE_RESULT, 0, $e ?: $result);
                $this->eventDispatcher->dispatch($event);
            });
        }

        //计算和安排下一次运行的时间
        $interval = $cron->getNextRunDate()->getTimestamp() - time();
        Timer::add($interval, [$this, 'check'], [$index, true], false);

        $this->eventDispatcher->dispatch(new CrontabEvent($name, CrontabEvent::TYPE_SCHED, $interval));
    }

}
