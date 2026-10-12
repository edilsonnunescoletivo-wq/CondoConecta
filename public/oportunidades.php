<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;

$user = Auth::requireAuth();
if (($user['role'] ?? '') !== 'fornecedor') {
    redirect('painel.php');
}

$pdo = Database::connection($config);
$sql = "SELECT DISTINCT sr.id, sr.title, sr.description, sr.urgency, sr.city, sr.state, sr.desired_date, sr.created_at,
                       c.name AS category_name, cp.display_name AS contractor_name,
                       p.status AS proposal_status, p.amount AS proposal_amount
        FROM service_requests sr
        INNER JOIN categories c ON c.id = sr.category_id
        INNER JOIN contractor_profiles cp ON cp.user_id = sr.contractor_user_id
        INNER JOIN supplier_profiles sp ON sp.user_id = :supplier_user_id
        INNER JOIN supplier_categories sc ON sc.supplier_id = sp.id AND sc.category_id = sr.category_id
        LEFT JOIN proposals p ON p.service_request_id = sr.id AND p.supplier_user_id = :proposal_user_id
        WHERE sr.status IN ('aberta','em_cotacao')
          AND (
            (sp.state = sr.state AND sp.city = sr.city)
            OR EXISTS (
                SELECT 1 FROM supplier_regions sreg
                WHERE sreg.supplier_id = sp.id AND sreg.state = sr.state AND sreg.city = sr.city
            )
          )
          AND (sr.urgency <> 'agora' OR sp.emergency_service = 1)
        ORDER BY (sr.urgency = 'agora') DESC, (sr.urgency = 'urgente') DESC, sr.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['supplier_user_id' => $user['id'], 'proposal_user_id' => $user['id']]);
$opportunities = $stmt->fetchAll();

$urgencyLabels = ['normal' => 'Normal', 'urgente' => 'Urgente', 'agora' => 'Preciso Agora'];
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Oportunidades — <?= e((string) ($config['name'] ?? 'CondoConecta')) ?></title><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="dashboard-page">
<header class="topbar"><div class="container nav"><a class="brand" href="painel.php">Condo<span>Conecta</span></a><nav><a href="minhas-propostas.php">Minhas propostas</a><a class="button button-ghost" href="painel.php">Painel</a></nav></div></header>
<main class="container dashboard-shell">
    <div class="page-heading"><div><span class="badge">FORNECEDOR</span><h1>Oportunidades compatíveis</h1><p>Somente solicitações das suas categorias e regiões aparecem aqui. “Preciso Agora” exige atendimento emergencial ativado.</p></div></div>
    <?php if (!$opportunities): ?>
        <div class="empty-state"><h2>Nenhuma oportunidade compatível agora</h2><p>Confira se você selecionou categorias e regiões no perfil de serviços.</p><a class="button button-secondary" href="perfil-fornecedor.php">Revisar perfil</a></div>
    <?php else: ?>
        <section class="opportunity-grid">
            <?php foreach ($opportunities as $opportunity): ?>
                <article class="opportunity-card <?= $opportunity['urgency'] === 'agora' ? 'opportunity-now' : '' ?>">
                    <div class="request-meta"><span class="status-tag <?= $opportunity['urgency'] === 'agora' ? 'status-danger' : ($opportunity['urgency'] === 'urgente' ? 'status-pending' : 'status-muted') ?>"><?= e($urgencyLabels[$opportunity['urgency']] ?? $opportunity['urgency']) ?></span><span><?= e((string) $opportunity['category_name']) ?></span></div>
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
