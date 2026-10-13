<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;

$user = Auth::requireAuth();
if (!in_array(($user['role'] ?? ''), ['contratante', 'fornecedor'], true)) {
    redirect('painel.php');
}

$pdo = Database::connection($config);
$isSupplier = $user['role'] === 'fornecedor';
$sql = "SELECT h.id, h.status, h.hired_at, h.started_at, h.completed_at,
               sr.title, sr.city, sr.state, c.name AS category_name,
               p.amount, sp.trade_name, cp.display_name AS contractor_name,
               r.id AS review_id
        FROM hirings h
        INNER JOIN service_requests sr ON sr.id = h.service_request_id
        INNER JOIN categories c ON c.id = sr.category_id
        INNER JOIN proposals p ON p.id = h.proposal_id
        INNER JOIN supplier_profiles sp ON sp.user_id = h.supplier_user_id
        INNER JOIN contractor_profiles cp ON cp.user_id = h.contractor_user_id
        LEFT JOIN reviews r ON r.hiring_id = h.id
        WHERE " . ($isSupplier ? 'h.supplier_user_id' : 'h.contractor_user_id') . " = :user_id
        ORDER BY h.hired_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $user['id']]);
$hirings = $stmt->fetchAll();

$statusLabels = ['contratada' => 'Contratada', 'em_execucao' => 'Em execução', 'concluida' => 'Concluída', 'cancelada' => 'Cancelada'];
$statusClass = ['contratada' => 'status-pending', 'em_execucao' => 'status-info', 'concluida' => 'status-ok', 'cancelada' => 'status-danger'];
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Minhas contratações — CondoConecta</title><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="dashboard-page">
<header class="topbar"><div class="container nav"><a class="brand" href="painel.php">Condo<span>Conecta</span></a><nav><?php if ($isSupplier): ?><a href="minhas-propostas.php">Propostas</a><?php else: ?><a href="minhas-solicitacoes.php">Solicitações</a><?php endif; ?><a class="button button-ghost" href="painel.php">Painel</a></nav></div></header>
<main class="container dashboard-shell">
    <div class="page-heading"><div><span class="badge">CONTRATAÇÕES</span><h1>Minhas contratações</h1><p>Acompanhe serviços contratados, em execução, concluídos e avaliações.</p></div></div>
    <?php if (!$hirings): ?><div class="empty-state"><h2>Nenhuma contratação ainda</h2><p><?= $isSupplier ? 'Quando uma proposta sua for aceita, a contratação aparecerá aqui.' : 'Quando você aceitar uma proposta, a contratação aparecerá aqui.' ?></p></div><?php else: ?>
    <section class="request-list"><?php foreach ($hirings as $hiring): ?><article class="request-row"><div class="request-row-main"><div class="request-meta"><span class="status-tag <?= e($statusClass[$hiring['status']] ?? 'status-muted') ?>"><?= e($statusLabels[$hiring['status']] ?? $hiring['status']) ?></span><span><?= e((string) $hiring['category_name']) ?></span><span>📍 <?= e((string) $hiring['city']) ?> • <?= e((string) $hiring['state']) ?></span></div><h2><?= e((string) $hiring['title']) ?></h2><div class="request-meta"><span><?= $isSupplier ? e((string) $hiring['contractor_name']) : e((string) $hiring['trade_name']) ?></span><strong>R$ <?= e(number_format((float) $hiring['amount'], 2, ',', '.')) ?></strong><span>Contratada em <?= e(date('d/m/Y', strtotime((string) $hiring['hired_at']))) ?></span><?php if ($hiring['status'] === 'concluida' && empty($hiring['review_id']) && !$isSupplier): ?><span class="status-tag status-pending">Avaliação pendente</span><?php endif; ?></div></div><a class="button button-secondary" href="contratacao.php?id=<?= (int) $hiring['id'] ?>">Abrir</a></article><?php endforeach; ?></section>
    <?php endif; ?>
</main></body></html>
