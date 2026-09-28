<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Models\PensionModel;
use App\Models\AccountModel;

final class PensionController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        $userId = (int) $_SESSION['user']['id'];
        $model = new PensionModel();

        $this->render('pension/index', [
            'plans' => $model->plans(),
            'accounts' => $model->accountsByUser($userId),
            'contributions' => $model->contributionsByUser($userId),
            'bankAccounts' => (new AccountModel())->getByUser($userId),
        ], 'app');
    }

    public function create(): void
    {
        AuthMiddleware::requireAuth();
        $userId = (int) $_SESSION['user']['id'];
        $this->render('pension/create', [
            'plans' => (new PensionModel())->plans(),
            'bankAccounts' => (new AccountModel())->getByUser($userId),
        ], 'app');
    }

    public function store(): void
    {
        AuthMiddleware::requireAuth();
        validate_csrf();

        $planId = (int) ($_POST['plan_id'] ?? 0);
        $initial = max(0, (float) ($_POST['initial_contribution'] ?? 0));
        $monthly = max(0, (float) ($_POST['monthly_contribution'] ?? 0));
        $retirementAge = (int) ($_POST['target_retirement_age'] ?? 65);
        $sourceAccountId = !empty($_POST['source_account_id']) ? (int) $_POST['source_account_id'] : null;

        if ($planId < 1 || $retirementAge < 50 || $retirementAge > 85) {
            flash('error', 'Please provide a valid pension plan and retirement age.');
            redirect('/pension/create');
        }

        try {
            $userId = (int) $_SESSION['user']['id'];
            $model = new PensionModel();
            $accountModel = new AccountModel();

            if ($sourceAccountId !== null) {
                $sourceAccount = $accountModel->findOwned($userId, $sourceAccountId);
                if (!$sourceAccount) {
                    throw new \RuntimeException('Source account not found.');
                }
                if ((float) $sourceAccount['balance'] < $initial) {
                    throw new \RuntimeException('Source account has insufficient balance.');
                }
            }

            $model->createAccount([
                'user_id' => $userId,
                'plan_id' => $planId,
                'account_number' => 'HN-PEN-' . strtoupper(bin2hex(random_bytes(4))),
                'current_balance' => $initial,
                'monthly_contribution' => $monthly,
                'target_retirement_age' => $retirementAge,
                'beneficiary_name' => trim($_POST['beneficiary_name'] ?? ''),
                'beneficiary_relation' => trim($_POST['beneficiary_relation'] ?? ''),
            ]);

            if ($initial > 0 && $sourceAccountId !== null) {
                $pensionAccountId = (int) $this->db->lastInsertId();
                $model->contribute(
                    $userId,
                    $pensionAccountId,
                    $initial,
                    'Initial contribution',
                    $sourceAccountId
                );
            }

            flash('success', 'Retirement account created successfully' . ($initial > 0 ? ' and funded.' : '.'));
            redirect('/pension');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
            redirect('/pension/create');
        }
    }

    public function contribute(): void
    {
        AuthMiddleware::requireAuth();
        validate_csrf();

        $userId = (int) $_SESSION['user']['id'];
        $accountId = (int) ($_POST['account_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $sourceAccountId = !empty($_POST['source_account_id']) ? (int) $_POST['source_account_id'] : null;
        $source = trim($_POST['source'] ?? 'Manual contribution');

        try {
            if ($sourceAccountId !== null) {
                $accountModel = new AccountModel();
                $sourceAccount = $accountModel->findOwned($userId, $sourceAccountId);
                if (!$sourceAccount) {
                    throw new \RuntimeException('Source account not found.');
                }
            }

            (new PensionModel())->contribute(
                $userId,
                $accountId,
                $amount,
                $source,
                $sourceAccountId
            );
            flash('success', 'Contribution posted to your retirement account.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('/pension');
    }
}
