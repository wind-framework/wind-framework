<?php

namespace Wind\Base;

use Revolt\EventLoop;
use Revolt\EventLoop\Driver;
use Workerman\Events\EventInterface;

class Revolt implements EventInterface {

    /**
     * Socket onReadable/onWritable events.
     * @var array
     */
    protected $_allEvents = [];

    /**
     * Signals.
     * @var array
     */
    protected $_eventSignal = [];

    /**
     * Timers.
     * @var array
     */
    protected $_eventTimer = [];

    /**
     * Timer Id counter.
     * @var int
     */
    protected static $_timerId = 1;

    private Driver $driver;

    public function __construct()
    {
        $this->driver = EventLoop::getDriver();
    }

    /**
     * {@inheritdoc}
     */
    public function add($fd, $flag, $func, $args = []) {
        switch ($flag) {
            case self::EV_READ:
                $fd_key = intval($fd);
                //Don't return any thing in revolt callback closure, so don't use fn().
                //else will get InvalidCallbackError: Non-null return value received from callback
                $event =  $this->driver->onReadable($fd, function() use ($fd, $func) {
                    $func($fd);
                });
                $this->_allEvents[$fd_key][$flag] = $event;
                return true;
            case self::EV_WRITE:
                $fd_key = intval($fd);
                $event = $this->driver->onWritable($fd, function() use ($fd, $func) {
                    $func($fd);
                });
                $this->_allEvents[$fd_key][$flag] = $event;
                return true;
            case self::EV_SIGNAL:
                $fd_key = intval($fd);
                $event = $this->driver->onSignal($fd, function() use ($fd, $func) {
                    $func($fd);
                });
                $this->_eventSignal[$fd_key] = $event;
                return true;
            case self::EV_TIMER:
                $event = $this->driver->repeat($fd, function() use ($func, $args) {
                    $func(...$args);
                });
                $this->_eventTimer[self::$_timerId] = $event;
                return self::$_timerId++;
            case self::EV_TIMER_ONCE:
                $timerId = self::$_timerId;
                $event = $this->driver->delay($fd, function () use ($func, $args, $timerId) {
                    unset($this->_eventTimer[$timerId]);
                    $func(...$args);
                });
                $this->_eventTimer[self::$_timerId] = $event;
                return self::$_timerId++;
            default:
                break;
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function del($fd, $flag) {
        switch ($flag) {
            case self::EV_READ:
            case self::EV_WRITE:
                $fd_key = intval($fd);
                if (isset($this->_allEvents[$fd_key][$flag])) {
                    $this->driver->cancel($this->_allEvents[$fd_key][$flag]);
                    unset($this->_allEvents[$fd_key][$flag]);
                }
                if (empty($this->_allEvents[$fd_key]))
                    unset($this->_allEvents[$fd_key]);
                break;
            case self::EV_SIGNAL:
                $fd_key = intval($fd);
                if (isset($this->_eventSignal[$fd_key])) {
                    $this->driver->cancel($this->_eventSignal[$fd_key]);
                    unset($this->_eventSignal[$fd_key]);
                }
                break;
            case self::EV_TIMER:
            case self::EV_TIMER_ONCE:
                if (isset($this->_eventTimer[$fd])) {
                    $this->driver->cancel($this->_eventTimer[$fd]);
                    unset($this->_eventTimer[$fd]);
                }
                break;
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function loop() {
        // if (!$this->driver->isRunning()) {
            $this->driver->run();
        // }
    }

    /**
     * {@inheritdoc}
     */
    public function clearAllTimer() {
        foreach ($this->_eventTimer as $event) {
            $this->driver->cancel($event);
        }
        $this->_eventTimer = [];
    }

    /**
     * {@inheritdoc}
     */
    public function destroy() {
        foreach ($this->_eventSignal as $id) {
            $this->driver->cancel($id);
        }
        $this->driver->stop();
        pcntl_signal(SIGINT, SIG_IGN);
    }

    /**
     * {@inheritdoc}
     */
    public function getTimerCount() {
        return count($this->_eventTimer);
    }
}
