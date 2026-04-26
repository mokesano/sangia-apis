<?php
declare(strict_types=1);

namespace Sangia\Api;

use Sangia\Api\Controllers\AdminController;
use Sangia\Api\Controllers\CitationController;
use Sangia\Api\Controllers\ImpactController;
use Sangia\Api\Controllers\JournalController;
use Sangia\Api\Controllers\OrcidController;
use Sangia\Api\Controllers\RecommendationController;
use Sangia\Api\Controllers\ScopusController;
use Sangia\Api\Controllers\SdgController;
use Sangia\Api\Controllers\SintaController;
use Sangia\Api\Controllers\TrendController;
use Sangia\Gateway\ApiKeyMiddleware;
use Sangia\Api\Middleware\RateLimitMiddleware;

class Router
{
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', '/') ?: '/';

        if ($this->routePublic($uri, $method)) return;

        ApiKeyMiddleware::validate();
        RateLimitMiddleware::check();

        if ($this->routeProtected($uri, $method)) return;

        Response::json(['status' => 'error', 'message' => "$method $uri not found"], 404);
    }

    // ── Public routes ─────────────────────────────────────────────────────────

    private function routePublic(string $uri, string $method): bool
    {
        if ($method === 'GET' && ($uri === '/health' || $uri === '/api/v1/health')) {
            Response::json(['status' => 'up', 'service' => 'Sangia API Engine', 'time' => date('c')]);
            return true;
        }

        if ($method === 'GET' && $uri === '/api/v1/sdg/versions') {
            (new SdgController())->versions();
            return true;
        }

        if ($method === 'GET' && ($uri === '/api/v1' || $uri === '/api')) {
            (new SdgController())->catalogue();
            return true;
        }

        return false;
    }

    // ── Protected routes ──────────────────────────────────────────────────────

    private function routeProtected(string $uri, string $method): bool
    {
        // SDG — 7 versioned endpoints
        if ($method === 'POST' && preg_match('#^/api/v1/sdg/(v0|v1|v2|v3|v4|v5|v5e)/classify$#', $uri, $m)) {
            (new SdgController())->classify($m[1]);
            return true;
        }

        // SDG — alias → v5
        if ($method === 'POST' && $uri === '/api/v1/sdg/classify') {
            header('Location: /api/v1/sdg/v5/classify', true, 307);
            exit;
        }

        // Scopus author
        if ($method === 'GET' && $uri === '/api/v1/scopus/author') {
            (new ScopusController())->author();
            return true;
        }

        // ORCID profile
        if ($method === 'GET' && $uri === '/api/v1/orcid/profile') {
            (new OrcidController())->profile();
            return true;
        }

        // Citation by DOI
        if ($method === 'GET' && $uri === '/api/v1/citation/doi') {
            (new CitationController())->doi();
            return true;
        }

        // Journal metrics
        if ($method === 'GET' && $uri === '/api/v1/journal/metrics') {
            (new JournalController())->metrics();
            return true;
        }

        // SINTA journal score
        if ($method === 'GET' && $uri === '/api/v1/sinta/score') {
            (new SintaController())->score();
            return true;
        }

        // Wizdam Impact Score (batched)
        if ($method === 'POST' && $uri === '/api/v1/impact/calculate') {
            (new ImpactController())->calculate();
            return true;
        }

        // Trend Analysis Engine (API #7)
        if ($method === 'POST' && $uri === '/api/v1/trend/analyze') {
            (new TrendController())->analyze();
            return true;
        }

        // Policy Recommendation Engine (API #8)
        if ($method === 'POST' && $uri === '/api/v1/recommendation/policy') {
            (new RecommendationController())->policy();
            return true;
        }

        // Admin — revoke API key
        if ($method === 'POST' && $uri === '/api/v1/admin/keys/revoke') {
            (new AdminController())->revokeKey();
            return true;
        }

        return false;
    }
}
