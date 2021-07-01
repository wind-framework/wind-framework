<?php
/**
 * @url https://github.com/CismonX/Workerman-Amp
 */
namespace Wind\Base;

use Amp\Loop;
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
     * {@inheritdoc}
     */
    public function add($fd, $flag, $func, $args = null) {
        switch ($flag) {
            case self::EV_READ:
                $fd_key = intval($fd);
                $event = Loop::onReadable($fd, function ($id, $socket) use ($func) {
                    //In Workerman the first parameter should be socket stream.
                    call_user_func($func, $socket);
                });
                $this->_allEvents[$fd_key][$flag] = $event;
                return true;
            case self::EV_WRITE:
                $fd_key = intval($fd);
                $event = Loop::onWritable($fd, function ($id, $socket) use ($func) {
                    //In Workerman the first parameter should be socket stream.
                    call_user_func($func, $socket);
                });
                $this->_allEvents[$fd_key][$flag] = $event;
                return true;
            case self::EV_SIGNAL:
                $fd_key = intval($fd);
                $event = Loop::onSignal($fd, function ($id, $signal) use ($func) {
                    //In Workerman the first parameter should be signal.
                    call_user_func($func, $signal);
                });
                $this->_eventSignal[$fd_key] = $event;
                return true;
            case self::EV_TIMER:
            case self::EV_TIMER_ONCE:
                $param = [$func, (array)$args, $flag, self::$_timerId];
                $event = Loop::repeat($fd * 1000, \Closure::bind(function () use ($param) {
                    $timer_id = $param[3];
                    if ($param[2] === self::EV_TIMER_ONCE) {
                        //Loop::delay() can also do the trick.
                        Loop::cancel($this->_eventTimer[$timer_id]);
                        unset($this->_eventTimer[$timer_id]);
                    }
                    try {
                        call_user_func_array($param[0], $param[1]);
                    } catch (\Throwable $e) {
                        Worker::log($e);
                        exit(250);
                    }
                }, $this, __CLASS__));
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
                    Loop::cancel($this->_allEvents[$fd_key][$flag]);
                    unset($this->_allEvents[$fd_key][$flag]);
                }
                if (empty($this->_allEvents[$fd_key]))
                    unset($this->_allEvents[$fd_key]);
                break;
            case self::EV_SIGNAL:
                $fd_key = intval($fd);
                if (isset($this->_eventSignal[$fd_key])) {
                    Loop::cancel($this->_eventSignal[$fd_key]);
                    unset($this->_eventSignal[$fd_key]);
                }
                break;
            case self::EV_TIMER:
            case self::EV_TIMER_ONCE:
                if (isset($this->_eventTimer[$fd])) {
                    Loop::cancel($this->_eventTimer[$fd]);
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
        Loop::run();
    }

    /**
     * {@inheritdoc}
     */
    public function clearAllTimer() {
        foreach ($this->_eventTimer as $event)
            Loop::cancel($event);
        $this->_eventTimer = [];
    }

    /**
     * {@inheritdoc}
     */
    public function destroy() {
        Loop::stop();
    }

    /**
     * {@inheritdoc}
     */
    public function getTimerCount() {
        return count($this->_eventTimer);
    }
}