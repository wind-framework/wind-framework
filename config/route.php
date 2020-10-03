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
    ["GET", "/exception", "App\Controller\IndexController::exception"],
];