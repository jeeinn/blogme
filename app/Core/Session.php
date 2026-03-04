<?php

declare(strict_types=1);

namespace Blogme\Core;

final class Session
{
    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('blogme');
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => $isSecure ? 'None' : 'Lax',
                'cookie_secure' => $isSecure,
            ]);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }
}
