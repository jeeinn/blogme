<?php

declare(strict_types=1);

namespace Blogme\Tests;

use Blogme\Core\Database;
use Blogme\Services\AppSetupService;
use PHPUnit\Framework\TestCase;

final class AppSetupServiceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/blogme_setup_' . uniqid('', true);
        mkdir($this->root, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testBootstrapDatabaseCreatesTables(): void
    {
        $service = new AppSetupService($this->root);
        $service->bootstrapFilesystem();
        $db = new Database($this->root . '/db.sqlite');

        $service->bootstrapDatabase($db);

        $stmt = $db->pdo()->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        $stmt->execute();
        self::assertSame('users', $stmt->fetchColumn());
        self::assertFileExists($this->root . '/storage/runtime/migrated_at.txt');
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
                continue;
            }
            @unlink($path);
        }
        @rmdir($dir);
    }
}
