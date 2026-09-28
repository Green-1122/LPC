<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class LoanModel extends Model
{
    public function recentByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM loans WHERE user_id = :user_id ORDER BY status DESC, created_at DESC LIMIT 5'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO loans
             (user_id, loan_name, principal_amount, remaining_balance, annual_rate, term_months, status, created_at)
             VALUES (:user_id, :loan_name, :principal_amount, :principal_amount, :annual_rate, :term_months, :status, NOW())'
        );
        $stmt->execute([
            'user_id' => (int) $data['user_id'],
            'loan_name' => trim((string) $data['loan_name']),
            'principal_amount' => $data['principal_amount'],
            'annual_rate' => $data['annual_rate'] ?? 0,
            'term_months' => $data['term_months'] ?? 60,
            'status' => $data['status'] ?? 'review',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateStatus(int $userId, int $loanId, string $status): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE loans SET status = :status, updated_at = NOW() WHERE id = :id AND user_id = :user_id'
        );
        return $stmt->execute(['status' => $status, 'id' => $loanId, 'user_id' => $userId]);
    }
}
