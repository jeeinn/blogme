<?php

declare(strict_types=1);

namespace Blogme\Tests;

use Blogme\Core\Database;
use Blogme\Repositories\PostRepository;
use Blogme\Repositories\TagRepository;
use Blogme\Repositories\UserRepository;
use Blogme\Services\MarkdownExportService;
use PHPUnit\Framework\TestCase;

final class MarkdownExportServiceTest extends TestCase
{
    private string $root;
    private string $dbPath;
    private Database $db;
    private UserRepository $users;
    private TagRepository $tags;
    private PostRepository $posts;

    protected function setUp(): void
    {
        $suffix = uniqid('', true);
        $this->root = sys_get_temp_dir() . '/blogme_export_root_' . $suffix;
        $this->dbPath = sys_get_temp_dir() . '/blogme_export_' . $suffix . '.sqlite';
        mkdir($this->root, 0755, true);

        $this->db = new Database($this->dbPath);
        $this->db->migrate();
        $this->users = new UserRepository($this->db);
        $this->tags = new TagRepository($this->db);
        $this->posts = new PostRepository($this->db, $this->tags);

        $this->users->create([
            'id' => 'u1',
            'email' => 'author@example.com',
            'nickname' => 'author',
            'password' => password_hash('123', PASSWORD_BCRYPT),
            'bio' => '',
            'created_at' => 1700000000,
        ]);

        $this->tags->create([
            'id' => 't1',
            'slug' => 'php',
            'name' => 'php',
            'description' => '',
            'created_at' => 1700000000,
        ]);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->root);
        @unlink($this->dbPath);
    }

    public function testExportAllClearsDirectoryAndWritesMarkdownFiles(): void
    {
        $now = 1710000000;

        $this->posts->create([
            'id' => 'p1',
            'title' => 'Hello/World',
            'slug' => 'hello-world',
            'excerpt' => 'Short excerpt',
            'author_id' => 'u1',
            'password' => '',
            'visibility' => 'public',
            'content' => "# Heading\nBody",
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
            'pinned_at' => 0,
            'trashed_at' => 0,
            'tag_ids' => ['t1'],
        ]);

        $this->posts->create([
            'id' => 'p2',
            'title' => 'Hello:World',
            'slug' => 'hello-world-2',
            'excerpt' => '',
            'author_id' => 'u1',
            'password' => '',
            'visibility' => 'public',
            'content' => 'Second body',
            'published_at' => $now - 1,
            'created_at' => $now - 1,
            'updated_at' => $now - 1,
            'pinned_at' => 0,
            'trashed_at' => 0,
            'tag_ids' => [],
        ]);

        $this->posts->create([
            'id' => 'p3',
            'title' => 'Draft Title',
            'slug' => 'draft-title',
            'excerpt' => '',
            'author_id' => 'u1',
            'password' => '',
            'visibility' => 'draft',
            'content' => 'Draft body',
            'published_at' => $now - 2,
            'created_at' => $now - 2,
            'updated_at' => $now - 2,
            'pinned_at' => 0,
            'trashed_at' => 123,
            'tag_ids' => [],
        ]);

        $directory = $this->root . '/exports/markdown';
        mkdir($directory, 0755, true);
        file_put_contents($directory . '/stale.md', 'old');

        $service = new MarkdownExportService($this->root, $this->posts);
        $result = $service->exportAll(['Timezone' => 28800]);

        self::assertSame(3, $result['count']);
        self::assertSame($directory, $result['directory']);
        self::assertFileDoesNotExist($directory . '/stale.md');

        $files = glob($directory . '/*.md');
        sort($files);

        self::assertSame([
            $directory . '/Draft Title - trash.md',
            $directory . '/Hello World - public (2).md',
            $directory . '/Hello World - public.md',
        ], $files);

        $markdown = file_get_contents($directory . '/Hello World - public.md');
        self::assertIsString($markdown);
        self::assertStringContainsString("title: 'Hello/World'", $markdown);
        self::assertStringContainsString("type: 'public'", $markdown);
        self::assertStringContainsString("nickname: 'author'", $markdown);
        self::assertStringContainsString("  - 'php'", $markdown);
        self::assertStringContainsString("published_at: '2024-03-10T00:00:00+08:00'", $markdown);
        self::assertStringContainsString("# Heading\nBody\n", $markdown);

        $trashMarkdown = file_get_contents($directory . '/Draft Title - trash.md');
        self::assertIsString($trashMarkdown);
        self::assertStringContainsString("type: 'trash'", $trashMarkdown);
        self::assertStringContainsString("trashed_at: '1970-01-01T08:02:03+08:00'", $trashMarkdown);
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }

            if (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
