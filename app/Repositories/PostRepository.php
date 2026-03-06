<?php

declare(strict_types=1);

namespace Blogme\Repositories;

use Blogme\Core\Database;
use PDO;

final class PostRepository
{
    public function __construct(private readonly Database $db, private readonly TagRepository $tags)
    {
    }

    public function countByUser(string $userId): int
    {
        $stmt = $this->db->pdo()->prepare('SELECT COUNT(*) FROM posts WHERE author_id = :uid');
        $stmt->execute(['uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function transferPosts(string $fromUserId, string $toUserId): void
    {
        $stmt = $this->db->pdo()->prepare('UPDATE posts SET author_id = :to_uid WHERE author_id = :from_uid');
        $stmt->execute(['to_uid' => $toUserId, 'from_uid' => $fromUserId]);
    }

    public function deleteByUser(string $userId): void
    {
        $stmt = $this->db->pdo()->prepare('DELETE FROM post_tags WHERE post_id IN (SELECT id FROM posts WHERE author_id = :uid)');
        $stmt->execute(['uid' => $userId]);
        $this->db->pdo()->prepare('DELETE FROM posts WHERE author_id = :uid')->execute(['uid' => $userId]);
    }

    public function create(array $post): void
    {
        $tagIds = $post['tag_ids'] ?? [];
        unset($post['tag_ids']);
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO posts (id, title, slug, excerpt, author_id, password, visibility, content, published_at, created_at, updated_at, pinned_at, trashed_at)
             VALUES (:id, :title, :slug, :excerpt, :author_id, :password, :visibility, :content, :published_at, :created_at, :updated_at, :pinned_at, :trashed_at)'
        );
        $stmt->execute($post);

        foreach ($tagIds as $tagId) {
            $this->db->pdo()->prepare('INSERT INTO post_tags (post_id, tag_id) VALUES (:pid, :tid)')
                ->execute(['pid' => $post['id'], 'tid' => $tagId]);
        }
    }

    public function update(array $post): void
    {
        $tagIds = $post['tag_ids'] ?? [];
        unset($post['tag_ids']);
        $stmt = $this->db->pdo()->prepare(
            'UPDATE posts SET title = :title, slug = :slug, excerpt = :excerpt, author_id = :author_id, password = :password, visibility = :visibility, content = :content, published_at = :published_at, created_at = :created_at, updated_at = :updated_at, pinned_at = :pinned_at WHERE id = :id'
        );
        $stmt->execute($post);
        $this->db->pdo()->prepare('DELETE FROM post_tags WHERE post_id = :pid')->execute(['pid' => $post['id']]);
        foreach ($tagIds as $tagId) {
            $this->db->pdo()->prepare('INSERT INTO post_tags (post_id, tag_id) VALUES (:pid, :tid)')
                ->execute(['pid' => $post['id'], 'tid' => $tagId]);
        }
    }

    public function trash(string $id): void
    {
        $this->db->pdo()->prepare('UPDATE posts SET trashed_at = :trashed WHERE id = :id')
            ->execute(['id' => $id, 'trashed' => time()]);
    }

    public function untrash(string $id): void
    {
        $this->db->pdo()->prepare('UPDATE posts SET trashed_at = 0 WHERE id = :id')->execute(['id' => $id]);
    }

    public function delete(string $id): void
    {
        $this->db->pdo()->prepare('DELETE FROM post_tags WHERE post_id = :id')->execute(['id' => $id]);
        $this->db->pdo()->prepare('DELETE FROM posts WHERE id = :id AND trashed_at != 0')->execute(['id' => $id]);
    }

    public function clearTrash(): void
    {
        $this->db->pdo()->prepare('DELETE FROM post_tags WHERE post_id IN (SELECT id FROM posts WHERE trashed_at != 0)')->execute();
        $this->db->pdo()->prepare('DELETE FROM posts WHERE trashed_at != 0')->execute();
    }

    public function clearExpiredTrash(): void
    {
        $exp = time() - 86400 * 30;
        $this->db->pdo()->prepare('DELETE FROM post_tags WHERE post_id IN (SELECT id FROM posts WHERE trashed_at != 0 AND trashed_at < :exp)')
            ->execute(['exp' => $exp]);
        $this->db->pdo()->prepare('DELETE FROM posts WHERE trashed_at != 0 AND trashed_at < :exp')->execute(['exp' => $exp]);
    }

    /** @return array<int, array<string, mixed>> */
    public function list(array $query, array $config): array
    {
        $sql = 'SELECT p.id, p.title, p.slug, p.excerpt, p.author_id, p.password, p.visibility, p.content, p.published_at, p.created_at, p.updated_at, p.pinned_at, p.trashed_at, u.id as user_id, u.nickname, u.email, u.bio, u.created_at as user_created_at FROM posts p JOIN users u ON p.author_id = u.id';
        $args = [];
        $sql .= $this->buildWhere($query, $config, $args);
        $sql .= ' ORDER BY p.pinned_at DESC, p.published_at DESC, p.created_at DESC';
        if (($query['limit'] ?? 0) > 0 && ($query['offset'] ?? -1) >= 0) {
            $sql .= ' LIMIT ? OFFSET ?';
            $args[] = (int) $query['limit'];
            $args[] = (int) $query['offset'];
        } elseif (($query['limit'] ?? 0) > 0) {
            $sql .= ' LIMIT ?';
            $args[] = (int) $query['limit'];
        }
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($args);
        return $this->hydratePosts($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function count(array $query, array $config): int
    {
        $sql = 'SELECT COUNT(*) FROM posts p JOIN users u ON p.author_id = u.id';
        $args = [];
        $sql .= $this->buildWhere($query, $config, $args);
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($args);
        return (int) $stmt->fetchColumn();
    }

    public function countByType(): array
    {
        $rows = $this->db->pdo()->query('SELECT visibility, COUNT(*) c FROM posts WHERE trashed_at = 0 GROUP BY visibility')->fetchAll(PDO::FETCH_ASSOC);
        $counts = ['All' => 0, 'NonTrash' => 0, 'Public' => 0, 'Private' => 0, 'Password' => 0, 'Draft' => 0, 'Trash' => 0];
        foreach ($rows as $row) {
            switch ($row['visibility']) {
                case 'public':
                    $counts['Public'] = (int) $row['c'];
                    break;
                case 'private':
                    $counts['Private'] = (int) $row['c'];
                    break;
                case 'password':
                    $counts['Password'] = (int) $row['c'];
                    break;
                case 'draft':
                    $counts['Draft'] = (int) $row['c'];
                    break;
            }
        }
        $counts['Trash'] = (int) $this->db->pdo()->query('SELECT COUNT(*) FROM posts WHERE trashed_at != 0')->fetchColumn();
        $counts['All'] = $counts['Public'] + $counts['Private'] + $counts['Password'] + $counts['Draft'] + $counts['Trash'];
        $counts['NonTrash'] = $counts['All'] - $counts['Trash'];
        return $counts;
    }

    /** @return array<int, string> */
    public function listDates(): array
    {
        $rows = $this->db->pdo()->query("SELECT strftime('%Y-%m', datetime(published_at, 'unixepoch')) d FROM posts GROUP BY strftime('%Y-%m', datetime(published_at, 'unixepoch'))")->fetchAll(PDO::FETCH_ASSOC);
        return array_values(array_map(static fn (array $row): string => (string) $row['d'], $rows));
    }

    public function byId(string $id): ?array
    {
        $stmt = $this->db->pdo()->prepare('SELECT p.id, p.title, p.slug, p.excerpt, p.author_id, p.password, p.visibility, p.content, p.published_at, p.created_at, p.updated_at, p.pinned_at, p.trashed_at, u.id as user_id, u.nickname, u.email, u.bio, u.created_at as user_created_at FROM posts p JOIN users u ON p.author_id = u.id WHERE p.id = :id AND p.trashed_at = 0');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $posts = $this->hydratePosts([$row]);
        return $posts[0] ?? null;
    }

    public function bySlug(string $slug): ?array
    {
        $stmt = $this->db->pdo()->prepare('SELECT p.id, p.title, p.slug, p.excerpt, p.author_id, p.password, p.visibility, p.content, p.published_at, p.created_at, p.updated_at, p.pinned_at, p.trashed_at, u.id as user_id, u.nickname, u.email, u.bio, u.created_at as user_created_at FROM posts p JOIN users u ON p.author_id = u.id WHERE p.slug = :slug AND p.trashed_at = 0');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $posts = $this->hydratePosts([$row]);
        return $posts[0] ?? null;
    }

    public function previous(string $id): ?array
    {
        $stmt = $this->db->pdo()->prepare(
            "SELECT p.id, p.title, p.slug, p.excerpt, p.author_id, p.password, p.visibility, p.content, p.published_at, p.created_at, p.updated_at, p.pinned_at, p.trashed_at, u.id as user_id, u.nickname, u.email, u.bio, u.created_at as user_created_at
             FROM posts p JOIN users u ON p.author_id = u.id
             WHERE p.published_at < :now
               AND (p.published_at < (SELECT published_at FROM posts WHERE id = :id1) OR (p.published_at = (SELECT published_at FROM posts WHERE id = :id2) AND p.created_at < (SELECT created_at FROM posts WHERE id = :id3)))
               AND (p.visibility = 'public' OR p.visibility = 'password')
               AND p.trashed_at = 0
             ORDER BY p.published_at DESC, p.created_at DESC
             LIMIT 1"
        );
        $stmt->execute(['now' => time(), 'id1' => $id, 'id2' => $id, 'id3' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        return $this->hydratePosts([$row])[0] ?? null;
    }

    public function next(string $id): ?array
    {
        $stmt = $this->db->pdo()->prepare(
            "SELECT p.id, p.title, p.slug, p.excerpt, p.author_id, p.password, p.visibility, p.content, p.published_at, p.created_at, p.updated_at, p.pinned_at, p.trashed_at, u.id as user_id, u.nickname, u.email, u.bio, u.created_at as user_created_at
             FROM posts p JOIN users u ON p.author_id = u.id
             WHERE p.published_at < :now
               AND (p.published_at > (SELECT published_at FROM posts WHERE id = :id1) OR (p.published_at = (SELECT published_at FROM posts WHERE id = :id2) AND p.created_at > (SELECT created_at FROM posts WHERE id = :id3)))
               AND (p.visibility = 'public' OR p.visibility = 'password')
               AND p.trashed_at = 0
             ORDER BY p.published_at ASC, p.created_at ASC
             LIMIT 1"
        );
        $stmt->execute(['now' => time(), 'id1' => $id, 'id2' => $id, 'id3' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        return $this->hydratePosts([$row])[0] ?? null;
    }

    private function buildWhere(array $q, array $config, array &$args): string
    {
        $sql = '';
        if (($q['tag_id'] ?? '') !== '') {
            $sql .= ' JOIN post_tags pt ON p.id = pt.post_id';
        }
        $sql .= ' WHERE 1=1';

        if (($q['tag_id'] ?? '') !== '') {
            $sql .= ' AND pt.tag_id = ?';
            $args[] = $q['tag_id'];
        }
        if (($q['author_id'] ?? '') !== '') {
            $sql .= ' AND p.author_id = ?';
            $args[] = $q['author_id'];
        }
        if (($q['title'] ?? '') !== '') {
            $sql .= ' AND p.title LIKE ?';
            $args[] = '%' . $q['title'] . '%';
        }
        if (($q['query'] ?? '') !== '') {
            $sql .= ' AND (p.title LIKE ? OR p.content LIKE ?)';
            $args[] = '%' . $q['query'] . '%';
            $args[] = '%' . $q['query'] . '%';
        }
        if (array_key_exists('is_published', $q) && $q['is_published'] !== null) {
            if ((bool) $q['is_published']) {
                $sql .= ' AND p.published_at <= ?';
            } else {
                $sql .= ' AND p.published_at > ?';
            }
            $args[] = time();
        }
        if (array_key_exists('is_trashed', $q) && $q['is_trashed'] !== null) {
            $sql .= ((bool) $q['is_trashed']) ? ' AND p.trashed_at != 0' : ' AND p.trashed_at = 0';
        }
        if (!empty($q['visibilities']) && is_array($q['visibilities'])) {
            $sql .= ' AND p.visibility IN (' . implode(', ', array_fill(0, count($q['visibilities']), '?')) . ')';
            foreach ($q['visibilities'] as $v) {
                $args[] = $v;
            }
        }
        $timezone = (int) ($config['Timezone'] ?? 0);
        if (($q['published_year'] ?? '') !== '') {
            $sql .= " AND strftime('%Y', datetime(published_at + ?, 'unixepoch')) = ?";
            $args[] = $timezone;
            $args[] = $q['published_year'];
        }
        if (($q['published_month'] ?? '') !== '') {
            $sql .= " AND strftime('%m', datetime(published_at + ?, 'unixepoch')) = ?";
            $args[] = $timezone;
            $args[] = $q['published_month'];
        }
        if (($q['published_day'] ?? '') !== '') {
            $sql .= " AND strftime('%d', datetime(published_at + ?, 'unixepoch')) = ?";
            $args[] = $timezone;
            $args[] = $q['published_day'];
        }
        if (($q['published_date'] ?? '') !== '') {
            $sql .= " AND strftime('%Y-%m', datetime(published_at + ?, 'unixepoch')) = ?";
            $args[] = $timezone;
            $args[] = $q['published_date'];
        }
        return $sql;
    }

    /** @param array<int, array<string, mixed>> $rows @return array<int, array<string, mixed>> */
    private function hydratePosts(array $rows): array
    {
        $data = [];
        foreach ($rows as $row) {
            $id = (string) $row['id'];
            $publishedAt = (int) $row['published_at'];
            $post = [
                'ID' => $id,
                'Title' => (string) $row['title'],
                'Slug' => (string) $row['slug'],
                'OriginalExcerpt' => (string) $row['excerpt'],
                'AuthorID' => (string) $row['author_id'],
                'Password' => (string) $row['password'],
                'Visibility' => (string) $row['visibility'],
                'Content' => (string) $row['content'],
                'PinnedAt' => (int) $row['pinned_at'],
                'PublishedAt' => $publishedAt,
                'CreatedAt' => (int) $row['created_at'],
                'UpdatedAt' => (int) $row['updated_at'],
                'TrashedAt' => (int) $row['trashed_at'],
                'Author' => [
                    'ID' => (string) $row['user_id'],
                    'Nickname' => (string) $row['nickname'],
                    'Email' => (string) $row['email'],
                    'Bio' => (string) $row['bio'],
                    'CreatedAt' => (int) $row['user_created_at'],
                    'Gravatar' => 'http://www.gravatar.com/avatar/' . md5(strtolower(trim((string) $row['email']))),
                ],
            ];
            $tags = $this->tags->byPost($id);
            $post['Tags'] = $tags;
            $post['TagsStr'] = implode(',', array_map(static fn (array $t): string => (string) $t['Name'], $tags));
            $post['PublishedDate'] = gmdate('Y-m-d', $publishedAt);
            $post['PublishedAtDatetime'] = gmdate('Y-m-d h:i A', $publishedAt);
            $post['PublishedAtISO'] = gmdate('Y-m-d\TH:i', $publishedAt);
            $post['PublishedYear'] = gmdate('Y', $publishedAt);
            $post['PublishedMonth'] = gmdate('m', $publishedAt);
            $post['PublishedDay'] = gmdate('d', $publishedAt);
            $post['IsPublished'] = time() >= $publishedAt;
            $coverPublic = dirname(__DIR__, 2) . '/public/uploads/covers/' . $id . '.jpg';
            $post['Cover'] = is_file($coverPublic) ? 'uploads/covers/' . $id . '.jpg' : '';
            $post['Excerpt'] = $post['OriginalExcerpt'] !== '' ? $post['OriginalExcerpt'] : $this->excerptFromContent($post['Content']);
            $data[] = $post;
        }
        return $data;
    }

    private function excerptFromContent(string $content): string
    {
        $text = strip_tags($content);
        $text = preg_replace('/[#>*_`\\-]/', '', $text) ?? $text;
        $text = trim($text);
        if (mb_strlen($text) > 200) {
            return mb_substr($text, 0, 200) . '...';
        }
        return $text;
    }
}
