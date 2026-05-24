<?php
declare(strict_types=1);

namespace Twitkey\Core;

final class Router
{
    /** @var array<int, array{method:string,pattern:string,regex:string,handler:array{0:class-string,1:string}}> */
    private array $routes = [];

    /**
     * Register a route for a method and path pattern.
     *
     * @param array{0:class-string,1:string} $handler
     */
    public function add(string $method, string $pattern, array $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'regex' => $this->compile($pattern),
            'handler' => $handler,
        ];
    }

    /**
     * Dispatch the current request to the first matching route.
     */
    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($_POST['_method'] ?? $method);
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_int($key)) {
                    $params[$key] = $value;
                }
            }

            [$class, $action] = $route['handler'];
            $controller = new $class();
            $controller->$action(...array_values($params));
            return;
        }

        http_response_code(404);
        Helpers::render('errors/404', ['title' => 'Not Found'], true);
    }

    /**
     * Convert a route pattern into a strict regular expression.
     */
    private function compile(string $pattern): string
    {
        $quoted = preg_quote(rtrim($pattern, '/') ?: '/', '#');
        $quoted = str_replace('\{id\}', '(?P<id>\d+)', $quoted);
        $quoted = str_replace('\{username\}', '(?P<username>@?[A-Za-z0-9_]{1,15})', $quoted);
        $quoted = str_replace('\{user\}', '(?P<user>@?[A-Za-z0-9_]{1,15})', $quoted);
        return '#^' . $quoted . '$#';
    }
}
