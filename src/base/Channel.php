<?php

namespace Wind\Base;

use Amp\DeferredFuture;
use Amp\Future;
use Wind\Base\Exception\RequireChannelException;
use Channel\Client;
use Workerman\Timer;

/**
 * Class Channel
 * @package Wind\Base
 *
 * @method void on($event, $callback)
 * @method void subscribe($events)
 * @method void unsubscribe($events)
 * @method void publish($events, $data)
 * @method void watch($channels, $callback, $autoReserve=true)
 * @method void unwatch($channels)
 * @method void enqueue($channels, $data)
 * @method void reserve()
 */
class Channel
{

    /**
     * @var DeferredFuture
     */
    private $connectDefer;

    private $connected = false;

    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config->get('server.channel');

        if (!$this->config['enable']) {
            throw new RequireChannelException('Component required channel server to be enable.');
        }
    }

    /**
     * Get connect Future
     *
     * @return Future<Amp\T>
     */
    protected function connect()
    {
        if ($this->connectDefer) {
            return $this->connectDefer->getFuture();
        }

        $this->connectDefer = new DeferredFuture();

        Client::$onConnect = function()  {
            $this->connected = true;
            $this->connectDefer->complete(null);
        };

        Client::$onClose = function()  {
            //断线后将 Promise 置于 pending 状态等待重连更新
            //已连接状态代表之前的 connectDefer 已经是 resolved 状态，此时需刷新 connectDefer 来让后面的发送等待
            //否则继续延用之前的 pending 状态的 connectDefer
            if ($this->connected) {
                $this->connectDefer = new DeferredFuture();
            }
            $this->connected = false;
        };

        Timer::add(0.5, function() {
            Client::connect($this->config['addr'] ?? '127.0.0.1', $this->config['port'] ?? 2206);
        }, [], false);

        return $this->connectDefer->getFuture();
    }

    public function __call($name, $arguments)
    {
        $future = $this->connect();

        if (!$future->isComplete()) {
            $future->await();
        }

        call_user_func_array([Client::class, $name], $arguments);
    }

}
