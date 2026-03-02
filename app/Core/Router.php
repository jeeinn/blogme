<?php

declare(strict_types=1);

namespace Blogme\Core;

final class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, vars:array<int,string>, handler:callable, name:string}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler, string $name = ''): void
    {
        $this->add('GET', $pattern, $handler, $name);
    }

    public function post(string $pattern, callable $handler, string $name = ''): void
    {
        $this->add('POST', $pattern, $handler, $name);
    }

    public function add(string $method, string $pattern, callable $handler, string $name = ''): void
    {
        $vars = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function (array $m) use (&$vars): string {
            $vars[] = $m[1];
            return '([^/]+)';
        }, $pattern);
        if (!is_string($regex)) {
            throw new HttpException(500, 'Invalid route pattern');
        }
        $regex = '#^' . $regex . '$#';

        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'regex' => $regex,
            'vars' => $vars,
            'handler' => $handler,
            'name' => $name !== '' ? $name : $pattern,
        ];
    }

    /** @return array{handler:callable, vars:array<string,string>, pattern:string, name:string}|null */
    public function match(string $method, string $path): ?array
    {
        $method = strtoupper($method);
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            $vars = [];
            foreach ($route['vars'] as $index => $varName) {
                $vars[$varName] = urldecode($matches[$index + 1] ?? '');
            }
            return [
                'handler' => $route['handler'],
                'vars' => $vars,
                'pattern' => $route['pattern'],
                'name' => $route['name'],
            ];
        }
        return null;
    }
}
