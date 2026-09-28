<?php

declare(strict_types=1);

namespace App\Helpers;

final class Audit
{
    private static $model = null;

    private static function model()
    {
        if (self::$model === null) {
            self::$model = new \App\Models\AuditModel();
        }
        return self::$model;
    }

    public static function log(
        ?int $userId,
        string $action,
        string $resource,
        ?int $resourceId = null,
        ?array $metadata = null
    ): int {
        return self::model()->log($userId, $action, $resource, $resourceId, $metadata);
    }

    public static function logUserAction(int $userId, string $action, ?array $meta = null): int
    {
        return self::log($userId, $action, 'users', $userId, $meta);
    }

    public static function logAccountAction(int $userId, int $accountId, string $action, ?array $meta = null): int
    {
        return self::log($userId, $action, 'accounts', $accountId, $meta);
    }

    public static function logTransactionAction(
        int $userId,
        int $transactionId,
        string $action,
        ?array $meta = null
    ): int {
        return self::log($userId, $action, 'transactions', $transactionId, $meta);
    }
}
