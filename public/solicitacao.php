<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Marketplace;
use App\Core\Money;
use Throwable;

$user = Auth::requireAuth();
$pdo = Database::connection($config);
$requestId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($requestId <= 0) {
    http_response_code(404);
    exit('Solicitação não encontrada.');
}

$loadRequest = static function () use ($pdo, $requestId): array|false {
    $stmt = $pdo->prepare("SELECT sr.*, c.name AS category_name, cp.display_name AS contractor_name
                           FROM service_requests sr
                           INNER JOIN categories c ON c.id = sr.category_id
                           INNER JOIN contractor_profiles cp ON cp.user_id = sr.contractor_user_id
                           WHERE sr.id = :id LIMIT 1");
    $stmt->execute(['id' => $requestId]);
    return $stmt->fetch();
};

$request = $loadRequest();
if (!$request) {
    http_response_code(404);
    exit('Solicitação não encontrada.');
}

$isContractor = ($user['role'] ?? '') === 'contratante';
$isSupplier = ($user['role'] ?? '') === 'fornecedor';
$isOwner = $isContractor && (int) $request['contractor_user_id'] === (int) $user['id'];

$myProposal = null;
if ($isSupplier) {
    $proposalStmt = $pdo->prepare('SELECT * FROM proposals WHERE service_request_id = :request_id AND supplier_user_id = :supplier_user_id LIMIT 1');
    $proposalStmt->execute(['request_id' => $requestId, 'supplier_user_id' => $user['id']]);
    $myProposal = $proposalStmt->fetch() ?: null;
}

$supplierAllowed = $isSupplier && (Marketplace::supplierCanAccessRequest($pdo, (int) $user['id'], $requestId) || $myProposal !== null);
if (!$isOwner && !$supplierAllowed && ($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Você não tem acesso a esta solicitação.');
}

$errors = [];
$action = (string) ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner && $action === 'accept_proposal') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    }

    $proposalId = (int) ($_POST['proposal_id'] ?? 0);
    if ($proposalId <= 0) {
        $errors[] = 'Proposta inválida.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $requestLock = $pdo->prepare('SELECT id, contractor_user_id, status FROM service_requests WHERE id = :id FOR UPDATE');
            $requestLock->execute(['id' => $requestId]);
            $lockedRequest = $requestLock->fetch();
            if (!$lockedRequest || (int) $lockedRequest['contractor_user_id'] !== (int) $user['id']) {
                throw new RuntimeException('Solicitação não encontrada para este contratante.');
            }
            if (!in_array($lockedRequest['status'], ['aberta', 'em_cotacao'], true)) {
                throw new RuntimeException('Esta solicitação não aceita mais escolha de proposta.');
            }

            $proposalLock = $pdo->prepare("SELECT id, supplier_user_id, status FROM proposals WHERE id = :proposal_id AND service_request_id = :request_id FOR UPDATE");
            $proposalLock->execute(['proposal_id' => $proposalId, 'request_id' => $requestId]);
            $chosen = $proposalLock->fetch();
            if (!$chosen || $chosen['status'] !== 'enviada') {
                throw new RuntimeException('A proposta selecionada não está disponível para aceite.');
            }

            $insertHiring = $pdo->prepare("INSERT INTO hirings (service_request_id, proposal_id, contractor_user_id, supplier_user_id, status) VALUES (:request_id, :proposal_id, :contractor_user_id, :supplier_user_id, 'contratada')");
            $insertHiring->execute([
                'request_id' => $requestId,
                'proposal_id' => $proposalId,
                'contractor_user_id' => $user['id'],
                'supplier_user_id' => $chosen['supplier_user_id'],
            ]);
            $hiringId = (int) $pdo->lastInsertId();

            $pdo->prepare("UPDATE proposals SET status = 'aceita', updated_at = CURRENT_TIMESTAMP WHERE id = :id")->execute(['id' => $proposalId]);
            $pdo->prepare("UPDATE proposals SET status = 'recusada', updated_at = CURRENT_TIMESTAMP WHERE service_request_id = :request_id AND id <> :proposal_id AND status = 'enviada'")->execute(['request_id' => $requestId, 'proposal_id' => $proposalId]);
            $pdo->prepare("UPDATE service_requests SET status = 'contratada', updated_at = CURRENT_TIMESTAMP WHERE id = :id")->execute(['id' => $requestId]);
            $pdo->commit();
            redirect('contratacao.php?id=' . $hiringId . '&contratada=1');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'Não foi possível concluir a contratação.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isSupplier && $supplierAllowed && $action !== 'accept_proposal') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    }

    if (!in_array($request['status'], ['aberta', 'em_cotacao'], true)) {
        $errors[] = 'Esta solicitação não está mais recebendo propostas.';
    }

    $amount = Money::parse((string) ($_POST['amount'] ?? ''));
    $estimatedDays = trim((string) ($_POST['estimated_days'] ?? ''));
    $warrantyDays = trim((string) ($_POST['warranty_days'] ?? ''));
    $details = trim((string) ($_POST['details'] ?? ''));

    if ($amount === null || $amount <= 0 || $amount > 9999999999) {
        $errors[] = 'Informe um valor válido para a proposta.';
    }
    if ($estimatedDays !== '' && ((int) $estimatedDays < 1 || (int) $estimatedDays > 3650)) {
        $errors[] = 'Informe um prazo estimado válido.';
    }
    if ($warrantyDays !== '' && ((int) $warrantyDays < 0 || (int) $warrantyDays > 3650)) {
        $errors[] = 'Informe uma garantia válida.';
    }
    if (mb_strlen($details) > 3000) {
        $errors[] = 'Os detalhes da proposta devem ter no máximo 3.000 caracteres.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $proposalSave = $pdo->prepare("INSERT INTO proposals
                (service_request_id, supplier_user_id, amount, estimated_days, warranty_days, details, status)
                VALUES (:request_id, :supplier_user_id, :amount, :estimated_days, :warranty_days, :details, 'enviada')
                ON DUPLICATE KEY UPDATE amount = VALUES(amount), estimated_days = VALUES(estimated_days), warranty_days = VALUES(warranty_days), details = VALUES(details), status = 'enviada', updated_at = CURRENT_TIMESTAMP");
            $proposalSave->execute([
                'request_id' => $requestId,
                'supplier_user_id' => $user['id'],
                'amount' => $amount,
                'estimated_days' => $estimatedDays !== '' ? (int) $estimatedDays : null,
                'warranty_days' => $warrantyDays !== '' ? (int) $warrantyDays : null,
                'details' => $details !== '' ? $details : null,
            ]);
            $pdo->prepare("UPDATE service_requests SET status = 'em_cotacao' WHERE id = :id AND status = 'aberta'")->execute(['id' => $requestId]);
            $pdo->commit();
            redirect('solicitacao.php?id=' . $requestId . '&proposta=salva');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Não foi possível salvar a proposta. Tente novamente.';
        }
    }
}

