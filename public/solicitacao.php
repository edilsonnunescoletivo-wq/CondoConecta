<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Marketplace;
use Throwable;

$user = Auth::requireAuth();
$pdo = Database::connection($config);
$requestId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($requestId <= 0) {
    http_response_code(404);
    exit('Solicitação não encontrada.');
}

$stmt = $pdo->prepare("SELECT sr.*, c.name AS category_name, cp.display_name AS contractor_name
                       FROM service_requests sr
                       INNER JOIN categories c ON c.id = sr.category_id
                       INNER JOIN contractor_profiles cp ON cp.user_id = sr.contractor_user_id
                       WHERE sr.id = :id LIMIT 1");
$stmt->execute(['id' => $requestId]);
$request = $stmt->fetch();
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isSupplier && $supplierAllowed) {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    }

    if (!in_array($request['status'], ['aberta', 'em_cotacao'], true)) {
        $errors[] = 'Esta solicitação não está mais recebendo propostas.';
    }

    $amountRaw = str_replace(['.', ','], ['', '.'], trim((string) ($_POST['amount'] ?? '')));
    $amount = (float) $amountRaw;
    $estimatedDays = trim((string) ($_POST['estimated_days'] ?? ''));
    $warrantyDays = trim((string) ($_POST['warranty_days'] ?? ''));
    $details = trim((string) ($_POST['details'] ?? ''));

    if ($amount <= 0 || $amount > 9999999999) {
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

if ($isSupplier) {
    $proposalStmt = $pdo->prepare('SELECT * FROM proposals WHERE service_request_id = :request_id AND supplier_user_id = :supplier_user_id LIMIT 1');
    $proposalStmt->execute(['request_id' => $requestId, 'supplier_user_id' => $user['id']]);
    $myProposal = $proposalStmt->fetch() ?: null;
}

$photoStmt = $pdo->prepare('SELECT id, original_name FROM service_request_photos WHERE service_request_id = :request_id ORDER BY id');
try {
    $photoStmt->execute(['request_id' => $requestId]);
    $photos = $photoStmt->fetchAll();
} catch (Throwable $exception) {
    $photos = [];
}

$proposals = [];
$matchCount = null;
if ($isOwner || ($user['role'] ?? '') === 'admin') {
    $proposalList = $pdo->prepare("SELECT p.*, sp.id AS supplier_id, sp.trade_name, sp.verified_at
                                   FROM proposals p
                                   INNER JOIN supplier_profiles sp ON sp.user_id = p.supplier_user_id
                                   WHERE p.service_request_id = :request_id AND p.status <> 'retirada'
                                   ORDER BY p.amount ASC, p.created_at ASC");
    $proposalList->execute(['request_id' => $requestId]);
    $proposals = $proposalList->fetchAll();
    $matchCount = Marketplace::countMatchingSuppliers($pdo, (int) $request['category_id'], (string) $request['city'], (string) $request['state'], (string) $request['urgency']);
}

$urgencyLabels = ['normal' => 'Normal', 'urgente' => 'Urgente', 'agora' => 'Preciso Agora'];
$statusLabels = ['rascunho' => 'Rascunho', 'aberta' => 'Aberta', 'em_cotacao' => 'Em cotação', 'contratada' => 'Contratada', 'cancelada' => 'Cancelada', 'concluida' => 'Concluída'];
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
            <p><?= nl2br(e((string) $request['description'])) ?></p>
        </div>
        <?php if ($isOwner && $matchCount !== null): ?><div class="match-summary"><strong><?= (int) $matchCount ?></strong><span>fornecedor(es) compatível(is)</span></div><?php endif; ?>
    </section>

    <div class="request-detail-grid">
        <section class="card-panel">
            <h2>Dados do atendimento</h2>
            <div class="detail-list"><div><span>Contratante</span><strong><?= e((string) $request['contractor_name']) ?></strong></div><div><span>Localidade</span><strong><?= e((string) $request['city']) ?> • <?= e((string) $request['state']) ?></strong></div><?php if ($request['address_reference']): ?><div><span>Referência</span><strong><?= e((string) $request['address_reference']) ?></strong></div><?php endif; ?><?php if ($request['desired_date']): ?><div><span>Data desejada</span><strong><?= e(date('d/m/Y', strtotime((string) $request['desired_date']))) ?></strong></div><?php endif; ?></div>

            <?php if ($photos): ?><h3>Fotos da necessidade</h3><div class="request-photo-grid"><?php foreach ($photos as $photo): ?><a href="solicitacao-imagem.php?id=<?= (int) $photo['id'] ?>" target="_blank" rel="noopener"><img src="solicitacao-imagem.php?id=<?= (int) $photo['id'] ?>" alt="<?= e((string) $photo['original_name']) ?>"></a><?php endforeach; ?></div><?php endif; ?>
        </section>

        <?php if ($isSupplier): ?>
            <section class="card-panel proposal-panel">
                <span class="badge">SUA PROPOSTA</span><h2><?= $myProposal ? 'Editar proposta' : 'Enviar proposta' ?></h2>
                <?php if (in_array($request['status'], ['aberta', 'em_cotacao'], true)): ?>
                <form method="post" class="stack-form">
                    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= $requestId ?>">
                    <label>Valor total (R$)<input type="text" name="amount" inputmode="decimal" required placeholder="0,00" value="<?= $myProposal ? e(number_format((float) $myProposal['amount'], 2, ',', '.')) : '' ?>"></label>
                    <div class="mini-grid"><label>Prazo estimado (dias)<input type="number" name="estimated_days" min="1" max="3650" value="<?= $myProposal ? e((string) ($myProposal['estimated_days'] ?? '')) : '' ?>"></label><label>Garantia (dias)<input type="number" name="warranty_days" min="0" max="3650" value="<?= $myProposal ? e((string) ($myProposal['warranty_days'] ?? '')) : '' ?>"></label></div>
                    <label>Detalhes<textarea name="details" rows="5" maxlength="3000" placeholder="Descreva escopo, materiais inclusos, condições e observações."><?= $myProposal ? e((string) ($myProposal['details'] ?? '')) : '' ?></textarea></label>
                    <button class="button button-primary" type="submit"><?= $myProposal ? 'Atualizar proposta' : 'Enviar proposta gratuitamente' ?></button>
                </form>
                <?php else: ?><p>Esta solicitação não está mais recebendo propostas.</p><?php endif; ?>
            </section>
        <?php else: ?>
            <section class="card-panel proposal-panel"><span class="badge">PROPOSTAS</span><h2><?= count($proposals) ?> recebida(s)</h2><?php if (!$proposals): ?><p class="muted">Aguardando fornecedores enviarem propostas.</p><?php else: ?><div class="proposal-list"><?php foreach ($proposals as $proposal): ?><article class="proposal-card"><div><strong><?= e((string) $proposal['trade_name']) ?> <?= $proposal['verified_at'] ? '✓' : '' ?></strong><a href="fornecedor.php?id=<?= (int) $proposal['supplier_id'] ?>" target="_blank" rel="noopener">Ver perfil</a></div><strong class="proposal-price">R$ <?= e(number_format((float) $proposal['amount'], 2, ',', '.')) ?></strong><div class="request-meta"><?php if ($proposal['estimated_days']): ?><span>Prazo: <?= (int) $proposal['estimated_days'] ?> dia(s)</span><?php endif; ?><?php if ($proposal['warranty_days'] !== null): ?><span>Garantia: <?= (int) $proposal['warranty_days'] ?> dia(s)</span><?php endif; ?></div><?php if ($proposal['details']): ?><p><?= nl2br(e((string) $proposal['details'])) ?></p><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?><p class="muted">A comparação e o aceite da proposta entram na próxima etapa.</p></section>
        <?php endif; ?>
    </div>
</main>
</body></html>
