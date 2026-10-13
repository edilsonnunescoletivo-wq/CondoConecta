<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use Throwable;

$user = Auth::requireAuth();
$pdo = Database::connection($config);
$hiringId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($hiringId <= 0) {
    http_response_code(404);
    exit('Contratação não encontrada.');
}

$loadHiring = static function () use ($pdo, $hiringId): array|false {
    $stmt = $pdo->prepare("SELECT h.*, sr.title, sr.description, sr.city, sr.state, sr.urgency, sr.status AS request_status,
                                 c.name AS category_name,
                                 p.amount, p.estimated_days, p.warranty_days, p.details AS proposal_details,
                                 sp.id AS supplier_id, sp.trade_name, su.name AS supplier_contact_name, su.email AS supplier_email, su.phone AS supplier_phone,
                                 cp.display_name AS contractor_name, cu.name AS contractor_contact_name, cu.email AS contractor_email, cu.phone AS contractor_phone,
                                 r.id AS review_id, r.quality_rating, r.deadline_rating, r.service_rating, r.value_rating, r.comment AS review_comment
                          FROM hirings h
                          INNER JOIN service_requests sr ON sr.id = h.service_request_id
                          INNER JOIN categories c ON c.id = sr.category_id
                          INNER JOIN proposals p ON p.id = h.proposal_id
                          INNER JOIN supplier_profiles sp ON sp.user_id = h.supplier_user_id
                          INNER JOIN users su ON su.id = h.supplier_user_id
                          INNER JOIN contractor_profiles cp ON cp.user_id = h.contractor_user_id
                          INNER JOIN users cu ON cu.id = h.contractor_user_id
                          LEFT JOIN reviews r ON r.hiring_id = h.id
                          WHERE h.id = :id LIMIT 1");
    $stmt->execute(['id' => $hiringId]);
    return $stmt->fetch();
};

$hiring = $loadHiring();
if (!$hiring) {
    http_response_code(404);
    exit('Contratação não encontrada.');
}

$isContractor = ($user['role'] ?? '') === 'contratante' && (int) $hiring['contractor_user_id'] === (int) $user['id'];
$isSupplier = ($user['role'] ?? '') === 'fornecedor' && (int) $hiring['supplier_user_id'] === (int) $user['id'];
$isAdmin = ($user['role'] ?? '') === 'admin';
if (!$isContractor && !$isSupplier && !$isAdmin) {
    http_response_code(403);
    exit('Você não tem acesso a esta contratação.');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    }

    $action = (string) ($_POST['action'] ?? '');

    if (!$errors && $action === 'start') {
        if (!$isSupplier) {
            $errors[] = 'Somente o fornecedor contratado pode iniciar a execução.';
        } elseif ($hiring['status'] !== 'contratada') {
            $errors[] = 'Esta contratação não pode ser iniciada neste estado.';
        } else {
            $stmt = $pdo->prepare("UPDATE hirings SET status = 'em_execucao', started_at = COALESCE(started_at, CURRENT_TIMESTAMP) WHERE id = :id AND status = 'contratada'");
            $stmt->execute(['id' => $hiringId]);
            redirect('contratacao.php?id=' . $hiringId . '&status=iniciada');
        }
    }

    if (!$errors && $action === 'complete') {
        if (!$isContractor) {
            $errors[] = 'Somente o contratante pode confirmar a conclusão do serviço.';
        } elseif (!in_array($hiring['status'], ['contratada', 'em_execucao'], true)) {
            $errors[] = 'Esta contratação não pode ser concluída neste estado.';
        } else {
            try {
                $pdo->beginTransaction();
                $lock = $pdo->prepare('SELECT status, service_request_id FROM hirings WHERE id = :id FOR UPDATE');
                $lock->execute(['id' => $hiringId]);
                $locked = $lock->fetch();
                if (!$locked || !in_array($locked['status'], ['contratada', 'em_execucao'], true)) {
                    throw new RuntimeException('A contratação mudou de estado. Atualize a página.');
                }
                $pdo->prepare("UPDATE hirings SET status = 'concluida', completed_at = CURRENT_TIMESTAMP WHERE id = :id")->execute(['id' => $hiringId]);
                $pdo->prepare("UPDATE service_requests SET status = 'concluida' WHERE id = :request_id")->execute(['request_id' => $locked['service_request_id']]);
                $pdo->commit();
                redirect('contratacao.php?id=' . $hiringId . '&status=concluida');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'Não foi possível concluir a contratação.';
            }
        }
    }

    if (!$errors && $action === 'review') {
        if (!$isContractor) {
            $errors[] = 'Somente o contratante pode avaliar o serviço.';
        } elseif ($hiring['status'] !== 'concluida') {
            $errors[] = 'A avaliação só é liberada após a conclusão do serviço.';
        } elseif (!empty($hiring['review_id'])) {
            $errors[] = 'Esta contratação já foi avaliada.';
        } else {
            $ratings = [
                'quality_rating' => (int) ($_POST['quality_rating'] ?? 0),
                'deadline_rating' => (int) ($_POST['deadline_rating'] ?? 0),
                'service_rating' => (int) ($_POST['service_rating'] ?? 0),
                'value_rating' => (int) ($_POST['value_rating'] ?? 0),
            ];
            foreach ($ratings as $rating) {
                if ($rating < 1 || $rating > 5) {
                    $errors[] = 'Todas as notas devem estar entre 1 e 5.';
                    break;
                }
            }
            $comment = trim((string) ($_POST['comment'] ?? ''));
            if (mb_strlen($comment) > 2000) {
                $errors[] = 'O comentário deve ter no máximo 2.000 caracteres.';
            }
            if (!$errors) {
                try {
                    $stmt = $pdo->prepare('INSERT INTO reviews (hiring_id, contractor_user_id, supplier_user_id, quality_rating, deadline_rating, service_rating, value_rating, comment) VALUES (:hiring_id, :contractor_user_id, :supplier_user_id, :quality_rating, :deadline_rating, :service_rating, :value_rating, :comment)');
                    $stmt->execute([
                        'hiring_id' => $hiringId,
                        'contractor_user_id' => $hiring['contractor_user_id'],
                        'supplier_user_id' => $hiring['supplier_user_id'],
                        'quality_rating' => $ratings['quality_rating'],
                        'deadline_rating' => $ratings['deadline_rating'],
                        'service_rating' => $ratings['service_rating'],
                        'value_rating' => $ratings['value_rating'],
                        'comment' => $comment !== '' ? $comment : null,
                    ]);
                    redirect('contratacao.php?id=' . $hiringId . '&avaliacao=salva');
                } catch (Throwable $exception) {
                    $errors[] = 'Não foi possível salvar a avaliação.';
                }
            }
        }
    }

    $hiring = $loadHiring();
}

