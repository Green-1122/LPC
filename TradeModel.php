<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class TradeModel extends Model
{
    public const STRATEGIES = [
        'market' => 'Market order', 'limit' => 'Limit order', 'stop_loss' => 'Stop-loss protection',
        'take_profit' => 'Take-profit target', 'dollar_cost_average' => 'Dollar-cost averaging',
        'value' => 'Value investing', 'growth' => 'Growth investing', 'momentum' => 'Momentum trading',
        'swing' => 'Swing trading', 'trend_following' => 'Trend following', 'mean_reversion' => 'Mean reversion',
        'breakout' => 'Breakout trading', 'pairs' => 'Pairs trading', 'covered_call' => 'Covered call education',
    ];

    public function listByUser(int $userId, int $limit = 30): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.id, t.symbol, t.side, t.strategy, t.quantity, t.entry_price, t.stop_price,
                    t.take_profit_price, t.status, t.execution_mode, t.created_at,
                    ts.settlement_status, ts.transaction_id
             FROM trades t LEFT JOIN trade_settlements ts ON ts.trade_id = t.id
             WHERE t.user_id = :user_id ORDER BY t.created_at DESC LIMIT :limit'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', max(1, min($limit, 100)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function createPaperTrade(int $userId, array $data): int
    {
        $strategy = $data['strategy'] ?? 'market';
        if (!isset(self::STRATEGIES[$strategy])) {
            throw new \InvalidArgumentException('Unsupported trading strategy.');
        }
        $quantity = (float) ($data['quantity'] ?? 0);
        $entryPrice = (float) ($data['entry_price'] ?? 0);
        $symbol = strtoupper(trim((string) ($data['symbol'] ?? '')));
        if ($symbol === '' || $quantity <= 0 || $entryPrice <= 0) {
            throw new \InvalidArgumentException('Symbol, quantity and entry price must be valid.');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO trades
             (user_id, symbol, side, strategy, quantity, entry_price, stop_price, take_profit_price,
              status, execution_mode, created_at)
             VALUES (:user_id, :symbol, :side, :strategy, :quantity, :entry_price, :stop_price,
                     :take_profit_price, "open", "paper", NOW())'
        );
        $stmt->execute([
            'user_id' => $userId, 'symbol' => $symbol,
            'side' => ($data['side'] ?? 'buy') === 'sell' ? 'sell' : 'buy',
            'strategy' => $strategy, 'quantity' => $quantity, 'entry_price' => $entryPrice,
            'stop_price' => $data['stop_price'] ?: null,
            'take_profit_price' => $data['take_profit_price'] ?: null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Close a paper trade. Optional account ID creates a settlement transaction atomically. */
    public function closeOwnedTrade(int $userId, int $tradeId, ?int $accountId = null, ?float $exitPrice = null): bool
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM trades WHERE id = :id AND user_id = :user_id AND status = "open" FOR UPDATE'
            );
            $stmt->execute(['id' => $tradeId, 'user_id' => $userId]);
            $trade = $stmt->fetch();
            if (!$trade) {
                $this->db->rollBack();
                return false;
            }

            $price = $exitPrice ?? (float) $trade['entry_price'];
            if ($price <= 0) {
                throw new \InvalidArgumentException('Exit price must be greater than zero.');
            }
            $pnl = ($price - (float) $trade['entry_price']) * (float) $trade['quantity'];

            $update = $this->db->prepare(
                'UPDATE trades SET status = "closed", exit_price = :exit_price, realized_pnl = :pnl, closed_at = NOW()
                 WHERE id = :id AND user_id = :user_id AND status = "open"'
            );
            $update->execute(['exit_price' => $price, 'pnl' => $pnl, 'id' => $tradeId, 'user_id' => $userId]);

            if ($accountId !== null) {
                $account = $this->db->prepare(
                    'SELECT id, currency FROM accounts WHERE id = :id AND user_id = :user_id AND status = "active" FOR UPDATE'
                );
                $account->execute(['id' => $accountId, 'user_id' => $userId]);
                $owned = $account->fetch();
                if (!$owned) {
                    throw new \RuntimeException('Settlement account not found or inactive.');
                }

                $transaction = $this->db->prepare(
                    'INSERT INTO transactions
                     (user_id, account_id, type, amount, currency, direction, description, reference_id, status, metadata)
                     VALUES (:user_id, :account_id, "trade_settlement", :amount, :currency, :direction,
                             :description, :reference_id, "completed", :metadata)'
                );
                $amount = abs($pnl);
                $transaction->execute([
                    'user_id' => $userId, 'account_id' => $accountId, 'amount' => $amount,
                    'currency' => $owned['currency'], 'direction' => $pnl >= 0 ? 'inbound' : 'outbound',
                    'description' => 'Paper trade settlement: ' . $trade['symbol'],
                    'reference_id' => 'trade:' . $tradeId,
                    'metadata' => json_encode(['trade_id' => $tradeId, 'exit_price' => $price, 'pnl' => $pnl], JSON_THROW_ON_ERROR),
                ]);
                $transactionId = (int) $this->db->lastInsertId();

                $settlement = $this->db->prepare(
                    'INSERT INTO trade_settlements (trade_id, transaction_id, account_id, settlement_status, settlement_date)
                     VALUES (:trade_id, :transaction_id, :account_id, "settled", NOW())'
                );
                $settlement->execute(['trade_id' => $tradeId, 'transaction_id' => $transactionId, 'account_id' => $accountId]);
            }

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
