<?php

namespace Wind\Queue;

use Amp\Promise;
use Exception;
use Wind\Base\Config;
use Wind\Process\Process;
use Wind\Queue\Driver\Driver;
use Psr\EventDispatcher\EventDispatcherInterface;
use function Amp\asyncCall;
use function Amp\call;

class ConsumerProcess extends Process
{

    protected $queue = 'default';

    private $config;
    private $concurrent = 1;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(Config $config, EventDispatcherInterface $eventDispatcher)
    {
        $this->name = 'QueueConsumer.'.$this->queue;

        $queueConfig = $config->get('queue.'.$this->queue);

        if (!$queueConfig) {
            throw new Exception("Unable to find queue '{$this->queue}' config.");
        }

        $this->count = $queueConfig['processes'] ?? 1;
        $this->concurrent = $queueConfig['concurrent'] ?? 1;
        $this->config = $queueConfig;

        $this->eventDispatcher = $eventDispatcher;
    }

    public function run()
    {
        for ($i=0; $i<$this->concurrent; $i++) {
            asyncCall(function() {
                /* @var $driver Driver */
                $driver = new $this->config['driver']($this->config);
                yield $driver->connect();

                while (true) {
                    $message = yield $driver->pop();

                    if ($message === null) {
                        continue;
                    }

                    /** @var Message $message */
                    $job = $message->job;
                    $jobClass = get_class($job);

                    try {
                        $this->eventDispatcher->dispatch(new QueueJobEvent(QueueJobEvent::STATE_GET, $jobClass, $message->id));
                        yield call([$job, 'handle']);
                        yield $driver->ack($message);
                        $this->eventDispatcher->dispatch(new QueueJobEvent(QueueJobEvent::STATE_SUCCEED, $jobClass, $message->id));
                    } catch (\Exception $e) {
                        $attempts = $driver->attempts($message);

                        if ($attempts instanceof Promise) {
                            $attempts = yield $attempts;
                        }

                        if ($attempts < $message->job->maxAttempts) {
                            $this->eventDispatcher->dispatch(new QueueJobEvent(QueueJobEvent::STATE_ERROR, $jobClass, $message->id, $e));
                            yield $driver->release($message, $attempts+1);
                        } else {
                            $this->eventDispatcher->dispatch(new QueueJobEvent(QueueJobEvent::STATE_FAILED, $jobClass, $message->id, $e));
                            yield $driver->fail($message);
                        }
                    }
                }
            });
        }
    }

}
