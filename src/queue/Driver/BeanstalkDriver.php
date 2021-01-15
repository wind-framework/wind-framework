<?php

namespace Wind\Queue\Driver;

use function Amp\call;
use Wind\Queue\Queue;
use Wind\Queue\Message;
use Wind\Beanstalk\BeanstalkClient;

class BeanstalkDriver extends Driver
{

    /**
     * @var BeanstalkClient
     */
    private $client;
    private $tube;

    public function __construct($config)
    {
        $this->client = new BeanstalkClient($config['host'], $config['port'], [
            'autoReconnect' => true,
            'concurrent' => true
        ]);
        // $this->client->debug = true;
        $this->tube = $config['tube'];
    }

    public function connect()
    {
        return call(function() {
            yield $this->client->connect();

            if ($this->tube != 'default') {
                yield $this->client->watch($this->tube);
                yield $this->client->ignore('default');
                yield $this->client->useTube($this->tube);
            }
        });
    }

    public function push(Message $message, $delay)
    {
        $raw = serialize($message->job);

        switch ($message->priority) {
            case Queue::PRI_NORMAL: $pri = BeanstalkClient::DEFAULT_PRI; break;
            case Queue::PRI_HIGH: $pri = 512; break;
            case Queue::PRI_LOW: $pri = 2048; break;
            default: $pri = $message->priority;
        }

        return $this->client->put($raw, $pri, $delay);
    }

    public function pop()
    {
        return call(function() {
            $data = yield $this->client->reserve();
            $job = unserialize($data['body']);
            return new Message($job, $data['id']);
        });
    }

    public function ack(Message $message)
    {
        return $this->client->delete($message->id);
    }

    public function fail(Message $message)
    {
        return $this->client->bury($message->id);
    }

    public function release(Message $message, $delay)
    {
        return call(function() use ($message, $delay) {
            $state = yield $this->client->statsJob($message->id);
            return $this->client->release($message->id, $state['pri'], $delay);
        });
    }

    public function attempts(Message $message)
    {
        return call(function() use ($message) {
            $state = yield $this->client->statsJob($message->id);
            return $state['releases'];
        });
    }

}
