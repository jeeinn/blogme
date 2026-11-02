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
        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            self::pathFromServer($_SERVER),
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER
        );
    }

    /**
     * Resolve the route path from server vars, compatible with both the
     * canonical Laravel nginx rewrite (`try_files ... /index.php?$query_string`)
     * and PATH_INFO style rewrites (`/index.php/$request_uri`).
     */
    public static function pathFromServer(array $server): string
    {
        $uriPath = parse_url((string)($server['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if (!is_string($uriPath) || $uriPath === '') {
            $uriPath = '/';
        }

        $scriptName = (string)($server['SCRIPT_NAME'] ?? '');
        if ($scriptName !== '' && str_starts_with($uriPath, $scriptName)) {
            $uriPath = substr($uriPath, strlen($scriptName));
        } else {
            $phpSelf = (string)($server['PHP_SELF'] ?? '');
            if ($phpSelf !== '' && str_starts_with($uriPath, $phpSelf)) {
                $uriPath = substr($uriPath, strlen($phpSelf));
            }
        }

        if (($uriPath === '' || $uriPath === '/') && isset($server['PATH_INFO'])) {
            $pathInfo = (string)$server['PATH_INFO'];
            if ($pathInfo !== '') {
                $uriPath = $pathInfo;
            }
        }

        if ($uriPath === '') {
            $uriPath = '/';
        }

        return rtrim($uriPath, '/') === '' ? '/' : rtrim($uriPath, '/');
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
        return self::isTlsServer($this->server);
    }

    public static function isTlsServer(array $server): bool
    {
        $https = strtolower((string) ($server['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return true;
        }
        if ((string) ($server['SERVER_PORT'] ?? '') === '443') {
            return true;
        }

        $forwardedProto = strtolower(trim((string) ($server['HTTP_X_FORWARDED_PROTO'] ?? '')));
        if ($forwardedProto !== '') {
            $firstProto = trim(explode(',', $forwardedProto)[0] ?? '');
            if ($firstProto === 'https') {
                return true;
            }
        }

        $forwardedSsl = strtolower((string) ($server['HTTP_X_FORWARDED_SSL'] ?? ''));
        if ($forwardedSsl === 'on' || $forwardedSsl === '1') {
            return true;
        }
        if ((string) ($server['HTTP_X_FORWARDED_PORT'] ?? '') === '443') {
            return true;
        }

        $cfVisitor = (string) ($server['HTTP_CF_VISITOR'] ?? '');
        if ($cfVisitor !== '') {
            $decoded = json_decode($cfVisitor, true);
            if (is_array($decoded) && strtolower((string) ($decoded['scheme'] ?? '')) === 'https') {
                return true;
            }
        }

        return false;
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
