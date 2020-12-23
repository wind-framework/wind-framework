<?php

namespace Framework\Base;

use Amp\Deferred;
use Framework\Base\Exception\RequireChannelException;
use Framework\Channel\Client;

/**
 * Class Channel
 * @package Framework\Base
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

        $defer = new Deferred();

        Client::$onConnect = function() use ($defer) {
            $defer->resolve();
        };

        Client::$onClose = function() use ($defer) {
            $this->connectDefer = null;
            //Wait for reconnected
        };

        Client::connect('127.0.0.1', $this->config['port']);

        $this->connectDefer = $defer;

        return $defer = $defer->promise();
    }

    public function __call($name, $arguments)
    {
        $this->connect()->onResolve(function() use ($name, $arguments) {
            call_user_func_array([Client::class, $name], $arguments);
        });
    }

}