<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class PensionModel extends Model
{
    public function plans(): array
    {
        return $this->db->query(
            'SELECT id, name, plan_type, description, annual_rate, minimum_contribution, employer_match_rate, risk_level
             FROM pension_plans WHERE is_active = 1 ORDER BY annual_rate DESC'
        )->fetchAll();
    }

    public function accountsByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT pa.*, pp.name AS plan_name, pp.plan_type, pp.annual_rate
             FROM pension_accounts pa JOIN pension_plans pp ON pp.id = pa.plan_id
             WHERE pa.user_id = :user_id ORDER BY pa.created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function contributionsByUser(int $userId, int $limit = 12): array
    {
        $stmt = $this->db->prepare(
            'SELECT pc.*, pa.account_number
             FROM pension_contributions pc
             JOIN pension_accounts pa ON pa.id = pc.pension_account_id
             WHERE pa.user_id = :user_id ORDER BY pc.created_at DESC LIMIT :limit'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', max(1, min($limit, 100)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function createAccount(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pension_accounts
             (user_id, plan_id, account_number, current_balance, monthly_contribution,
              employer_contribution_monthly, target_retirement_age, beneficiary_name,
              beneficiary_relation, status, created_at)
             VALUES (:user_id, :plan_id, :account_number, :current_balance, :monthly_contribution,
                     :employer_contribution_monthly, :target_retirement_age, :beneficiary_name,
                     :beneficiary_relation, :status, NOW())'
        );
        $stmt->execute([
            'user_id' => (int) $data['user_id'],
            'plan_id' => (int) $data['plan_id'],
            'account_number' => trim((string) $data['account_number']),
            'current_balance' => $data['current_balance'] ?? 0,
            'monthly_contribution' => $data['monthly_contribution'] ?? 0,
            'employer_contribution_monthly' => $data['employer_contribution_monthly'] ?? 0,
            'target_retirement_age' => (int) $data['target_retirement_age'],
            'beneficiary_name' => trim((string) $data['beneficiary_name']),
            'beneficiary_relation' => trim((string) $data['beneficiary_relation']),
            'status' => $data['status'] ?? 'active',
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Post a contribution atomically. When $sourceAccountId is supplied, the
     * user's active bank account is debited and the resulting transaction is
     * linked to the contribution through source_account_id/reference_id.
     */
    public function contribute(
        int $userId,
        int $accountId,
        float $amount,
        string $source,
        ?int $sourceAccountId = null
    ): bool {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Contribution must be greater than zero.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'SELECT id FROM pension_accounts
                 WHERE id = :account_id AND user_id = :user_id AND status = "active" FOR UPDATE'
            );
            $stmt->execute(['account_id' => $accountId, 'user_id' => $userId]);
            if (!$stmt->fetch()) {
                throw new \RuntimeException('Pension account not found or inactive.');
            }

            $transactionId = null;
            if ($sourceAccountId !== null) {
                $bank = $this->db->prepare(
                    'SELECT id, currency, balance FROM accounts
                     WHERE id = :id AND user_id = :user_id AND status = "active" FOR UPDATE'
                );
                $bank->execute(['id' => $sourceAccountId, 'user_id' => $userId]);
                $sourceAccount = $bank->fetch();
                if (!$sourceAccount || (float) $sourceAccount['balance'] < $amount) {
                    throw new \RuntimeException('Source account not found or has insufficient funds.');
                }

                $debit = $this->db->prepare(
                    'UPDATE accounts SET balance = balance - :amount, updated_at = NOW()
                     WHERE id = :id AND user_id = :user_id AND balance >= :amount'
                );
                $debit->execute(['amount' => $amount, 'id' => $sourceAccountId, 'user_id' => $userId]);
                if ($debit->rowCount() !== 1) {
                    throw new \RuntimeException('Unable to debit source account.');
                }

                $transaction = $this->db->prepare(
                    'INSERT INTO transactions
                     (user_id, account_id, type, amount, currency, direction, description, reference_id, status, metadata)
                     VALUES (:user_id, :account_id, "pension_contribution", :amount, :currency,
                             "outbound", :description, :reference_id, "completed", :metadata)'
                );
                $transaction->execute([
                    'user_id' => $userId,
                    'account_id' => $sourceAccountId,
                    'amount' => $amount,
                    'currency' => $sourceAccount['currency'],
                    'description' => $source,
                    'reference_id' => 'pension:' . $accountId,
                    'metadata' => json_encode(['pension_account_id' => $accountId], JSON_THROW_ON_ERROR),
                ]);
                $transactionId = (int) $this->db->lastInsertId();
            }

            $update = $this->db->prepare(
                'UPDATE pension_accounts SET current_balance = current_balance + :amount, updated_at = NOW() WHERE id = :id'
            );
            $update->execute(['amount' => $amount, 'id' => $accountId]);

            $insert = $this->db->prepare(
                'INSERT INTO pension_contributions
                 (pension_account_id, amount, contribution_type, source_account_id, source, status, created_at)
                 VALUES (:account_id, :amount, "personal", :source_account_id, :source, "completed", NOW())'
            );
            $insert->execute([
                'account_id' => $accountId,
                'amount' => $amount,
                'source_account_id' => $sourceAccountId,
                'source' => $source,
            ]);

            $this->db->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }
}
