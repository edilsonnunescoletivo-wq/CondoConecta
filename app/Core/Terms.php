<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Terms
{
    public const VERSION = '2026-10-13';

    public static function accepted(PDO $pdo, int $userId): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM terms_acceptances WHERE user_id = :user_id AND terms_version = :version LIMIT 1');
        $stmt->execute(['user_id' => $userId, 'version' => self::VERSION]);
        return (bool) $stmt->fetchColumn();
    }

    public static function record(PDO $pdo, int $userId, string $source = 'atualizacao'): void
    {
        $secret = getenv('TERMS_AUDIT_SECRET') ?: '';
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $ipHash = $ip !== '' && $secret !== '' ? hash('sha256', $ip . '|' . $secret) : null;
        $uaHash = $ua !== '' ? hash('sha256', $ua) : null;

        $stmt = $pdo->prepare("INSERT INTO terms_acceptances (user_id, terms_version, acceptance_source, ip_hash, user_agent_hash)
            VALUES (:user_id, :version, :source, :ip_hash, :ua_hash)
            ON DUPLICATE KEY UPDATE accepted_at = CURRENT_TIMESTAMP, acceptance_source = VALUES(acceptance_source), ip_hash = VALUES(ip_hash), user_agent_hash = VALUES(user_agent_hash)");
        $stmt->execute([
            'user_id' => $userId,
            'version' => self::VERSION,
            'source' => in_array($source, ['cadastro','login','atualizacao'], true) ? $source : 'atualizacao',
            'ip_hash' => $ipHash,
            'ua_hash' => $uaHash,
        ]);
    }
}
