<?php

declare(strict_types=1);

namespace Blogme\Core;

final class Csrf
{
    private const KEY = '_csrf_token';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = (string) $this->session->get(self::KEY, '');
        if ($token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::KEY, $token);
        }
        return $token;
    }

    public function verify(string $submitted): bool
    {
        return hash_equals($this->token(), $submitted);
    }
}
