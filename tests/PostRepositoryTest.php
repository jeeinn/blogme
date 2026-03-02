<?php

declare(strict_types=1);

namespace Blogme\Tests;

use Blogme\Core\Database;
use Blogme\Repositories\PostRepository;
use Blogme\Repositories\TagRepository;
use Blogme\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;

final class PostRepositoryTest extends TestCase
{
    private string $dbPath;
    private Database $db;
    private UserRepository $users;
    private TagRepository $tags;
    private PostRepository $posts;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/blogme_test_' . uniqid('', true) . '.sqlite';
        $this->db = new Database($this->dbPath);
        $this->db->migrate();
        $this->users = new UserRepository($this->db);
        $this->tags = new TagRepository($this->db);
        $this->posts = new PostRepository($this->db, $this->tags);

        $this->users->create([
            'id' => 'u1',
            'email' => 'a@example.com',
            'nickname' => 'alice',
            'password' => password_hash('123', PASSWORD_BCRYPT),
            'bio' => '',
            'created_at' => time(),
        ]);
        $this->tags->create([
            'id' => 't1',
            'slug' => 'php',
            'name' => 'php',
            'description' => '',
            'created_at' => time(),
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->dbPath);
    }

    public function testCreateAndListPost(): void
    {
        $now = time();
        $this->posts->create([
            'id' => 'p1',
            'title' => 'Hello',
            'slug' => 'hello',
            'excerpt' => '',
            'author_id' => 'u1',
            'password' => '',
            'visibility' => 'public',
            'content' => 'content',
            'published_at' => $now - 10,
            'created_at' => $now - 10,
            'updated_at' => $now - 10,
            'pinned_at' => 0,
            'trashed_at' => 0,
            'tag_ids' => ['t1'],
        ]);

        $list = $this->posts->list([
            'is_published' => true,
            'is_trashed' => false,
            'visibilities' => ['public'],
        ], ['Timezone' => 0]);

        self::assertCount(1, $list);
        self::assertSame('Hello', $list[0]['Title']);
        self::assertSame('alice', $list[0]['Author']['Nickname']);
        self::assertSame('php', $list[0]['Tags'][0]['Name']);
    }
}
