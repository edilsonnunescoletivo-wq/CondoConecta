<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Notifications;
use Throwable;

$user = Auth::requireAuth();
$pdo = Database::connection($config);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        $error = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } else {
        try {
            Notifications::markAllRead($pdo, (int) $user['id']);
            Csrf::rotate();
            redirect('notificacoes.php?lidas=1');
        } catch (Throwable $exception) {
            $error = 'Não foi possível atualizar as notificações.';
        }
    }
}

try {
    $notifications = Notifications::forUser($pdo, (int) $user['id']);
    $unread = Notifications::unreadCount($pdo, (int) $user['id']);
} catch (Throwable $exception) {
    $notifications = [];
    $unread = 0;
    $error = 'O módulo de notificações ainda não está disponível. Aplique a migração 004 no banco.';
}
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Notificações — CondoConecta</title><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="dashboard-page">
<header class="topbar"><div class="container nav"><a class="brand" href="painel.php">Condo<span>Conecta</span></a><nav><a href="painel.php">Painel</a><?php if (($user['role'] ?? '') === 'fornecedor'): ?><a href="oportunidades.php">Oportunidades</a><?php endif; ?></nav></div></header>
<main class="container dashboard-shell">
    <div class="page-heading"><span class="badge">NOTIFICAÇÕES</span><h1>Central de avisos</h1><p>Chamados urgentes e eventos importantes aparecem aqui.</p></div>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if (isset($_GET['lidas'])): ?><div class="alert alert-success">Notificações marcadas como lidas.</div><?php endif; ?>

    <div class="form-actions" style="margin-bottom:18px">
        <strong><?= $unread ?> não lida(s)</strong>
        <?php if ($unread > 0): ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="button button-ghost" type="submit">Marcar todas como lidas</button></form><?php endif; ?>
    </div>

    <?php if (!$notifications): ?>
        <div class="empty-state"><h2>Nenhuma notificação</h2><p>Quando houver uma oportunidade urgente compatível, ela aparecerá aqui.</p></div>
    <?php else: ?>
        <section class="request-list">
            <?php foreach ($notifications as $notification): ?>
                <article class="request-row <?= $notification['read_at'] ? '' : 'request-now' ?>">
                    <div class="request-row-main">
                        <div class="request-meta"><span class="status-tag <?= $notification['read_at'] ? 'status-muted' : 'status-danger' ?>"><?= $notification['read_at'] ? 'Lida' : 'Nova' ?></span><span><?= e(date('d/m/Y H:i', strtotime((string) $notification['created_at']))) ?></span></div>
                        <h2><?= e((string) $notification['title']) ?></h2>
                        <p class="muted"><?= e((string) $notification['message']) ?></p>
                    </div>
                    <?php if ($notification['link']): ?><a class="button button-secondary" href="abrir-notificacao.php?id=<?= (int) $notification['id'] ?>">Abrir</a><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
</body></html>
