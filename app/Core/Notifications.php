<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Notifications
{
    public static function notifyUrgentRequest(PDO $pdo, int $requestId): int
    {
        $requestStmt = $pdo->prepare('SELECT id, title, urgency, city, state FROM service_requests WHERE id = :id LIMIT 1');
        $requestStmt->execute(['id' => $requestId]);
        $request = $requestStmt->fetch();

        if (!$request || !in_array((string) $request['urgency'], ['urgente', 'agora'], true)) {
            return 0;
        }

        $matches = Marketplace::matchingSuppliersForRequest($pdo, $requestId);
        if (!$matches) {
            return 0;
        }

        $type = $request['urgency'] === 'agora' ? 'preciso_agora' : 'oportunidade_urgente';
        $title = $request['urgency'] === 'agora' ? '🚨 Preciso Agora na sua região' : '⚠️ Nova oportunidade urgente';
        $message = sprintf('%s — %s/%s', (string) $request['title'], (string) $request['city'], (string) $request['state']);
        $link = 'solicitacao.php?id=' . (int) $request['id'];

        $stmt = $pdo->prepare("INSERT IGNORE INTO notifications
            (user_id, type, title, message, link, reference_type, reference_id)
            VALUES (:user_id, :type, :title, :message, :link, 'service_request', :reference_id)");

        $created = 0;
        foreach ($matches as $match) {
            $stmt->execute([
                'user_id' => (int) $match['user_id'],
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'link' => $link,
                'reference_id' => (int) $request['id'],
            ]);
            $created += $stmt->rowCount();
        }

        return $created;
    }

    public static function unreadCount(PDO $pdo, int $userId): int
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND read_at IS NULL');
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function forUser(PDO $pdo, int $userId, int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));
        $stmt = $pdo->prepare("SELECT id, type, title, message, link, read_at, created_at
                               FROM notifications
                               WHERE user_id = :user_id
                               ORDER BY created_at DESC
                               LIMIT {$limit}");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function markAllRead(PDO $pdo, int $userId): void
    {
        $stmt = $pdo->prepare('UPDATE notifications SET read_at = COALESCE(read_at, CURRENT_TIMESTAMP) WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    public static function markRead(PDO $pdo, int $userId, int $notificationId): void
    {
        $stmt = $pdo->prepare('UPDATE notifications SET read_at = COALESCE(read_at, CURRENT_TIMESTAMP) WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $notificationId, 'user_id' => $userId]);
    }
}
