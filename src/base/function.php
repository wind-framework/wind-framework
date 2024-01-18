<?php

use Revolt\EventLoop;
use Wind\Base\Application;
use Wind\Base\Config;
use Wind\Base\Exception\CallableException;

function getApp() {
    return Application::getInstance();
}

function di() {
    return Application::getInstance()->container;
}

/**
 * 等于普通模式的 exit() 函数
 *
 * @param int $code
 * @throws Exception
 */
function done($code=0) {
    throw new \Wind\Base\Exception\ExitException('', $code);
}

/**
 * 读取 env 配置
 *
 * @param string $key
 * @param mixed $defaultValue
 * @return mixed
 */
function env($key, $defaultValue=null) {
    return di()->get(Config::class)->env($key) ?? $defaultValue;
}

/**
 * 读取配置
 *
 * @param string $key
 * @param mixed $defaultValue
 * @return mixed
 */
function config($key, $defaultValue=null) {
    return di()->get(Config::class)->get($key, $defaultValue);
}

/**
 * 智能包装 $callable 让其可放心调用
 *
 * 内部自动判断目标方法或函数是否动态或静态，如果是动态则通过容器创建该类并支持依赖注入
 *
 * @param string|array $callable 因为可能传入的方法是动态方法，这时不是一个合法的 callable，所以不能限定类型为 callable
 * @param bool $persistent 是否持久化到容器中，是则使用窗口的 get 方法创建单例，否则使用 make 创建临时对象
 * @return callable 返回可任意调用的 callable
 * @throws CallableException
 */
function wrapCallable($callable, $persistent=true) {
    if (is_array($callable)) {
        list($class, $method) = $callable;
    } elseif (is_string($callable) && str_contains($callable, '::')) {
        list($class, $method) = explode('::', $callable);
    } elseif (is_callable($callable)) {
        return $callable;
    } else {
        throw new CallableException("'$callable' is not a valid callable!");
    }

    if (!class_exists($class)) {
        throw new CallableException("Class '$class' not found.");
    }

    try {
        if ((new \ReflectionMethod($class, $method))->isStatic()) {
            return $callable;
        } else {
            //动态类型需要先实例化，这得益于依赖注入才能实现
            //如果该类的构造函数参数不能依赖注入，则不能通过 Task 动态调用
            $instance = $persistent ? di()->get($class) : di()->make($class);
            return [$instance, $method];
        }
    } catch (ReflectionException $e) {
        throw new CallableException($e->getMessage());
    }
}

function fmtException(Throwable $e, $maxStackTrace) {
    $string = get_class($e).': '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine()."\r\n"
        .'Stack trace:'."\r\n";

    $ts = 0;
    foreach ($e->getTrace() as $i => $row) {
        $string .= '#'.$i.' '.(isset($row['file']) ? $row['file'].'('.$row['line'].')' : '[internal function]').': ';

        if (isset($row['class'])) {
            $string .= "{$row['class']}{$row['type']}{$row['function']}()\r\n";
        } else {
            $string .= "{$row['function']}()\r\n";
        }

        if (++$ts == $maxStackTrace) {
            break;
        }
    }

    if ($ts < $maxStackTrace) {
        $string .= "{main}\r\n";
    }

    return $string;
}

function defer(\Closure $callback) {
    return EventLoop::defer($callback);
}

function asyncCallable(callable $callback) {
    return static fn(...$args) => EventLoop::queue($callback(...), ...$args);
}