$request = $loadRequest();
if ($isSupplier) {
    $proposalStmt = $pdo->prepare('SELECT * FROM proposals WHERE service_request_id = :request_id AND supplier_user_id = :supplier_user_id LIMIT 1');
    $proposalStmt->execute(['request_id' => $requestId, 'supplier_user_id' => $user['id']]);
    $myProposal = $proposalStmt->fetch() ?: null;
}

$hiringStmt = $pdo->prepare('SELECT id, supplier_user_id, status FROM hirings WHERE service_request_id = :request_id LIMIT 1');
$hiringStmt->execute(['request_id' => $requestId]);
$hiring = $hiringStmt->fetch() ?: null;

$photoStmt = $pdo->prepare('SELECT id, original_name FROM service_request_photos WHERE service_request_id = :request_id ORDER BY id');
try {
    $photoStmt->execute(['request_id' => $requestId]);
    $photos = $photoStmt->fetchAll();
} catch (Throwable $exception) {
    $photos = [];
}

$proposals = [];
$matchCount = null;
$minPrice = null;
$minDays = null;
if ($isOwner || ($user['role'] ?? '') === 'admin') {
    $proposalList = $pdo->prepare("SELECT p.*, sp.id AS supplier_id, sp.trade_name, sp.verified_at
                                   FROM proposals p
                                   INNER JOIN supplier_profiles sp ON sp.user_id = p.supplier_user_id
                                   WHERE p.service_request_id = :request_id AND p.status <> 'retirada'
                                   ORDER BY CASE p.status WHEN 'aceita' THEN 0 WHEN 'enviada' THEN 1 ELSE 2 END, p.amount ASC, p.created_at ASC");
    $proposalList->execute(['request_id' => $requestId]);
    $proposals = $proposalList->fetchAll();
    $activeValues = array_values(array_filter($proposals, static fn(array $p): bool => in_array($p['status'], ['enviada', 'aceita'], true)));
    if ($activeValues) {
        $minPrice = min(array_map(static fn(array $p): float => (float) $p['amount'], $activeValues));
        $days = array_values(array_filter(array_map(static fn(array $p): ?int => $p['estimated_days'] !== null ? (int) $p['estimated_days'] : null, $activeValues), static fn(?int $v): bool => $v !== null && $v > 0));
        $minDays = $days ? min($days) : null;
    }
    $matchCount = Marketplace::countMatchingSuppliers($pdo, (int) $request['category_id'], (string) $request['city'], (string) $request['state'], (string) $request['urgency']);
}

$urgencyLabels = ['normal' => 'Normal', 'urgente' => 'Urgente', 'agora' => 'Preciso Agora'];
$statusLabels = ['rascunho' => 'Rascunho', 'aberta' => 'Aberta', 'em_cotacao' => 'Em cotação', 'contratada' => 'Contratada', 'cancelada' => 'Cancelada', 'concluida' => 'Concluída'];
$proposalStatusLabels = ['enviada' => 'Em análise', 'aceita' => 'Aceita', 'recusada' => 'Não selecionada', 'retirada' => 'Retirada'];
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e((string) $request['title']) ?> — CondoConecta</title><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="dashboard-page">
<header class="topbar"><div class="container nav"><a class="brand" href="painel.php">Condo<span>Conecta</span></a><nav><?php if ($isSupplier): ?><a href="oportunidades.php">Oportunidades</a><?php else: ?><a href="minhas-solicitacoes.php">Minhas solicitações</a><?php endif; ?><a class="button button-ghost" href="painel.php">Painel</a></nav></div></header>
<main class="container request-detail-shell">
    <?php if (isset($_GET['proposta'])): ?><div class="alert alert-success">Proposta salva com sucesso. Você pode editá-la enquanto a solicitação estiver em cotação.</div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <section class="request-detail-hero">
        <div>
            <div class="request-meta"><span class="status-tag <?= $request['urgency'] === 'agora' ? 'status-danger' : ($request['urgency'] === 'urgente' ? 'status-pending' : 'status-muted') ?>"><?= e($urgencyLabels[$request['urgency']] ?? $request['urgency']) ?></span><span><?= e((string) $request['category_name']) ?></span><span><?= e($statusLabels[$request['status']] ?? $request['status']) ?></span></div>
            <h1><?= e((string) $request['title']) ?></h1>