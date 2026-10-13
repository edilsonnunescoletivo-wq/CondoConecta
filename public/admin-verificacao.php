<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Verification;
use Throwable;

$user = Auth::requireAuth();
if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Acesso restrito à administração.');
}

$pdo = Database::connection($config);
$errors = [];
$success = null;
$supplierUserId = (int) ($_GET['supplier'] ?? $_POST['supplier_user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $notes = trim((string) ($_POST['notes'] ?? ''));
        if (mb_strlen($notes) > 1000) $errors[] = 'A observação deve ter no máximo 1.000 caracteres.';

        try {
            if (!$errors && in_array($action, ['approve_document','reject_document'], true)) {
                $documentId = (int) ($_POST['document_id'] ?? 0);
                $newStatus = $action === 'approve_document' ? 'aprovado' : 'rejeitado';
                if ($newStatus === 'rejeitado' && $notes === '') $errors[] = 'Informe o motivo da rejeição.';
                if (!$errors) {
                    $stmt = $pdo->prepare('UPDATE supplier_documents SET status = :status, review_notes = :notes, reviewed_by_user_id = :admin_id, reviewed_at = CURRENT_TIMESTAMP WHERE id = :id AND supplier_user_id = :supplier_user_id');
                    $stmt->execute([
                        'status' => $newStatus,
                        'notes' => $notes !== '' ? $notes : null,
                        'admin_id' => $user['id'],
                        'id' => $documentId,
                        'supplier_user_id' => $supplierUserId,
                    ]);
                    Verification::refreshSeal($pdo, $supplierUserId);
                    Csrf::rotate();
                    redirect('admin-verificacao.php?supplier=' . $supplierUserId . '&revisado=1');
                }
            } elseif (!$errors && $action === 'grant_seal') {
                if (!Verification::grantSeal($pdo, $supplierUserId, (int) $user['id'], $notes)) {
                    $errors[] = 'Para conceder o selo, aprove primeiro um documento-base de identificação válido.';
                } else {
                    Csrf::rotate();
                    redirect('admin-verificacao.php?supplier=' . $supplierUserId . '&selo=concedido');
                }
            } elseif (!$errors && $action === 'revoke_seal') {
                Verification::revokeSeal($pdo, $supplierUserId, (int) $user['id'], $notes !== '' ? $notes : 'Selo revogado pela administração.');
                Csrf::rotate();
                redirect('admin-verificacao.php?supplier=' . $supplierUserId . '&selo=revogado');
            }
        } catch (Throwable $exception) {
            $errors[] = 'Não foi possível concluir a revisão. Verifique se a migração 005 foi aplicada.';
        }
    }
}

