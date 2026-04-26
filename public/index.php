<?php
declare(strict_types=1);

/**
 * Sangia API Engine — Entry Point
 * Boots the engine and delegates everything to the Router.
 */

require_once dirname(__DIR__) . '/library/autoload.php';

use Sangia\Api\Config\Config;
use Sangia\Api\Middleware\CorsMiddleware;
use Sangia\Api\Response;
use Sangia\Api\Router;

Config::load();
CorsMiddleware::handle();

set_exception_handler(function (Throwable $e) {
    $debug = Config::get('APP_DEBUG', 'false') === 'true';
    Response::serverError($debug ? $e->getMessage() : 'Internal server error');
});

(new Router())->dispatch();
