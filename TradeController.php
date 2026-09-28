<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Models\TradeModel;
use App\Models\AccountModel;

final class TradeController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        $userId = (int) $_SESSION['user']['id'];
        $this->render('trading/index', [
            'trades' => (new TradeModel())->listByUser($userId),
            'strategies' => TradeModel::STRATEGIES,
            'bankAccounts' => (new AccountModel())->getByUser($userId),
        ], 'app');
    }

    public function quick(): void
    {
        AuthMiddleware::requireAuth();
        $this->render('trading/quick', ['strategies' => TradeModel::STRATEGIES], 'app');
    }

    public function execute(): void
    {
        AuthMiddleware::requireAuth();
        validate_csrf();

        try {
            (new TradeModel())->createPaperTrade((int) $_SESSION['user']['id'], [
                'symbol' => $_POST['symbol'] ?? '',
                'side' => $_POST['side'] ?? 'buy',
                'strategy' => $_POST['strategy'] ?? 'market',
                'quantity' => $_POST['quantity'] ?? 0,
                'entry_price' => $_POST['entry_price'] ?? 0,
                'stop_price' => $_POST['stop_price'] ?? null,
                'take_profit_price' => $_POST['take_profit_price'] ?? null,
            ]);
            flash('success', 'Paper trade created. Live execution is disabled until a regulated broker is connected.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('/trade');
    }

    public function close(): void
    {
        AuthMiddleware::requireAuth();
        validate_csrf();

        try {
            $userId = (int) $_SESSION['user']['id'];
            $tradeId = (int) ($_POST['trade_id'] ?? 0);
            $exitPrice = !empty($_POST['exit_price']) ? (float) $_POST['exit_price'] : null;
            $accountId = !empty($_POST['account_id']) ? (int) $_POST['account_id'] : null;

            if ($accountId !== null) {
                $accountModel = new AccountModel();
                $account = $accountModel->findOwned($userId, $accountId);
                if (!$account) {
                    throw new \RuntimeException('Settlement account not found.');
                }
            }

            $closed = (new TradeModel())->closeOwnedTrade(
                $userId,
                $tradeId,
                $accountId,
                $exitPrice
            );
            flash($closed ? 'success' : 'error',
                $closed ? 'Paper trade closed and settled.' : 'Trade was not found or is already closed.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('/trade');
    }
}
