<?php

declare(strict_types=1);

namespace Blogme\Tests;

use Blogme\Services\MediaService;
use PHPUnit\Framework\TestCase;

final class MediaServiceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/blogme_media_' . uniqid('', true);
        mkdir($this->root, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testCollectPhotoFiles(): void
    {
        $dir = $this->root . '/public/uploads/images/2026/03';
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/demo.jpg', 'x');
        file_put_contents($dir . '/ignore.txt', 'x');

        $service = new MediaService($this->root);
        $files = $service->collectPhotoFiles();

        self::assertCount(1, $files);
        self::assertSame('2026', $files[0]['Year']);
        self::assertSame('03', $files[0]['Month']);
        self::assertSame('demo.jpg', $files[0]['Filename']);
    }

    public function testNormalizeFilesArray(): void
    {
        $service = new MediaService($this->root);

        $single = $service->normalizeFilesArray([
            'name' => 'a.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => 'tmp',
            'error' => 0,
            'size' => 1,
        ]);
        self::assertCount(1, $single);

        $multi = $service->normalizeFilesArray([
            'name' => ['a.jpg', 'b.jpg'],
            'type' => ['image/jpeg', 'image/jpeg'],
            'tmp_name' => ['t1', 't2'],
            'error' => [0, 0],
            'size' => [1, 2],
        ]);
        self::assertCount(2, $multi);
        self::assertSame('b.jpg', $multi[1]['name']);
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
