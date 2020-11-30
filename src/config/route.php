<?php
use \FastRoute\RouteCollector;
return function(RouteCollector $r) {
	$r->addRoute('GET', '/', 'App\Controller\IndexController::index');
	$r->addRoute('GET', '/cache', 'App\Controller\IndexController::cache');
	$r->addRoute('GET', '/soul', 'App\Controller\DbController::soul');
	$r->addRoute('GET', '/soul/{id:\d+}', 'App\Controller\DbController::soulFind');
	$r->addRoute('GET', '/db/concurrent', 'App\Controller\DbController::concurrent');
	$r->addRoute('GET', '/sleep', 'App\Controller\IndexController::sleep');
	$r->addRoute('GET', '/block', 'App\Controller\IndexController::block');
	$r->addRoute('GET', '/exception', 'App\Controller\IndexController::exception');
	$r->addRoute('GET', '/gc-status', 'App\Controller\IndexController::gcStatus');
	$r->addRoute('GET', '/gc-recycle', 'App\Controller\IndexController::gcRecycle');

	$r->addGroup('/test/', function(RouteCollector $r) {
		$r->addRoute('GET', 'task', 'App\Controller\TestController::taskCall');
		$r->addRoute('GET', 'request/{id:\d}', 'App\Controller\TestController::request');
		$r->addRoute(['GET', 'POST'], 'closure', function(\Workerman\Protocols\Http\Request $req) {
			return $req->uri();
		});
		
		$r->addRoute('GET', 'queue', 'App\Controller\TestController::queue');
		$r->addRoute('GET', 'http', 'App\Controller\TestController::http');
		$r->addRoute('GET', 'log', 'App\Controller\TestController::log');
	});

	$r->addRoute('GET', '/static/{filename:.+}', '\Framework\Base\FileServer::sendStatic');
	$r->addRoute('GET', '/{filename:favicon\.ico}', '\Framework\Base\FileServer::sendStatic');
};