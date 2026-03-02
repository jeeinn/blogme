<?php

declare(strict_types=1);

namespace Blogme\Core;

final class Flash
{
    private const KEY = '_flash';

    public function __construct(private readonly Session $session)
    {
    }

    public function set(string $message): void
    {
        $this->session->set(self::KEY, $message);
    }

    public function getAndClear(): string
    {
        $value = (string) $this->session->get(self::KEY, '');
        if ($value !== '') {
            $this->session->remove(self::KEY);
        }
        return $value;
    }
}
