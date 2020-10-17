<?php
return [
    // {id} must be a number (\d+)
    ['GET', '/user/{id:\d+}', 'get_user_handler'],
    // The /{title} suffix is optional
    ['GET', '/articles/{id:\d+}[/{title}]', 'get_article_handler'],
    ["GET", "/", "App\Controller\IndexController::index"],
    ["GET", "/cache", "App\Controller\IndexController::cache"],
    ["GET", "/soul", "App\Controller\DbController::soul"],
    ["GET", "/soul/{id:\d+}", "App\Controller\DbController::soulFind"],
    ["GET", "/db/concurrent", "App\Controller\DbController::concurrent"],
    ["GET", "/sleep", "App\Controller\IndexController::sleep"],
    ["GET", "/block", "App\Controller\IndexController::block"],
    ["GET", "/exception", "App\Controller\IndexController::exception"],
    ['GET', '/gc-status', "App\Controller\IndexController::gcStatus"],
    ['GET', '/gc-recycle', 'App\Controller\IndexController::gcRecycle'],
    ['GET', '/test/task', 'App\Controller\TestController::taskCall'],
    ['GET', '/test/request/{id:\d+}', 'App\Controller\TestController::request'],
	['GET', '/test/closure', function(\Workerman\Protocols\Http\Request $req) {
		return $req->uri();
	}]
];