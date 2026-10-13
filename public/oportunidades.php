<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Marketplace;
use App\Core\Notifications;
use Throwable;

$user = Auth::requireAuth();
if (($user['role'] ?? '') !== 'fornecedor') {
    redirect('painel.php');
}

$pdo = Database::connection($config);
$matches = Marketplace::supplierOpportunityMatches($pdo, (int) $user['id']);
$opportunities = [];

if ($matches) {
    $params = ['proposal_user_id' => $user['id']];
    $placeholders = [];
    foreach (array_keys($matches) as $index => $requestId) {
        $key = 'request_' . $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $requestId;
    }

    $sql = "SELECT sr.id, sr.title, sr.description, sr.urgency, sr.city, sr.state, sr.desired_date, sr.created_at,
                   c.name AS category_name, cp.display_name AS contractor_name,
                   p.status AS proposal_status, p.amount AS proposal_amount
            FROM service_requests sr
            INNER JOIN categories c ON c.id = sr.category_id
            INNER JOIN contractor_profiles cp ON cp.user_id = sr.contractor_user_id
            LEFT JOIN proposals p ON p.service_request_id = sr.id AND p.supplier_user_id = :proposal_user_id
            WHERE sr.id IN (" . implode(',', $placeholders) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $opportunities = $stmt->fetchAll();

    foreach ($opportunities as &$opportunity) {
        $opportunity['distance_km'] = $matches[(int) $opportunity['id']] ?? null;
    }
    unset($opportunity);

    $urgencyRank = ['normal' => 1, 'urgente' => 2, 'agora' => 3];
    usort($opportunities, static function (array $a, array $b) use ($urgencyRank): int {
        $rank = ($urgencyRank[$b['urgency']] ?? 0) <=> ($urgencyRank[$a['urgency']] ?? 0);
        if ($rank !== 0) return $rank;

        $aDistance = $a['distance_km'];
        $bDistance = $b['distance_km'];
        if ($aDistance === null && $bDistance !== null) return 1;
        if ($aDistance !== null && $bDistance === null) return -1;
        if ($aDistance !== null && $bDistance !== null) {
            $distanceCmp = $aDistance <=> $bDistance;
            if ($distanceCmp !== 0) return $distanceCmp;
        }
        return strcmp((string) $b['created_at'], (string) $a['created_at']);
    });
}

try {
    $unreadNotifications = Notifications::unreadCount($pdo, (int) $user['id']);
} catch (Throwable $exception) {
    $unreadNotifications = 0;
}

$urgencyLabels = ['normal' => 'Normal', 'urgente' => 'Urgente', 'agora' => 'Preciso Agora'];
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Oportunidades — <?= e((string) ($config['name'] ?? 'CondoConecta')) ?></title><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="dashboard-page">
<header class="topbar"><div class="container nav"><a class="brand" href="painel.php">Condo<span>Conecta</span></a><nav><a href="localizacao-fornecedor.php">Minha localização</a><a href="notificacoes.php">🔔 Notificações<?= $unreadNotifications > 0 ? ' (' . $unreadNotifications . ')' : '' ?></a><a href="minhas-propostas.php">Minhas propostas</a><a class="button button-ghost" href="painel.php">Painel</a></nav></div></header>
<main class="container dashboard-shell">
    <div class="page-heading"><div><span class="badge">FORNECEDOR</span><h1>Oportunidades compatíveis</h1><p>Chamados urgentes vêm primeiro. Quando há geolocalização nos dois lados, os mais próximos aparecem antes e o seu raio máximo de atendimento é respeitado.</p></div></div>
    <?php if (!$opportunities): ?>
        <div class="empty-state"><h2>Nenhuma oportunidade compatível agora</h2><p>Confira categorias, regiões, atendimento emergencial e sua localização de referência.</p><div class="form-actions"><a class="button button-secondary" href="perfil-fornecedor.php">Revisar perfil</a><a class="button button-ghost" href="localizacao-fornecedor.php">Configurar localização</a></div></div>
    <?php else: ?>
        <section class="opportunity-grid">
            <?php foreach ($opportunities as $opportunity): ?>
                <article class="opportunity-card <?= $opportunity['urgency'] === 'agora' ? 'opportunity-now' : '' ?>">
                    <div class="request-meta"><span class="status-tag <?= $opportunity['urgency'] === 'agora' ? 'status-danger' : ($opportunity['urgency'] === 'urgente' ? 'status-pending' : 'status-muted') ?>"><?= e($urgencyLabels[$opportunity['urgency']] ?? $opportunity['urgency']) ?></span><span><?= e((string) $opportunity['category_name']) ?></span><?php if ($opportunity['distance_km'] !== null): ?><span class="status-tag status-ok">≈ <?= e(number_format((float) $opportunity['distance_km'], 1, ',', '.')) ?> km</span><?php else: ?><span class="status-tag status-muted">Match por região</span><?php endif; ?></div>
                    <h2><?= e((string) $opportunity['title']) ?></h2>
                    <p><?= e(mb_strimwidth((string) $opportunity['description'], 0, 180, '…')) ?></p>
                    <div class="opportunity-info"><span>📍 <?= e((string) $opportunity['city']) ?> • <?= e((string) $opportunity['state']) ?></span><span>🏢 <?= e((string) $opportunity['contractor_name']) ?></span><?php if ($opportunity['desired_date']): ?><span>📅 <?= e(date('d/m/Y', strtotime((string) $opportunity['desired_date']))) ?></span><?php endif; ?></div>
                    <?php if ($opportunity['proposal_status']): ?><div class="proposal-sent"><strong>Proposta enviada</strong><span>R$ <?= e(number_format((float) $opportunity['proposal_amount'], 2, ',', '.')) ?></span></div><?php endif; ?>
                    <a class="button <?= $opportunity['proposal_status'] ? 'button-secondary' : 'button-primary' ?> button-full" href="solicitacao.php?id=<?= (int) $opportunity['id'] ?>"><?= $opportunity['proposal_status'] ? 'Ver / editar proposta' : 'Ver oportunidade e enviar proposta' ?></a>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
</body></html>
