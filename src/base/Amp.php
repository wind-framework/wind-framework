<?php
/**
 * @url https://github.com/CismonX/Workerman-Amp
 */
namespace Wind\Base;

use Workerman\Events\EventInterface;
use Workerman\Worker;

class Amp implements EventInterface {

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

    /**
     * Amp Loop Dirver
     *
     * @var \Amp\Loop\Driver
     */
    protected $driver;

    public function __construct()
    {
        $this->driver = \Amp\Loop::getDriver();
    }

    /**
     * {@inheritdoc}
     */
    public function add($fd, $flag, $func, $args = null) {
        switch ($flag) {
            case self::EV_READ:
                $fd_key = intval($fd);
                //In Workerman the first parameter should be socket stream.
                $event = $this->driver->onReadable($fd, fn($id, $socket) => $func($socket));
                $this->_allEvents[$fd_key][$flag] = $event;
                return true;
            case self::EV_WRITE:
                $fd_key = intval($fd);
                //In Workerman the first parameter should be socket stream.
                $event = $this->driver->onWritable($fd, fn ($id, $socket) => $func($socket));
                $this->_allEvents[$fd_key][$flag] = $event;
                return true;
            case self::EV_SIGNAL:
                $fd_key = intval($fd);
                //In Workerman the first parameter should be signal.
                $event = $this->driver->onSignal($fd, fn($id, $signal) => $func($signal));
                $this->_eventSignal[$fd_key] = $event;
                return true;
            case self::EV_TIMER:
                $event = $this->driver->repeat($fd * 1000, fn() => call_user_func_array($func, (array)$args));
                $this->_eventTimer[self::$_timerId] = $event;
                return self::$_timerId++;
            case self::EV_TIMER_ONCE:
                $timerId = self::$_timerId;
                $event = $this->driver->delay($fd * 1000, function () use ($func, $args, $timerId) {
                    unset($this->_eventTimer[$timerId]);
                    call_user_func_array($func, (array)$args);
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
        if (!$this->driver->isRunning()) {
            $this->driver->run();
        }
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
        $this->driver->stop();
    }

    /**
     * {@inheritdoc}
     */
    public function getTimerCount() {
        return count($this->_eventTimer);
    }
}
