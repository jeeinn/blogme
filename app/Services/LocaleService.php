<?php

declare(strict_types=1);

namespace Blogme\Services;

final class LocaleService
{
    /** @var array<string, array<string, string>> */
    private array $baseLocales = [];

    /** @var array<string, array<string, string>> */
    private array $themeLocales = [];

    public function __construct(private readonly string $baseLocaleDir, private readonly string $themesDir)
    {
        $this->baseLocales = $this->loadLocaleDir($this->baseLocaleDir);
    }

    public function loadThemeLocale(string $theme): void
    {
        $this->themeLocales = $this->loadLocaleDir($this->themesDir . '/' . $theme . '/locales');
    }

    public function t(string $key, string $locale): string
    {
        if (isset($this->baseLocales[$locale][$key])) {
            return $this->baseLocales[$locale][$key];
        }
        if (isset($this->baseLocales['en-us'][$key])) {
            return $this->baseLocales['en-us'][$key];
        }
        return $key;
    }

    public function tt(string $key, string $locale): string
    {
        if (isset($this->themeLocales[$locale][$key])) {
            return $this->themeLocales[$locale][$key];
        }
        if (isset($this->themeLocales['default'][$key])) {
            return $this->themeLocales['default'][$key];
        }
        return $key;
    }

    private function loadLocaleDir(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $result = [];
        $files = glob($dir . '/*.json') ?: [];
        foreach ($files as $file) {
            $name = strtolower(pathinfo($file, PATHINFO_FILENAME));
            $raw = file_get_contents($file);
            if (!is_string($raw) || $raw === '') {
                continue;
            }
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $result[$name] = $decoded;
            }
        }
        return $result;
    }
}
