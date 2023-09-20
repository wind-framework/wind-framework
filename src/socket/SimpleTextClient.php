<?php

namespace Wind\Socket;

use Amp\Socket\Socket;
use Revolt\EventLoop;

/**
 * Wind Framework Simple Text Client
 *
 * Helps create clients by simple text protocols, with queues, reconnect, and easy-to-implement transactions.
 */
abstract class SimpleTextClient {

    protected Socket $socket;

    /**
     * Command queue
     */
    private \SplQueue $queue;

    /**
     * Connection Status
     */
    protected int $status = 0;

    protected const STATUS_CLOSED = 0;
    protected const STATUS_CONNECTING = 1;
    protected const STATUS_CONNECTED = 2;

    /**
     * Is current processing a command
     */
    protected bool $processing = false;

    /**
     * Is command queue paused
     */
    protected bool $queuePaused = false;

    /**
     * Whether to reconnect when connection is lost
     */
    protected bool $autoReconnect = false;

    /**
     * Reconnect delay after connection lost
     */
    protected int $reconnectDelay = 2;

    public function __construct()
    {
        $this->queue = new \SplQueue;
        $this->queue->setIteratorMode(\SplQueue::IT_MODE_DELETE);
    }

    public function connect()
    {
        if ($this->status == self::STATUS_CONNECTED) {
            return;
        }

        $this->status = self::STATUS_CONNECTING;

        try {
            $this->socket = $this->createSocket();
        } catch (\Throwable $e) {
            if ($this->autoReconnect) {
                EventLoop::delay($this->reconnectDelay, $this->connect(...));
                return;
            } else {
                throw $e;
            }
        }

        $this->authenticate();

        $this->status = self::STATUS_CONNECTED;

        //resume queue
        if ($this->queuePaused) {
            $this->queuePaused = false;
            $this->process();
        }
    }

    /**
     * Close client
     */
    public function close()
    {
        if (!$this->socket->isClosed()) {
            $this->socket->close();
        }

        $this->cleanResources();
        $this->status = self::STATUS_CLOSED;
    }

    /**
     * Create connect socket
     */
    protected abstract function createSocket(): Socket;

    /**
     * Authenticate socket connect
     *
     * Attention: In authenticate commands must use $direct when connection status is connecting.
     */
    protected abstract function authenticate();

    /**
     * Clean resources after closed
     */
    protected abstract function cleanResources();

    /**
     * Execute the command and wait the result
     *
     * @param bool $direct Directly send command without into the queue
     * @return mixed
     */
    protected function execute(SimpleTextCommand $cmd, $direct=false)
    {
        if (!$direct) {
            $this->queue->enqueue($cmd);
            $this->process();
        } else {
            $this->send($cmd);
        }

        return $cmd->getFuture()->await();
    }

    /**
     * Send the command
     */
    private function send(SimpleTextCommand $cmd)
    {
        // echo "Send: ".$cmd->encode();

        $this->socket->write($cmd->encode());
        $buffer = $this->socket->read();

        if ($buffer === null) {
            echo "Redis connection is closed\n";
            echo 'Readable: '.($this->socket->isReadable() ? 'true' : 'false')."\n";
            echo 'Writeable: '.($this->socket->isWritable() ? 'true' : 'false')."\n";
            echo 'IsClosed: '.($this->socket->isClosed() ? 'true' : 'false')."\n";

            if ($this->autoReconnect) {
                $this->queuePaused = true;
                $this->queue->enqueue($cmd);

                $this->socket->close();
                $this->status = self::STATUS_CLOSED;

                $this->connect();
            } else {
                $this->close();
                throw new \Exception('Connection lost while send command.');
            }

            return;
        }

        $cmd->resolve($buffer);
    }

    protected function process()
    {
        if ($this->processing || $this->queue->isEmpty() || $this->queuePaused) {
            return;
        }

        $this->processing = true;

        EventLoop::queue(function() {
            while (!$this->queue->isEmpty()) {
                /** @var Command $command */
                $cmd = $this->queue->dequeue();
                $this->send($cmd);
            }
            $this->processing = false;
        });
    }

}
