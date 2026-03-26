<?php
// ─────────────────────────────────────────────
//  app/repositories/UserRepository.php
//  Espejo de app/repositories/user_repository.py
// ─────────────────────────────────────────────

namespace App\Repositories;

use App\Db\Session;
use App\Core\Security;
use PDO;

class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Session::getConnection();
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): array
    {
        $id   = $this->generateUuid();
        $now  = date('Y-m-d H:i:s');
        $hash = Security::hashPassword($data['password']);

        $stmt = $this->db->prepare('
            INSERT INTO users (id, email, password_hash, role, is_active, is_verified, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $id,
            strtolower(trim($data['email'])),
            $hash,
            $data['role']        ?? 'user',
            $data['is_active']   ?? true,
            $data['is_verified'] ?? false,
            $now,
            $now,
        ]);

        return $this->findById($id);
    }

    public function updateLastLogin(string $id): void
    {
        $stmt = $this->db->prepare('UPDATE users SET last_login = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $id]);
    }

    public function setResetToken(string $email, string $token, \DateTime $expires): bool
    {
        $stmt = $this->db->prepare('
            UPDATE users SET reset_token = ?, reset_token_expire = ?, updated_at = ? WHERE email = ?
        ');
        return $stmt->execute([$token, $expires->format('Y-m-d H:i:s'), date('Y-m-d H:i:s'), strtolower($email)]);
    }

    public function findByResetToken(string $token): ?array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM users WHERE reset_token = ? AND reset_token_expire > ? LIMIT 1
        ');
        $stmt->execute([$token, date('Y-m-d H:i:s')]);
        return $stmt->fetch() ?: null;
    }

    public function resetPassword(string $id, string $newPassword): void
    {
        $stmt = $this->db->prepare('
            UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expire = NULL, updated_at = ? WHERE id = ?
        ');
        $stmt->execute([Security::hashPassword($newPassword), date('Y-m-d H:i:s'), $id]);
    }

    public function verifyEmail(string $id): void
    {
        $stmt = $this->db->prepare('UPDATE users SET is_verified = 1, updated_at = ? WHERE id = ?');
        $stmt->execute([date('Y-m-d H:i:s'), $id]);
    }

    // ── Helpers ───────────────────────────────

    private function generateUuid(): string
{
    // Una alternativa más compatible si random_bytes falla
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
}
