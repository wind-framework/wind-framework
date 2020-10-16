<?php

use Amp\Promise;
use Framework\Base\Application;

if (!function_exists('str_contains')) {
    function str_contains($str, $search) {
        return strpos($str, $search) !== false;
    }
}

function getApp() {
    return Application::getInstance();
}

function di() {
    return Application::getInstance()->container;
}

/**
 * 对协程 callable 进行依赖注入调用
 *
 * @param callable $callable
 * @param array $args
 * @param \Invoker\Invoker $invoker 指定自定义的 Invoker 调用，否则使用全局容器
 * @return Promise
 */
function wireCall($callable, $args=[], $invoker=null) {
	return \Amp\call(function() use ($callable, $args, $invoker) {
		$ret = ($invoker ?: di())->call($callable, $args);
		if ($ret instanceof \Generator || $ret instanceof Promise) {
			$ret = yield from $ret;
		}
		return $ret;
	});
}

/**
 * 等于普通模式的 exit() 函数
 *
 * @param int $code
 * @throws Exception
 */
function done($code=0) {
    throw new Exception('', $code);
}

/**
 * 读取 env 配置
 *
 * @param string $key
 * @param mixed $defaultValue
 * @return mixed
 */
function env($key, $defaultValue=null) {
    return array_key_exists($key, $_ENV) ? $_ENV[$key] : $defaultValue;
}