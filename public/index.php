<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/library/autoload.php';

use Sangia\Api\Config\Config;
use Sangia\Api\Middleware\CorsMiddleware;
use Sangia\Api\Response;
use Sangia\Api\Router;
use Sangia\Api\Controllers\StatsController;
use Sangia\Api\Controllers\ResearchersController;
use Sangia\Api\Controllers\ArticlesController;
use Sangia\Api\Controllers\InstitutionsController;
use Sangia\Api\Controllers\ImpactScoresController;
use Sangia\Api\Controllers\AuthController;
use Sangia\Api\Controllers\SdgController;

Config::load();
CorsMiddleware::handle();

set_exception_handler(function (Throwable $e) {
    $debug = Config::get('APP_DEBUG', 'false') === 'true';
    Response::serverError($debug ? $e->getMessage() : 'Internal server error');
});

$router = new Router();

// ── Stats ─────────────────────────────────────────────────────────────
$router->get('/api/v1/stats', [StatsController::class, 'index']);

// ── Researchers ───────────────────────────────────────────────────────
$router->get('/api/v1/researchers',         [ResearchersController::class, 'index']);
$router->get('/api/v1/researchers/top',     [ResearchersController::class, 'top']);
$router->get('/api/v1/researchers/{orcid}', [ResearchersController::class, 'show']);

// ── Articles / Publications ────────────────────────────────────────────
$router->get('/api/v1/articles',           [ArticlesController::class, 'index']);
$router->get('/api/v1/articles/top',       [ArticlesController::class, 'top']);
$router->get('/api/v1/articles/trends',    [ArticlesController::class, 'trends']);
$router->get('/api/v1/articles/{id}',      [ArticlesController::class, 'show']);

// ── Institutions ───────────────────────────────────────────────────────
$router->get('/api/v1/institutions',       [InstitutionsController::class, 'index']);
$router->get('/api/v1/institutions/map',   [InstitutionsController::class, 'map']);
$router->get('/api/v1/institutions/{id}',  [InstitutionsController::class, 'show']);

// ── Impact Scores ──────────────────────────────────────────────────────
$router->get( '/api/v1/impact-scores/averages/{type}',         [ImpactScoresController::class, 'averages']);
$router->get( '/api/v1/impact-scores/{type}/{id}',             [ImpactScoresController::class, 'show']);
$router->get( '/api/v1/impact-scores/{type}/{id}/history',     [ImpactScoresController::class, 'history']);
$router->post('/api/v1/impact-scores/{type}/{id}/calculate',   [ImpactScoresController::class, 'calculate']);

// ── SDG ────────────────────────────────────────────────────────────────
$router->post('/api/v1/sdg/classify', [SdgController::class, 'classify']);

// ── Auth ───────────────────────────────────────────────────────────────
$router->post('/api/v1/auth/login',   [AuthController::class, 'login']);
$router->get( '/auth/orcid-login',    [AuthController::class, 'orcidLogin']);
$router->get( '/auth/orcid-callback', [AuthController::class, 'orcidCallback']);

// ── Health check ───────────────────────────────────────────────────────
$router->get('/health', [StatsController::class, 'index']);

$router->dispatch(
    $_SERVER['REQUEST_METHOD'],
    $_SERVER['REQUEST_URI']
);
