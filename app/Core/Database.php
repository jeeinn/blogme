<?php

declare(strict_types=1);

namespace Blogme\Core;

use PDO;
use Throwable;

final class Database
{
    private PDO $pdo;

    public function __construct(string $path)
    {
        $this->pdo = new PDO('sqlite:' . $path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function migrate(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id TEXT NOT NULL PRIMARY KEY,
                email TEXT NOT NULL UNIQUE,
                nickname TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                bio TEXT NOT NULL,
                created_at INTEGER NOT NULL
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS posts (
                id TEXT NOT NULL PRIMARY KEY,
                title TEXT NOT NULL,
                slug TEXT NOT NULL,
                excerpt TEXT NOT NULL,
                author_id TEXT NOT NULL,
                password TEXT NOT NULL,
                visibility TEXT NOT NULL,
                content TEXT NOT NULL,
                pinned_at INTEGER NOT NULL,
                published_at INTEGER NOT NULL,
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL,
                trashed_at INTEGER NOT NULL
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS tags (
                id TEXT NOT NULL PRIMARY KEY,
                slug TEXT NOT NULL,
                name TEXT NOT NULL,
                description TEXT NOT NULL,
                created_at INTEGER NOT NULL
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS post_tags (
                tag_id TEXT NOT NULL,
                post_id TEXT NOT NULL,
                PRIMARY KEY (tag_id, post_id)
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS navigations (
                id TEXT NOT NULL PRIMARY KEY,
                url TEXT NOT NULL,
                name TEXT NOT NULL,
                sequence INTEGER NOT NULL
            )'
        );

        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_posts_slug ON posts (slug)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_posts_author_id ON posts (author_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_posts_published_at ON posts (published_at)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_posts_trashed_at ON posts (trashed_at)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_post_tags_post_id ON post_tags (post_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_post_tags_tag_id ON post_tags (tag_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_tags_slug ON tags (slug)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_tags_name ON tags (name)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_navigations_sequence ON navigations (sequence)');
    }

    public function transaction(callable $callback): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $callback($this->pdo);
        }

        $this->pdo->beginTransaction();
        try {
            $result = $callback($this->pdo);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
