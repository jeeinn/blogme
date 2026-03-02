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
    }

    public function bootstrapTheme(): void
    {
        $dst = $this->root . '/data/themes/default';
        if (is_dir($dst)) {
            return;
        }
        $src = $this->root . '/resources/themes/default';
        $this->copyDir($src, $dst);
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
}
