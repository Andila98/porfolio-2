<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Tiny regex router. Patterns use {name} placeholders, which match one path
 * segment. Handlers are [ControllerClass, 'method'] pairs or closures and
 * receive (Request $request, array $params).
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: callable|array{class-string, string}}> */
    private array $routes = [];

    public function get(string $pattern, callable|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function add(string $method, string $pattern, callable|array $handler): void
    {
        $regex = preg_replace('#\\\{([a-z_]+)\\\}#i', '(?P<$1>[^/]+)', preg_quote($pattern, '#'));
        $this->routes[] = ['method' => $method, 'regex' => '#^' . $regex . '$#', 'handler' => $handler];
    }

    /**
     * @return array{status: int, handler?: callable|array{class-string, string}, params?: array<string, string>, allowed?: list<string>}
     */
    public function match(string $method, string $path): array
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            $routeMethod = $route['method'];
            if ($routeMethod === $method || ($routeMethod === 'GET' && $method === 'HEAD')) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return ['status' => 200, 'handler' => $route['handler'], 'params' => $params];
            }
            $allowed[] = $routeMethod;
        }
        return $allowed === [] ? ['status' => 404] : ['status' => 405, 'allowed' => array_values(array_unique($allowed))];
    }
}
