<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

final class AccountModel extends Model
{
    public function getByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM accounts WHERE user_id = :user_id ORDER BY is_primary DESC, created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function getPrimary(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM accounts WHERE user_id = :user_id AND status = "active" ORDER BY is_primary DESC, created_at DESC LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public function findOwned(int $userId, int $accountId, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM accounts WHERE id = :id AND user_id = :user_id LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $accountId, 'user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO accounts (user_id, account_number, account_type, balance, currency, status, is_primary, created_at)
             VALUES (:user_id, :account_number, :account_type, :balance, :currency, :status, :is_primary, NOW())'
        );
        $stmt->execute([
            'user_id' => (int) $data['user_id'],
            'account_number' => trim((string) $data['account_number']),
            'account_type' => trim((string) $data['account_type']),
            'balance' => $data['balance'] ?? 0,
            'currency' => strtoupper((string) ($data['currency'] ?? 'USD')),
            'status' => $data['status'] ?? 'active',
            'is_primary' => (int) ($data['is_primary'] ?? 0),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function adjustBalance(int $userId, int $accountId, string $amount): bool
    {
        if (!is_numeric($amount) || (float) $amount == 0.0) {
            throw new \InvalidArgumentException('Balance adjustment must be non-zero.');
        }

        $stmt = $this->db->prepare(
            'UPDATE accounts SET balance = balance + :amount, updated_at = NOW()
             WHERE id = :id AND user_id = :user_id AND status = "active"
             AND balance + :amount >= 0'
        );
        $stmt->execute(['amount' => $amount, 'id' => $accountId, 'user_id' => $userId]);
        return $stmt->rowCount() === 1;
    }
}
