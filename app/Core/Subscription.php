<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use PDO;

final class Subscription
{
    public static function ensureTrial(PDO $pdo, int $userId): array
    {
        $current = self::current($pdo, $userId);
        if ($current) return $current;

        $plan = $pdo->query("SELECT id, trial_days FROM plans WHERE slug='profissional' AND active=1 LIMIT 1")->fetch();
        if (!$plan) throw new \RuntimeException('Plano de teste não configurado.');

        $trialDays = max(1, (int) $plan['trial_days']);
        $trialEnd = (new DateTimeImmutable('now'))->modify('+' . $trialDays . ' days')->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare("INSERT INTO subscriptions (contractor_user_id, plan_id, provider, status, trial_ends_at, expires_at) VALUES (:user_id,:plan_id,'manual','trial',:trial_end,:trial_end)");
        $stmt->execute(['user_id' => $userId, 'plan_id' => $plan['id'], 'trial_end' => $trialEnd]);
        return self::current($pdo, $userId) ?? [];
    }

    public static function current(PDO $pdo, int $userId): ?array
    {
        $stmt = $pdo->prepare("SELECT s.*, p.name AS plan_name, p.slug AS plan_slug, p.monthly_price, p.monthly_request_limit, p.audience
            FROM subscriptions s INNER JOIN plans p ON p.id=s.plan_id
            WHERE s.contractor_user_id=:user_id ORDER BY s.id DESC LIMIT 1");
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        if (!$row) return null;

        if ($row['status'] === 'trial' && !empty($row['trial_ends_at']) && strtotime((string)$row['trial_ends_at']) < time()) {
            $pdo->prepare("UPDATE subscriptions SET status='inadimplente' WHERE id=:id AND status='trial'")->execute(['id' => $row['id']]);
            $row['status'] = 'inadimplente';
        }
        if ($row['status'] === 'ativa' && !empty($row['current_period_end']) && strtotime((string)$row['current_period_end']) < time()) {
            $pdo->prepare("UPDATE subscriptions SET status='inadimplente' WHERE id=:id AND status='ativa'")->execute(['id' => $row['id']]);
            $row['status'] = 'inadimplente';
        }
        return $row;
    }

    public static function hasAccess(PDO $pdo, int $userId): bool
    {
        $sub = self::current($pdo, $userId) ?? self::ensureTrial($pdo, $userId);
        return in_array($sub['status'] ?? '', ['trial','ativa'], true);
    }

    public static function canCreateRequest(PDO $pdo, int $userId): array
    {
        $sub = self::current($pdo, $userId) ?? self::ensureTrial($pdo, $userId);
        if (!in_array($sub['status'] ?? '', ['trial','ativa'], true)) {
            return [false, 'Seu período de acesso terminou. Escolha um plano para publicar novas solicitações.'];
        }
        $limit = $sub['monthly_request_limit'] !== null ? (int) $sub['monthly_request_limit'] : null;
        if ($limit === null) return [true, null];
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM service_requests WHERE contractor_user_id=:user_id AND created_at >= DATE_FORMAT(CURRENT_DATE,'%Y-%m-01')");
        $stmt->execute(['user_id' => $userId]);
        if ((int)$stmt->fetchColumn() >= $limit) {
            return [false, 'Você atingiu o limite mensal do seu plano. Faça upgrade para continuar publicando solicitações.'];
        }
        return [true, null];
    }
}
