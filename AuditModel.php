<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class AuditModel extends Model
{
    public function log(
        ?int $actorUserId,
        string $action,
        string $resource,
        ?int $resourceId = null,
        ?array $metadata = null
    ): int {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $meta = $metadata !== null ? json_encode($metadata, JSON_THROW_ON_ERROR) : null;

        $stmt = $this->db->prepare(
            'INSERT INTO audit_events
             (actor_user_id, action, resource, resource_id, ip_address, user_agent, metadata, created_at)
             VALUES (:actor_user_id, :action, :resource, :resource_id, :ip_address, :user_agent, :metadata, NOW())'
        );
        $stmt->execute([
            'actor_user_id' => $actorUserId,
            'action' => trim((string) $action),
            'resource' => trim((string) $resource),
            'resource_id' => $resourceId,
            'ip_address' => $ip,
            'user_agent' => substr((string) $ua, 0, 255),
            'metadata' => $meta,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function byUser(int $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM audit_events WHERE actor_user_id = :user_id ORDER BY created_at DESC LIMIT :limit'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', max(1, min($limit, 500)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function byResource(string $resource, ?int $resourceId = null, int $limit = 50): array
    {
        if ($resourceId !== null) {
            $stmt = $this->db->prepare(
                'SELECT * FROM audit_events
                 WHERE resource = :resource AND resource_id = :resource_id
                 ORDER BY created_at DESC LIMIT :limit'
            );
            $stmt->bindValue('resource', $resource, PDO::PARAM_STR);
            $stmt->bindValue('resource_id', $resourceId, PDO::PARAM_INT);
        } else {
            $stmt = $this->db->prepare(
                'SELECT * FROM audit_events WHERE resource = :resource ORDER BY created_at DESC LIMIT :limit'
            );
            $stmt->bindValue('resource', $resource, PDO::PARAM_STR);
        }
        $stmt->bindValue('limit', max(1, min($limit, 500)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
