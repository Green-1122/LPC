<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class TransactionModel extends Model
{
    public function recentByUser(int $userId, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, a.account_number
             FROM transactions t
             LEFT JOIN accounts a ON a.id = t.account_id
             WHERE t.user_id = :user_id
             ORDER BY t.created_at DESC, t.id DESC LIMIT :limit'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', max(1, min($limit, 500)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO transactions
             (user_id, account_id, type, amount, currency, direction, description, reference_id, status, metadata, created_at)
             VALUES (:user_id, :account_id, :type, :amount, :currency, :direction, :description, :reference_id, :status, :metadata, NOW())'
        );
        $metadata = $data['metadata'] ?? null;
        if (is_array($metadata)) {
            $metadata = json_encode($metadata, JSON_THROW_ON_ERROR);
        }
        $stmt->execute([
            'user_id' => (int) $data['user_id'],
            'account_id' => $data['account_id'] ?? null,
            'type' => trim((string) $data['type']),
            'amount' => $data['amount'],
            'currency' => strtoupper((string) ($data['currency'] ?? 'USD')),
            'direction' => $data['direction'],
            'description' => trim((string) $data['description']),
            'reference_id' => $data['reference_id'] ?? null,
            'status' => $data['status'] ?? 'completed',
            'metadata' => $metadata,
        ]);
        return (int) $this->db->lastInsertId();
    }
}
