<?php

declare(strict_types=1);

namespace Blogme\Repositories;

use Blogme\Core\Database;
use PDO;

final class NavigationRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $stmt = $this->db->pdo()->query('SELECT id, url, name, sequence FROM navigations ORDER BY sequence ASC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['ID'] = $row['id'];
            $row['URL'] = $row['url'];
            $row['Name'] = $row['name'];
            $row['Sequence'] = (int) $row['sequence'];
        }
        return $rows;
    }

    public function clear(): void
    {
        $this->db->pdo()->exec('DELETE FROM navigations');
    }

    public function create(array $nav): void
    {
        $stmt = $this->db->pdo()->prepare('INSERT INTO navigations (id, url, name, sequence) VALUES (:id, :url, :name, :sequence)');
        $stmt->execute($nav);
    }
}
