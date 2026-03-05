<?php

declare(strict_types=1);

namespace Blogme\Services;

final class AppSetupService
{
    public function __construct(private readonly string $root)
    {
    }

    public function bootstrapFilesystem(): void
    {
        $paths = [
            $this->root . '/data/uploads/images',
            $this->root . '/data/uploads/covers',
            $this->root . '/public/uploads/images',
            $this->root . '/public/uploads/covers',
            $this->root . '/public/admin/assets',
            $this->root . '/public/assets',
            $this->root . '/data/themes',
            $this->root . '/storage/cache',
            $this->root . '/storage/logs',
            $this->root . '/storage/runtime',
            $this->root . '/storage/rate_limit',
        ];
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }

        // Keep builtin PHP server mode working with `-t public` by ensuring
        // assets and uploads are directly available under public/.
        $this->syncDir($this->root . '/resources/admin_assets', $this->root . '/public/admin/assets');
        $this->syncDir($this->root . '/data/uploads', $this->root . '/public/uploads');
    }

    public function bootstrapTheme(): void
    {
        $dst = $this->root . '/data/themes/default';
        if (!is_dir($dst)) {
            $src = $this->root . '/resources/themes/default';
            $this->copyDir($src, $dst);
        }

        $this->syncActiveThemeAssets();
    }

    private function copyDir(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        $items = scandir($src) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $srcPath = $src . '/' . $item;
            $dstPath = $dst . '/' . $item;
            if (is_dir($srcPath)) {
                $this->copyDir($srcPath, $dstPath);
                continue;
            }
            copy($srcPath, $dstPath);
        }
    }

    private function syncDir(string $src, string $dst): void
    {
        if (!is_dir($src)) {
            return;
        }
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        $items = scandir($src) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $srcPath = $src . '/' . $item;
            $dstPath = $dst . '/' . $item;
            if (is_dir($srcPath)) {
                $this->syncDir($srcPath, $dstPath);
                continue;
            }
            if (!is_file($dstPath) || filesize($dstPath) !== filesize($srcPath) || filemtime($dstPath) < filemtime($srcPath)) {
                copy($srcPath, $dstPath);
            }
        }
    }

    private function syncActiveThemeAssets(): void
    {
        $theme = $this->activeTheme();
        $src = $this->root . '/data/themes/' . $theme . '/assets';
        $defaultSrc = $this->root . '/data/themes/default/assets';
        $dst = $this->root . '/public/assets';

        if (!is_dir($src)) {
            $src = $defaultSrc;
        }
        if (!is_dir($src)) {
            return;
        }

        $this->syncDirMirror($src, $dst);
    }

    private function activeTheme(): string
    {
        $configPath = $this->root . '/config.json';
        if (!is_file($configPath)) {
            return 'default';
        }

        $raw = file_get_contents($configPath);
        if (!is_string($raw) || $raw === '') {
            return 'default';
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return 'default';
        }

        $theme = $data['theme'] ?? $data['Theme'] ?? 'default';
        if (!is_string($theme) || $theme === '') {
            return 'default';
        }

        if (!preg_match('/^[A-Za-z0-9_-]+$/', $theme)) {
            return 'default';
        }

        return $theme;
    }

    private function syncDirMirror(string $src, string $dst): void
    {
        if (!is_dir($src)) {
            return;
        }
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }

        $srcItems = scandir($src) ?: [];
        $srcNames = [];
        foreach ($srcItems as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $srcNames[$item] = true;
            $srcPath = $src . '/' . $item;
            $dstPath = $dst . '/' . $item;

            if (is_dir($srcPath)) {
                $this->syncDirMirror($srcPath, $dstPath);
                continue;
            }

            if (!is_file($dstPath) || filesize($dstPath) !== filesize($srcPath) || filemtime($dstPath) < filemtime($srcPath)) {
                copy($srcPath, $dstPath);
            }
        }

        $dstItems = scandir($dst) ?: [];
        foreach ($dstItems as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (isset($srcNames[$item])) {
                continue;
            }

            $dstPath = $dst . '/' . $item;
            if (is_dir($dstPath)) {
                $this->deleteDir($dstPath);
                continue;
            }
            if (is_file($dstPath)) {
                unlink($dstPath);
            }
        }
    }

    private function deleteDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $target = $path . '/' . $item;
            if (is_dir($target)) {
                $this->deleteDir($target);
                continue;
            }
            if (is_file($target)) {
                unlink($target);
            }
        }

        rmdir($path);
    }
}
