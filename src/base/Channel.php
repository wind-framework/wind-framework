<?php

namespace Wind\Base;

use Amp\Deferred;
use Wind\Base\Exception\RequireChannelException;
use Wind\Channel\Client;
use Workerman\Timer;

/**
 * Class Channel
 * @package Wind\Base
 *
 * @method on($event, $callback)
 * @method subscribe($events)
 * @method unsubscribe($events)
 * @method publish($events, $data)
 * @method watch($channels, $callback, $autoReserve=true)
 * @method unwatch($channels)
 * @method enqueue($channels, $data)
 * @method reserve()
 */
class Channel
{

    /**
     * @var Deferred
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

    protected function connect()
    {
        if ($this->connectDefer) {
            return $this->connectDefer->promise();
        }

        $this->connectDefer = new Deferred();

        Client::$onConnect = function()  {
            $this->connected = true;
            $this->connectDefer->resolve();
        };

        Client::$onClose = function()  {
            //断线后将 Promise 置于 pending 状态等待重连更新
            //已连接状态代表之前的 connectDefer 已经是 resolved 状态，此时需刷新 connectDefer 来让后面的发送等待
            //否则继续延用之前的 pending 状态的 connectDefer
            if ($this->connected) {
                $this->connectDefer = new Deferred();
            }
            $this->connected = false;
        };

        Timer::add(0.5, function() {
            Client::connect('127.0.0.1', $this->config['port']);
        }, [], false);

        return $this->connectDefer->promise();
    }

    public function __call($name, $arguments)
    {
        $this->connect()->onResolve(function() use ($name, $arguments) {
            call_user_func_array([Client::class, $name], $arguments);
        });
    }

}