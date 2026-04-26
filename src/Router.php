<?php
declare(strict_types=1);

namespace Sangia\Api;

class Router
{
    private array $routes = [];

    public function get(string $pattern, array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, array $handler): void
    {
        $this->routes[] = compact('method', 'pattern', 'handler');
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = rtrim(parse_url($uri, PHP_URL_PATH) ?? '/', '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            $regex = '#^' . preg_replace('/\{([a-z_]+)\}/', '([^/]+)', $route['pattern']) . '$#';

            if (preg_match($regex, $uri, $matches)) {
                array_shift($matches);
                [$class, $action] = $route['handler'];
                (new $class())->$action(...$matches);
                return;
            }
        }

        Response::notFound("Endpoint $method $uri not found");
    }
}
