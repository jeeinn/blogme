<?php

declare(strict_types=1);

namespace Blogme\Core;

final class Request
{
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $post,
        private readonly array $files,
        private readonly array $server
    ) {
    }

    public static function capture(): self
    {
        $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (!is_string($uriPath) || $uriPath === '') {
            $uriPath = '/';
        }
        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            rtrim($uriPath, '/') === '' ? '/' : rtrim($uriPath, '/'),
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function allPost(): array
    {
        return $this->post;
    }

    public function files(): array
    {
        return $this->files;
    }

    public function file(string $key): mixed
    {
        return $this->files[$key] ?? null;
    }

    public function host(): string
    {
        return (string) ($this->server['HTTP_HOST'] ?? 'localhost');
    }

    public function isTls(): bool
    {
        if (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') {
            return true;
        }
        return (($this->server['SERVER_PORT'] ?? null) === '443');
    }

    public function scheme(): string
    {
        return $this->isTls() ? 'https' : 'http';
    }

    public function fullUrl(): string
    {
        $query = http_build_query($this->query);
        return $this->scheme() . '://' . $this->host() . $this->path . ($query !== '' ? ('?' . $query) : '');
    }

    public function clientIp(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '127.0.0.1');
    }

    public function rawQueryString(): string
    {
        return (string) ($this->server['QUERY_STRING'] ?? '');
    }
}
