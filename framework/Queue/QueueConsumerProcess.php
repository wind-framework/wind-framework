<?php

namespace Framework\Queue;

use Exception;
use Framework\Base\Config;
use Framework\Process\Process;
use Workerman\Worker;

use function Amp\asyncCall;
use function Amp\call;

class QueueConsumerProcess extends Process
{

    protected $queue = 'default';

    private $config;
    private $concurrent = 1;

    public function __construct(Config $config)
    {
        $this->name = 'QueueConsumer.'.$this->queue;

        $qconfig = $config->get('queue.'.$this->queue);

        if (!$qconfig) {
            throw new Exception("Unable to find queue '{$this->queue}' config.");
        }

        $this->count = $qconfig['processes'] ?? 1;
        $this->concurrent = $qconfig['concurrent'] ?? 1;
        $this->config = $qconfig;
    }

    public function run()
    {
        for ($i=0; $i<$this->concurrent; $i++) {
            asyncCall(function() {
                /** @var DriverInterface $driver */
                $driver = new $this->config['driver']($this->config);

                Worker::log("[Queue] Connect.."); 
                yield $driver->connect();

                Worker::log("[Queue] Reserving.."); 
                while ($message = yield $driver->pop()) {
                    /** @var Message $message */
                    $job = $message->job;
                    $jobClass = get_class($job);

                    try {
                        Worker::log("[Queue] Get job: $jobClass.");
                        yield call([$job, 'handle']);
                        yield $driver->ack($message);
                    } catch (\Exception $e) {
                        $ex = get_class($e);
                        $code = $e->getCode();
                        $msg = $e->getMessage();
                        //Todo: 消费失败重试机制
                        Worker::log("[Queue] Consume $jobClass  error because: $ex: [$code] $msg");
                    }
                }
            });
        }
    }

}
