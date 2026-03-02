<?php

declare(strict_types=1);

namespace Blogme\Repositories;

use Blogme\Core\Database;
use PDO;

final class UserRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function create(array $user): void
    {
        $stmt = $this->db->pdo()->prepare('INSERT INTO users (id, email, nickname, password, bio, created_at) VALUES (:id, :email, :nickname, :password, :bio, :created_at)');
        $stmt->execute($user);
    }

    public function byEmail(string $email): ?array
    {
        $stmt = $this->db->pdo()->prepare('SELECT id, email, nickname, password, bio, created_at FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $this->hydrate($row) : null;
    }

    public function byId(string $id): ?array
    {
        $stmt = $this->db->pdo()->prepare('SELECT id, email, nickname, password, bio, created_at FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $this->hydrate($row) : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $rows = $this->db->pdo()->query('SELECT id, email, nickname, password, bio, created_at FROM users')->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn (array $row): array => $this->hydrate($row), $rows);
    }

    public function nicknameExists(string $nickname): bool
    {
        $stmt = $this->db->pdo()->prepare('SELECT EXISTS(SELECT 1 FROM users WHERE nickname = :nickname)');
        $stmt->execute(['nickname' => $nickname]);
        return (int) $stmt->fetchColumn() === 1;
    }

    public function update(string $id, string $nickname, string $bio, string $email): void
    {
        $stmt = $this->db->pdo()->prepare('UPDATE users SET nickname = :nickname, bio = :bio, email = :email WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'nickname' => $nickname,
            'bio' => $bio,
            'email' => $email,
        ]);
    }

    public function updatePassword(string $id, string $password): void
    {
        $stmt = $this->db->pdo()->prepare('UPDATE users SET password = :password WHERE id = :id');
        $stmt->execute(['id' => $id, 'password' => $password]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->db->pdo()->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): array
    {
        $row['CreatedAt'] = (int) $row['created_at'];
        $row['ID'] = $row['id'];
        $row['Email'] = $row['email'];
        $row['Nickname'] = $row['nickname'];
        $row['Password'] = $row['password'];
        $row['Bio'] = $row['bio'];
        $row['Gravatar'] = 'http://www.gravatar.com/avatar/' . md5(strtolower(trim((string) $row['email'])));
        return $row;
    }
}
