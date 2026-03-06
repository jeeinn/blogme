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
            $this->root . '/public/uploads/images',
            $this->root . '/public/uploads/covers',
            $this->root . '/public/admin/assets',
            $this->root . '/public/themes',
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
        $default = $this->root . '/public/themes/default';
        if (!is_dir($default)) {
            mkdir($default, 0755, true);
        }
    }

}
