<?php

declare(strict_types=1);

namespace Blogme\Services;

use Blogme\Core\Database;
use Throwable;

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

    public function bootstrapDatabase(Database $db): void
    {
        $flagPath = $this->root . '/storage/runtime/migrated_at.txt';
        $dbPath = $this->root . '/db.sqlite';
        $needs = !is_file($flagPath) || !is_file($dbPath) || (is_file($dbPath) && filesize($dbPath) === 0);

        if (!$needs) {
            try {
                $stmt = $db->pdo()->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
                $stmt->execute();
                $needs = $stmt->fetchColumn() === false;
            } catch (Throwable) {
                $needs = true;
            }
        }

        if ($needs) {
            $db->migrate();
            file_put_contents($flagPath, (string) time());
        }
    }

}
