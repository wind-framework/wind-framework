<?php

namespace Wind\Socket;

use Amp\Socket\ConnectException;
use Amp\Socket\DnsSocketConnector;
use Amp\Socket\Socket;
use Amp\Socket\SocketConnector;
use Revolt\EventLoop;

use function Amp\delay;

/**
 * Wind Framework Simple Text Client
 *
 * Helps create clients by simple text protocols, with queues, reconnect, and easy-to-implement transactions.
 */
abstract class SimpleTextClient {

    private static $connector;

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
    protected bool $queuePaused = true;

    /**
     * Whether to reconnect when connection is lost
     */
    protected bool $autoReconnect = false;

    /**
     * Reconnect delay after connection lost
     */
    protected int $reconnectDelay = 3;

    /**
     * Reconnect max limit, or exception will be throw
     */
    protected int $reconnectMaxAttempts = 5;

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

        if (self::$connector === null) {
            self::$connector = new DnsSocketConnector();
        }

        $attempts = 0;

        CONNECT:

        try {
            $this->socket = $this->createSocket(self::$connector);
        } catch (ConnectException $e) {
            if ($this->autoReconnect && ++$attempts < $this->reconnectMaxAttempts) {
                delay($this->reconnectDelay);
                goto CONNECT;
            } else {
                $this->status = self::STATUS_CLOSED;
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
     *
     * @param SocketConnector $connector
     */
    protected abstract function createSocket(SocketConnector $connector): Socket;

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
        if ($this->status == self::STATUS_CLOSED) {
            $this->connect();
        }

        if (!$direct) {
            $this->queue->enqueue($cmd);
            $this->process();
        } else {
            try {
                $buffer = $this->send($cmd);
                $cmd->resolve($buffer);
            } catch (\Throwable $e) {
                $cmd->resolve($e);
            }
        }

        return $cmd->getFuture()->await();
    }

    /**
     * Processing the queue
     */
    protected function process()
    {
        if ($this->processing || $this->queue->isEmpty() || $this->queuePaused) {
            return;
        }

        $this->processing = true;

        EventLoop::queue(function() {
            while (!$this->queue->isEmpty()) {
                if ($this->queuePaused) {
                    break;
                }

                /** @var SimpleTextCommand $cmd */
                $cmd = $this->queue->dequeue();

                try {
                    $buffer = $this->send($cmd);
                    $cmd->resolve($buffer);
                } catch (\Throwable $e) {
                    if ($this->autoReconnect) {
                        $this->queuePaused = true;
                        $this->queue->enqueue($cmd);

                        $this->socket->close();
                        $this->status = self::STATUS_CLOSED;

                        try {
                            $this->connect();
                        } catch (ConnectException $e) {
                            //make all queue error
                            $error = new SimpleTextClientException('Failed to reconnect to server.', 0, $e);
                            while (!$this->queue->isEmpty()) {
                                $cmd = $this->queue->dequeue();
                                $cmd->resolve($error);
                            }
                        }
                    } else {
                        $this->close();
                        $cmd->resolve(new SimpleTextClientException('Connection lost while send command.', 0, $e));
                    }
                }
            }

            $this->processing = false;
        });
    }

    /**
     * Send the command and get response
     *
     * @return string
     */
    private function send(SimpleTextCommand $cmd)
    {
        if ($this->socket->isClosed()) {
            throw new SimpleTextClientException('Connection already closed.');
        }

        try {
            $this->socket->write($cmd->encode());
            $buffer = $this->socket->read();
        } catch (\Throwable $e) {
            throw new SimpleTextClientException($e->getMessage(), 0, $e);
        }

        if ($buffer === null) {
            throw new SimpleTextClientException('Connection lost while send command.');
        }

        return $buffer;
    }

}
