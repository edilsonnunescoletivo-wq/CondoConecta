<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Verification;
use Throwable;

$user = Auth::requireAuth();
if (($user['role'] ?? '') !== 'fornecedor') redirect('painel.php');

$pdo = Database::connection($config);
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } else {
        $documentType = (string) ($_POST['document_type'] ?? '');
        $expiresAt = trim((string) ($_POST['expires_at'] ?? ''));
        $file = $_FILES['document'] ?? null;

        if (!isset(Verification::DOCUMENT_TYPES[$documentType])) $errors[] = 'Selecione um tipo de documento válido.';
        if ($expiresAt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt)) $errors[] = 'Informe uma validade válida.';
        if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) $errors[] = 'Selecione um arquivo para envio.';

        if (!$errors && is_array($file)) {
            $tmp = (string) ($file['tmp_name'] ?? '');
            $size = (int) ($file['size'] ?? 0);
            $originalName = mb_substr(basename((string) ($file['name'] ?? 'documento')), 0, 180);
            if ($size <= 0 || $size > 10 * 1024 * 1024) $errors[] = 'O documento deve ter no máximo 10 MB.';

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = is_file($tmp) ? (string) $finfo->file($tmp) : '';
            $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
            if (!isset($allowed[$mime])) $errors[] = 'Envie PDF, JPG ou PNG.';

            if (!$errors) {
                $storageDir = dirname(__DIR__) . '/storage/supplier-documents';
                if (!is_dir($storageDir) && !mkdir($storageDir, 0770, true) && !is_dir($storageDir)) {
                    $errors[] = 'Não foi possível preparar o armazenamento dos documentos.';
                } else {
                    $storedName = bin2hex(random_bytes(24)) . '.' . $allowed[$mime];
                    $destination = $storageDir . '/' . $storedName;
                    if (!move_uploaded_file($tmp, $destination)) {
                        $errors[] = 'Não foi possível armazenar o documento.';
                    } else {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO supplier_documents
                                (supplier_user_id, document_type, file_path, original_name, mime_type, file_size, status, expires_at)
                                VALUES (:user_id, :type, :file_path, :original_name, :mime_type, :file_size, 'pendente', :expires_at)");
                            $stmt->execute([
                                'user_id' => $user['id'],
                                'type' => $documentType,
                                'file_path' => $storedName,
                                'original_name' => $originalName,
                                'mime_type' => $mime,
                                'file_size' => $size,
                                'expires_at' => $expiresAt !== '' ? $expiresAt : null,
                            ]);
                            Verification::refreshSeal($pdo, (int) $user['id']);
                            Csrf::rotate();
                            redirect('fornecedor-documentos.php?enviado=1');
                        } catch (Throwable $exception) {
                            @unlink($destination);
                            $errors[] = 'Não foi possível registrar o documento. Verifique se a migração 005 foi aplicada.';
                        }
                    }
                }
            }
        }
    }
}

Verification::refreshSeal($pdo, (int) $user['id']);
$profileStmt = $pdo->prepare('SELECT verified_at FROM supplier_profiles WHERE user_id = :user_id');
$profileStmt->execute(['user_id' => $user['id']]);
$verifiedAt = $profileStmt->fetchColumn();

$stmt = $pdo->prepare('SELECT id, document_type, original_name, status, review_notes, expires_at, created_at, reviewed_at FROM supplier_documents WHERE supplier_user_id = :user_id ORDER BY created_at DESC');
$stmt->execute(['user_id' => $user['id']]);
$documents = $stmt->fetchAll();

if (isset($_GET['enviado'])) $success = 'Documento enviado para análise.';
$statusLabels = ['pendente' => 'Pendente', 'aprovado' => 'Aprovado', 'rejeitado' => 'Rejeitado'];
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Documentos — CondoConecta</title><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="dashboard-page"><header class="topbar"><div class="container nav"><a class="brand" href="painel.php">Condo<span>Conecta</span></a><nav><a class="button button-ghost" href="painel.php">Painel</a></nav></div></header>
<main class="container dashboard-shell">
<div class="page-heading"><span class="badge">VERIFICAÇÃO</span><h1>Fornecedor Verificado</h1><p>Envie documentos para análise. Seus arquivos ficam privados e só podem ser acessados por você e pela administração da plataforma.</p></div>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="status-banner"><strong><?= $verifiedAt ? '✓ Selo ativo' : 'Selo ainda não concedido' ?></strong><span><?= $verifiedAt ? 'Seu perfil está identificado como Fornecedor Verificado.' : 'A administração analisará os documentos antes de conceder o selo.' ?></span></div>
<section class="card-panel"><h2>Enviar documento</h2><form method="post" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><label>Tipo<select name="document_type" required><option value="">Selecione</option><?php foreach (Verification::DOCUMENT_TYPES as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label><label>Validade <span class="optional">se houver</span><input type="date" name="expires_at"></label><label class="form-span">Arquivo PDF/JPG/PNG<input type="file" name="document" accept="application/pdf,image/jpeg,image/png" required></label><div class="form-span"><button class="button button-primary" type="submit">Enviar para análise</button></div></form></section>
<section class="card-panel" style="margin-top:18px"><h2>Documentos enviados</h2><?php if (!$documents): ?><div class="empty-state">Nenhum documento enviado ainda.</div><?php else: ?><div class="request-list"><?php foreach ($documents as $doc): ?><article class="request-row"><div class="request-row-main"><div class="request-meta"><span class="status-tag <?= $doc['status']==='aprovado'?'status-ok':($doc['status']==='rejeitado'?'status-danger':'status-pending') ?>"><?= e($statusLabels[$doc['status']] ?? $doc['status']) ?></span><span><?= e(Verification::DOCUMENT_TYPES[$doc['document_type']] ?? $doc['document_type']) ?></span><?php if ($doc['expires_at']): ?><span>Validade: <?= e(date('d/m/Y', strtotime((string)$doc['expires_at']))) ?></span><?php endif; ?></div><h2><?= e((string)($doc['original_name'] ?? 'Documento')) ?></h2><?php if (!empty($doc['review_notes'])): ?><p><?= e((string)$doc['review_notes']) ?></p><?php endif; ?></div><a class="button button-secondary" href="documento-fornecedor.php?id=<?= (int)$doc['id'] ?>" target="_blank" rel="noopener">Visualizar</a></article><?php endforeach; ?></div><?php endif; ?></section>
</main></body></html>
