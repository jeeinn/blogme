<?php

declare(strict_types=1);

namespace Blogme\Repositories;

use Blogme\Core\Database;
use PDO;

final class TagRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function list(int $offset, int $limit, string $keyword = ''): array
    {
        if ($keyword !== '') {
            $stmt = $this->db->pdo()->prepare('SELECT t.id, t.slug, t.name, t.description, t.created_at, COUNT(pt.post_id) AS post_count FROM tags t LEFT JOIN post_tags pt ON t.id = pt.tag_id WHERE name LIKE :keyword GROUP BY t.id, t.slug, t.name, t.description ORDER BY t.created_at DESC LIMIT :offset, :limit');
            $stmt->bindValue(':keyword', '%' . $keyword . '%');
        } else {
            $stmt = $this->db->pdo()->prepare('SELECT t.id, t.slug, t.name, t.description, t.created_at, COUNT(pt.post_id) AS post_count FROM tags t LEFT JOIN post_tags pt ON t.id = pt.tag_id GROUP BY t.id, t.slug, t.name, t.description ORDER BY t.created_at DESC LIMIT :offset, :limit');
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$this, 'hydrate'], $rows);
    }

    public function count(string $keyword = ''): int
    {
        if ($keyword !== '') {
            $stmt = $this->db->pdo()->prepare('SELECT COUNT(*) FROM tags WHERE name LIKE :keyword');
            $stmt->execute(['keyword' => '%' . $keyword . '%']);
            return (int) $stmt->fetchColumn();
        }
        return (int) $this->db->pdo()->query('SELECT COUNT(*) FROM tags')->fetchColumn();
    }

    public function create(array $tag): void
    {
        $stmt = $this->db->pdo()->prepare('INSERT INTO tags (id, slug, name, description, created_at) VALUES (:id, :slug, :name, :description, :created_at)');
        $stmt->execute($tag);
    }

    public function byId(string $id): ?array
    {
        $stmt = $this->db->pdo()->prepare('SELECT t.id, t.slug, t.name, t.description, t.created_at, COUNT(pt.post_id) AS post_count FROM tags t LEFT JOIN post_tags pt ON t.id = pt.tag_id WHERE t.id = :id GROUP BY t.id, t.slug, t.name, t.description');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $this->hydrate($row) : null;
    }

    public function bySlug(string $slug): ?array
    {
        $stmt = $this->db->pdo()->prepare('SELECT id, slug, name, description, created_at FROM tags WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $this->hydrate($row) : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function byNames(array $names): array
    {
        if ($names === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $stmt = $this->db->pdo()->prepare("SELECT id, slug, name, description, created_at FROM tags WHERE name IN ($placeholders)");
        $stmt->execute($names);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$this, 'hydrate'], $rows);
    }

    /** @return array<int, array<string, mixed>> */
    public function byPost(string $postId): array
    {
        $stmt = $this->db->pdo()->prepare('SELECT t.id, t.slug, t.name, t.description, t.created_at FROM tags t JOIN post_tags pt ON t.id = pt.tag_id WHERE pt.post_id = :pid');
        $stmt->execute(['pid' => $postId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$this, 'hydrate'], $rows);
    }

    /** @return array<int, array<string, mixed>> */
    public function mostUsed(int $limit = 10): array
    {
        $stmt = $this->db->pdo()->prepare('SELECT t.id, t.slug, t.name, t.description, t.created_at, COUNT(pt.post_id) AS post_count FROM tags t JOIN post_tags pt ON t.id = pt.tag_id GROUP BY t.id, t.slug, t.name, t.description ORDER BY post_count DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$this, 'hydrate'], $rows);
    }

    public function update(array $tag): void
    {
        $stmt = $this->db->pdo()->prepare('UPDATE tags SET slug = :slug, name = :name, description = :description, created_at = :created_at WHERE id = :id');
        $stmt->execute($tag);
    }

    public function delete(string $id): void
    {
        $this->db->pdo()->prepare('DELETE FROM post_tags WHERE tag_id = :id')->execute(['id' => $id]);
        $this->db->pdo()->prepare('DELETE FROM tags WHERE id = :id')->execute(['id' => $id]);
    }

    private function hydrate(array $row): array
    {
        $row['ID'] = $row['id'];
        $row['Slug'] = $row['slug'];
        $row['Name'] = $row['name'];
        $row['Description'] = $row['description'] ?? '';
        $row['CreatedAt'] = (int) $row['created_at'];
        $row['PostCount'] = isset($row['post_count']) ? (int) $row['post_count'] : 0;
        return $row;
    }
}
