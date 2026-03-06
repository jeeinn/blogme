<?php

declare(strict_types=1);

namespace Blogme\Services;

final class ConfigService
{
    public function __construct(private readonly string $root)
    {
    }

    public function path(): string
    {
        return $this->root . '/config.json';
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    public function load(): ?array
    {
        if (!$this->exists()) {
            return null;
        }
        $raw = file_get_contents($this->path());
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function save(array $config): void
    {
        file_put_contents(
            $this->path(),
            json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
    }

    /** @return array<int, string> */
    public function listThemes(): array
    {
        $base = $this->root . '/public/themes';
        if (!is_dir($base)) {
            return [];
        }
        $items = scandir($base) ?: [];
        $themes = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (is_dir($base . '/' . $item)) {
                $themes[] = $item;
            }
        }
        sort($themes);
        return $themes;
    }

    public function themeExists(string $theme): bool
    {
        return is_dir($this->root . '/public/themes/' . $theme);
    }
}
