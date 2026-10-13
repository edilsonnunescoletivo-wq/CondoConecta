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
$stmt = $pdo->prepare("SELECT p.id, p.amount, p.estimated_days, p.warranty_days, p.status, p.updated_at,
                             sr.id AS request_id, sr.title, sr.city, sr.state, sr.urgency, sr.status AS request_status,
                             c.name AS category_name, cp.display_name AS contractor_name,
                             h.id AS hiring_id, h.status AS hiring_status
                      FROM proposals p
                      INNER JOIN service_requests sr ON sr.id = p.service_request_id
                      INNER JOIN categories c ON c.id = sr.category_id
                      INNER JOIN contractor_profiles cp ON cp.user_id = sr.contractor_user_id
                      LEFT JOIN hirings h ON h.proposal_id = p.id
                      WHERE p.supplier_user_id = :user_id
                      ORDER BY p.updated_at DESC");
$stmt->execute(['user_id' => $user['id']]);
$proposals = $stmt->fetchAll();

$statusLabels = ['enviada' => 'Em análise', 'aceita' => 'Aceita', 'recusada' => 'Não selecionada', 'retirada' => 'Retirada'];
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Minhas propostas — CondoConecta</title><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="dashboard-page">
<header class="topbar"><div class="container nav"><a class="brand" href="painel.php">Condo<span>Conecta</span></a><nav><a href="oportunidades.php">Oportunidades</a><a href="minhas-contratacoes.php">Contratações</a><a class="button button-ghost" href="painel.php">Painel</a></nav></div></header>
<main class="container dashboard-shell">
    <div class="page-heading"><div><span class="badge">FORNECEDOR</span><h1>Minhas propostas</h1><p>Acompanhe propostas em análise, selecionadas e não selecionadas.</p></div></div>
    <?php if (!$proposals): ?><div class="empty-state"><h2>Nenhuma proposta enviada</h2><p>Consulte as oportunidades compatíveis com seu perfil.</p><a class="button button-primary" href="oportunidades.php">Ver oportunidades</a></div><?php else: ?>
    <section class="request-list"><?php foreach ($proposals as $proposal): ?><article class="request-row"><div class="request-row-main"><div class="request-meta"><span class="status-tag <?= $proposal['status'] === 'aceita' ? 'status-ok' : ($proposal['status'] === 'recusada' ? 'status-muted' : 'status-pending') ?>"><?= e($statusLabels[$proposal['status']] ?? $proposal['status']) ?></span><span><?= e((string) $proposal['category_name']) ?></span><span>📍 <?= e((string) $proposal['city']) ?> • <?= e((string) $proposal['state']) ?></span></div><h2><?= e((string) $proposal['title']) ?></h2><div class="request-meta"><span><?= e((string) $proposal['contractor_name']) ?></span><strong>R$ <?= e(number_format((float) $proposal['amount'], 2, ',', '.')) ?></strong><?php if ($proposal['estimated_days']): ?><span><?= (int) $proposal['estimated_days'] ?> dia(s)</span><?php endif; ?></div></div><div class="row-actions"><?php if ($proposal['hiring_id']): ?><a class="button button-primary" href="contratacao.php?id=<?= (int) $proposal['hiring_id'] ?>">Contratação</a><?php else: ?><a class="button button-secondary" href="solicitacao.php?id=<?= (int) $proposal['request_id'] ?>">Abrir</a><?php endif; ?></div></article><?php endforeach; ?></section>
    <?php endif; ?>
</main></body></html>