if ($supplierUserId > 0) {
    Verification::refreshSeal($pdo, $supplierUserId);
    $supplierStmt = $pdo->prepare('SELECT sp.user_id, sp.trade_name, sp.city, sp.state, sp.verified_at, sp.verification_notes, sp.verification_reviewed_at, u.email, u.phone FROM supplier_profiles sp INNER JOIN users u ON u.id = sp.user_id WHERE sp.user_id = :user_id LIMIT 1');
    $supplierStmt->execute(['user_id' => $supplierUserId]);
    $supplier = $supplierStmt->fetch();
    if (!$supplier) {
        http_response_code(404);
        exit('Fornecedor não encontrado.');
    }
    $docStmt = $pdo->prepare('SELECT id, document_type, original_name, status, review_notes, expires_at, created_at, reviewed_at FROM supplier_documents WHERE supplier_user_id = :user_id ORDER BY FIELD(status,\'pendente\',\'rejeitado\',\'aprovado\'), created_at DESC');
    $docStmt->execute(['user_id' => $supplierUserId]);
    $documents = $docStmt->fetchAll();
} else {
    $supplier = null;
    $documents = [];
    $listStmt = $pdo->query("SELECT sp.user_id, sp.trade_name, sp.city, sp.state, sp.verified_at,
        SUM(CASE WHEN sd.status='pendente' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN sd.status='aprovado' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN sd.status='rejeitado' THEN 1 ELSE 0 END) AS rejected_count,
        COUNT(sd.id) AS document_count
        FROM supplier_profiles sp
        INNER JOIN users u ON u.id = sp.user_id AND u.active = 1
        LEFT JOIN supplier_documents sd ON sd.supplier_user_id = sp.user_id
        GROUP BY sp.user_id, sp.trade_name, sp.city, sp.state, sp.verified_at
        ORDER BY pending_count DESC, sp.trade_name ASC");
    $suppliers = $listStmt->fetchAll();
}

if (isset($_GET['revisado'])) $success = 'Documento revisado com sucesso.';
if (($_GET['selo'] ?? '') === 'concedido') $success = 'Selo Fornecedor Verificado concedido.';
if (($_GET['selo'] ?? '') === 'revogado') $success = 'Selo revogado.';
$statusLabels = ['pendente' => 'Pendente', 'aprovado' => 'Aprovado', 'rejeitado' => 'Rejeitado'];
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verificação de fornecedores — CondoConecta</title><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="dashboard-page"><header class="topbar"><div class="container nav"><a class="brand" href="painel.php">Condo<span>Conecta</span></a><nav><?php if ($supplier): ?><a href="admin-verificacao.php">Fila de verificação</a><?php endif; ?><a class="button button-ghost" href="painel.php">Painel</a></nav></div></header>
<main class="container dashboard-shell">
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (!$supplier): ?>
<div class="page-heading"><span class="badge">ADMINISTRAÇÃO</span><h1>Fornecedor Verificado</h1><p>Revise documentos e controle o selo de confiança da plataforma.</p></div>
<?php if (!$suppliers): ?><div class="empty-state">Nenhum fornecedor cadastrado.</div><?php else: ?><div class="request-list"><?php foreach ($suppliers as $row): ?><article class="request-row"><div class="request-row-main"><div class="request-meta"><?php if ($row['verified_at']): ?><span class="status-tag status-ok">✓ Verificado</span><?php else: ?><span class="status-tag status-muted">Sem selo</span><?php endif; ?><span><?= (int)$row['pending_count'] ?> pendente(s)</span><span><?= (int)$row['approved_count'] ?> aprovado(s)</span><span><?= (int)$row['rejected_count'] ?> rejeitado(s)</span></div><h2><?= e((string)$row['trade_name']) ?></h2><div class="request-meta"><span>📍 <?= e((string)$row['city']) ?>/<?= e((string)$row['state']) ?></span><span><?= (int)$row['document_count'] ?> documento(s)</span></div></div><a class="button button-primary" href="admin-verificacao.php?supplier=<?= (int)$row['user_id'] ?>">Analisar</a></article><?php endforeach; ?></div><?php endif; ?>
<?php else: ?>
<div class="page-heading"><span class="badge">ANÁLISE</span><h1><?= e((string)$supplier['trade_name']) ?></h1><p><?= e((string)$supplier['city']) ?>/<?= e((string)$supplier['state']) ?> · <?= e((string)$supplier['email']) ?><?= !empty($supplier['phone']) ? ' · '.e((string)$supplier['phone']) : '' ?></p></div>
<div class="status-banner"><strong><?= $supplier['verified_at'] ? '✓ Fornecedor Verificado' : 'Fornecedor ainda sem selo' ?></strong><span><?= $supplier['verified_at'] ? 'Selo concedido em '.e(date('d/m/Y', strtotime((string)$supplier['verified_at']))) : 'Aprove ao menos um documento-base válido antes de conceder o selo.' ?></span></div>
<section class="card-panel"><h2>Controle do selo</h2><form method="post" class="stack-form"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="supplier_user_id" value="<?= (int)$supplier['user_id'] ?>"><label>Observação administrativa<textarea name="notes" rows="3" maxlength="1000"><?= e((string)($supplier['verification_notes'] ?? '')) ?></textarea></label><?php if ($supplier['verified_at']): ?><button class="button button-ghost" name="action" value="revoke_seal" type="submit">Revogar selo</button><?php else: ?><button class="button button-primary" name="action" value="grant_seal" type="submit">Conceder selo verificado</button><?php endif; ?></form></section>
<section class="card-panel" style="margin-top:18px"><h2>Documentos</h2><?php if (!$documents): ?><div class="empty-state">Este fornecedor ainda não enviou documentos.</div><?php else: ?><div class="request-list"><?php foreach ($documents as $doc): ?><article class="request-row"><div class="request-row-main"><div class="request-meta"><span class="status-tag <?= $doc['status']==='aprovado'?'status-ok':($doc['status']==='rejeitado'?'status-danger':'status-pending') ?>"><?= e($statusLabels[$doc['status']] ?? $doc['status']) ?></span><span><?= e(Verification::DOCUMENT_TYPES[$doc['document_type']] ?? $doc['document_type']) ?></span><?php if ($doc['expires_at']): ?><span>Validade: <?= e(date('d/m/Y', strtotime((string)$doc['expires_at']))) ?></span><?php endif; ?></div><h2><?= e((string)($doc['original_name'] ?? 'Documento')) ?></h2><?php if ($doc['review_notes']): ?><p><?= e((string)$doc['review_notes']) ?></p><?php endif; ?></div><div class="card-actions"><a class="button button-secondary" target="_blank" rel="noopener" href="documento-fornecedor.php?id=<?= (int)$doc['id'] ?>">Visualizar</a><form method="post" class="review-inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="supplier_user_id" value="<?= (int)$supplier['user_id'] ?>"><input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>"><input type="text" name="notes" maxlength="1000" placeholder="Observação / motivo da rejeição"><button class="button button-secondary" name="action" value="approve_document" type="submit">Aprovar</button><button class="button button-ghost" name="action" value="reject_document" type="submit">Rejeitar</button></form></div></article><?php endforeach; ?></div><?php endif; ?></section>
<?php endif; ?>
</main></body></html>
