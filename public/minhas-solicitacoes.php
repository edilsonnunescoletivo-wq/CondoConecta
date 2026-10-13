<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;

$user = Auth::requireAuth();
if (($user['role'] ?? '') !== 'contratante') {
    redirect('painel.php');
}

$pdo = Database::connection($config);
$stmt = $pdo->prepare("SELECT sr.id, sr.title, sr.urgency, sr.city, sr.state, sr.status, sr.desired_date, sr.created_at,
                             c.name AS category_name,
                             (SELECT COUNT(*) FROM proposals p WHERE p.service_request_id = sr.id AND p.status <> 'retirada') AS proposal_count
                      FROM service_requests sr
                      INNER JOIN categories c ON c.id = sr.category_id
                      WHERE sr.contractor_user_id = :user_id
                      ORDER BY sr.created_at DESC");
$stmt->execute(['user_id' => $user['id']]);
$requests = $stmt->fetchAll();

$urgencyLabels = ['normal' => 'Normal', 'urgente' => 'Urgente', 'agora' => 'Preciso Agora'];
$statusLabels = ['rascunho' => 'Rascunho', 'aberta' => 'Aberta', 'em_cotacao' => 'Em cotação', 'contratada' => 'Contratada', 'cancelada' => 'Cancelada', 'concluida' => 'Concluída'];
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Minhas solicitações — <?= e((string) ($config['name'] ?? 'CondoConecta')) ?></title><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="dashboard-page">
<header class="topbar"><div class="container nav"><a class="brand" href="painel.php">Condo<span>Conecta</span></a><nav><a class="button button-primary" href="nova-solicitacao.php">Nova solicitação</a><a class="button button-ghost" href="painel.php">Painel</a></nav></div></header>
<main class="container dashboard-shell">
    <?php if (isset($_GET['criada'])): ?><div class="alert alert-success"><strong>Solicitação publicada.</strong> <?= isset($_GET['match']) ? 'Encontramos ' . (int) $_GET['match'] . ' fornecedor(es) compatível(is) neste momento.' : '' ?></div><?php endif; ?>
    <div class="page-heading"><div><span class="badge">COTAÇÕES</span><h1>Minhas solicitações</h1><p>Acompanhe o andamento e as propostas recebidas.</p></div></div>
    <?php if (!$requests): ?>
        <div class="empty-state"><h2>Nenhuma solicitação ainda</h2><p>Publique sua primeira necessidade e encontre fornecedores da região.</p><a class="button button-primary" href="nova-solicitacao.php">Criar solicitação</a></div>
    <?php else: ?>
        <section class="request-list">
            <?php foreach ($requests as $request): ?>
                <article class="request-row <?= $request['urgency'] === 'agora' ? 'request-now' : '' ?>">
                    <div class="request-row-main">
                        <div class="request-meta"><span class="status-tag <?= $request['urgency'] === 'agora' ? 'status-danger' : ($request['urgency'] === 'urgente' ? 'status-pending' : 'status-muted') ?>"><?= e($urgencyLabels[$request['urgency']] ?? $request['urgency']) ?></span><span><?= e((string) $request['category_name']) ?></span><span>📍 <?= e((string) $request['city']) ?> • <?= e((string) $request['state']) ?></span></div>
                        <h2><?= e((string) $request['title']) ?></h2>
                        <div class="request-meta"><span><?= e($statusLabels[$request['status']] ?? $request['status']) ?></span><span><?= (int) $request['proposal_count'] ?> proposta(s)</span><?php if ($request['desired_date']): ?><span>Desejado: <?= e(date('d/m/Y', strtotime((string) $request['desired_date']))) ?></span><?php endif; ?></div>
                    </div>
                    <a class="button button-secondary" href="solicitacao.php?id=<?= (int) $request['id'] ?>">Abrir</a>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
</body></html>