$statusLabels = ['contratada' => 'Contratada', 'em_execucao' => 'Em execução', 'concluida' => 'Concluída', 'cancelada' => 'Cancelada'];
$statusClass = ['contratada' => 'status-pending', 'em_execucao' => 'status-info', 'concluida' => 'status-ok', 'cancelada' => 'status-danger'];
$averageReview = !empty($hiring['review_id']) ? ((int) $hiring['quality_rating'] + (int) $hiring['deadline_rating'] + (int) $hiring['service_rating'] + (int) $hiring['value_rating']) / 4 : null;
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Contratação — CondoConecta</title><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="dashboard-page">
<header class="topbar"><div class="container nav"><a class="brand" href="painel.php">Condo<span>Conecta</span></a><nav><a href="minhas-contratacoes.php">Contratações</a><a class="button button-ghost" href="painel.php">Painel</a></nav></div></header>
<main class="container request-detail-shell">
    <?php if (isset($_GET['contratada'])): ?><div class="alert alert-success">Proposta aceita e contratação criada com sucesso.</div><?php endif; ?>
    <?php if (($_GET['status'] ?? '') === 'iniciada'): ?><div class="alert alert-success">Execução marcada como iniciada.</div><?php endif; ?>
    <?php if (($_GET['status'] ?? '') === 'concluida'): ?><div class="alert alert-success">Serviço concluído. A avaliação já pode ser registrada.</div><?php endif; ?>
    <?php if (isset($_GET['avaliacao'])): ?><div class="alert alert-success">Avaliação registrada com sucesso.</div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <section class="request-detail-hero">
        <div><div class="request-meta"><span class="status-tag <?= e($statusClass[$hiring['status']] ?? 'status-muted') ?>"><?= e($statusLabels[$hiring['status']] ?? $hiring['status']) ?></span><span><?= e((string) $hiring['category_name']) ?></span><span>📍 <?= e((string) $hiring['city']) ?> • <?= e((string) $hiring['state']) ?></span></div><h1><?= e((string) $hiring['title']) ?></h1><p><?= nl2br(e((string) $hiring['description'])) ?></p></div>
        <div class="match-summary"><strong>R$ <?= e(number_format((float) $hiring['amount'], 2, ',', '.')) ?></strong><span>valor contratado</span></div>
    </section>

    <div class="request-detail-grid">
        <section class="card-panel">
            <h2>Resumo da contratação</h2>
            <div class="detail-list"><div><span>Fornecedor</span><strong><?= e((string) $hiring['trade_name']) ?></strong></div><div><span>Contratante</span><strong><?= e((string) $hiring['contractor_name']) ?></strong></div><?php if ($hiring['estimated_days']): ?><div><span>Prazo estimado</span><strong><?= (int) $hiring['estimated_days'] ?> dia(s)</strong></div><?php endif; ?><?php if ($hiring['warranty_days'] !== null): ?><div><span>Garantia</span><strong><?= (int) $hiring['warranty_days'] ?> dia(s)</strong></div><?php endif; ?><div><span>Contratada em</span><strong><?= e(date('d/m/Y H:i', strtotime((string) $hiring['hired_at']))) ?></strong></div><?php if ($hiring['started_at']): ?><div><span>Iniciada em</span><strong><?= e(date('d/m/Y H:i', strtotime((string) $hiring['started_at']))) ?></strong></div><?php endif; ?><?php if ($hiring['completed_at']): ?><div><span>Concluída em</span><strong><?= e(date('d/m/Y H:i', strtotime((string) $hiring['completed_at']))) ?></strong></div><?php endif; ?></div>
            <?php if ($hiring['proposal_details']): ?><h3>Escopo da proposta</h3><p><?= nl2br(e((string) $hiring['proposal_details'])) ?></p><?php endif; ?>

            <h3>Contato liberado após contratação</h3>
            <div class="contact-grid">
                <div><span>Fornecedor</span><strong><?= e((string) $hiring['supplier_contact_name']) ?></strong><a href="mailto:<?= e((string) $hiring['supplier_email']) ?>"><?= e((string) $hiring['supplier_email']) ?></a><?php if ($hiring['supplier_phone']): ?><a href="tel:<?= e((string) $hiring['supplier_phone']) ?>"><?= e((string) $hiring['supplier_phone']) ?></a><?php endif; ?></div>
                <div><span>Contratante</span><strong><?= e((string) $hiring['contractor_contact_name']) ?></strong><a href="mailto:<?= e((string) $hiring['contractor_email']) ?>"><?= e((string) $hiring['contractor_email']) ?></a><?php if ($hiring['contractor_phone']): ?><a href="tel:<?= e((string) $hiring['contractor_phone']) ?>"><?= e((string) $hiring['contractor_phone']) ?></a><?php endif; ?></div>
            </div>
        </section>

        <aside class="card-panel proposal-panel">
            <span class="badge">ANDAMENTO</span><h2><?= e($statusLabels[$hiring['status']] ?? $hiring['status']) ?></h2>
            <?php if ($isSupplier && $hiring['status'] === 'contratada'): ?><p>Quando o atendimento começar, registre o início da execução.</p><form method="post"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= $hiringId ?>"><input type="hidden" name="action" value="start"><button class="button button-primary button-full" type="submit">Iniciar serviço</button></form><?php endif; ?>
            <?php if ($isContractor && in_array($hiring['status'], ['contratada', 'em_execucao'], true)): ?><p>Confirme somente quando o serviço tiver sido efetivamente concluído.</p><form method="post" onsubmit="return confirm('Confirmar a conclusão deste serviço?');"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= $hiringId ?>"><input type="hidden" name="action" value="complete"><button class="button button-primary button-full" type="submit">Confirmar conclusão</button></form><?php endif; ?>

            <?php if ($hiring['status'] === 'concluida' && empty($hiring['review_id']) && $isContractor): ?>
                <hr><span class="badge">AVALIAÇÃO</span><h2>Avalie o fornecedor</h2><form method="post" class="stack-form"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= $hiringId ?>"><input type="hidden" name="action" value="review"><div class="mini-grid"><label>Qualidade<select name="quality_rating" required><?php for ($i=5;$i>=1;$i--): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></label><label>Prazo<select name="deadline_rating" required><?php for ($i=5;$i>=1;$i--): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></label><label>Atendimento<select name="service_rating" required><?php for ($i=5;$i>=1;$i--): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></label><label>Custo-benefício<select name="value_rating" required><?php for ($i=5;$i>=1;$i--): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></label></div><label>Comentário <span class="optional">opcional</span><textarea name="comment" rows="4" maxlength="2000"></textarea></label><button class="button button-primary" type="submit">Publicar avaliação</button></form>
            <?php elseif (!empty($hiring['review_id'])): ?><hr><span class="badge">AVALIAÇÃO</span><h2>⭐ <?= e(number_format((float) $averageReview, 1, ',', '.')) ?>/5</h2><div class="review-breakdown"><span>Qualidade: <?= (int) $hiring['quality_rating'] ?>/5</span><span>Prazo: <?= (int) $hiring['deadline_rating'] ?>/5</span><span>Atendimento: <?= (int) $hiring['service_rating'] ?>/5</span><span>Custo-benefício: <?= (int) $hiring['value_rating'] ?>/5</span></div><?php if ($hiring['review_comment']): ?><p><?= nl2br(e((string) $hiring['review_comment'])) ?></p><?php endif; ?><?php endif; ?>
        </aside>
    </div>
</main></body></html>
