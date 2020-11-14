<?php

namespace Framework\Queue\Driver;

use function Amp\call;
use Framework\Queue\Queue;
use Framework\Queue\Message;
use Framework\Beanstalk\BeanstalkClient;

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
        $pri = $this->getPri($message->priority);
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
        return $this->client->release($message->id, BeanstalkClient::DEFAULT_PRI, $delay);
    }

    public function attempts(Message $message)
    {
        return call(function() use ($message) {
            $state = yield $this->client->statsJob($message->id);
            return $state['releases'];
        });
    }

    private function getPri($priority)
    {
        switch ($priority) {
            case Queue::PRIORITY_NORMAL: return BeanstalkClient::DEFAULT_PRI;
            case Queue::PRIORITY_HIGH: return 512;
            case Queue::PRIORITY_LOW: return 2048;
            default: return BeanstalkClient::DEFAULT_PRI;
        }
    }

}
