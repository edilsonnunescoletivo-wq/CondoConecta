<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use Throwable;

$user = Auth::requireAuth();
if (($user['role'] ?? '') !== 'fornecedor') {
    redirect('painel.php');
}

$pdo = Database::connection($config);
$profileStmt = $pdo->prepare('SELECT id, trade_name FROM supplier_profiles WHERE user_id = :user_id LIMIT 1');
$profileStmt->execute(['user_id' => $user['id']]);
$profile = $profileStmt->fetch();
if (!$profile) {
    redirect('painel.php');
}

$supplierId = (int) $profile['id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } else {
        $action = (string) ($_POST['action'] ?? 'add');

        if ($action === 'add') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $upload = $_FILES['image'] ?? null;

            if ($title === '' || mb_strlen($title) > 160) {
                $errors[] = 'Informe um título com até 160 caracteres.';
            }
            if (mb_strlen($description) > 1500) {
                $errors[] = 'A descrição deve ter no máximo 1.500 caracteres.';
            }
            if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                $errors[] = 'Selecione uma foto do trabalho.';
            }

            $storedName = null;
            $destination = null;
            $mime = null;
            $size = 0;

            if (!$errors && is_array($upload)) {
                if ((int) $upload['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = 'A foto não pôde ser enviada.';
                } else {
                    $tmp = (string) ($upload['tmp_name'] ?? '');
                    $size = (int) ($upload['size'] ?? 0);
                    if ($size <= 0 || $size > 5 * 1024 * 1024) {
                        $errors[] = 'A foto deve ter no máximo 5 MB.';
                    } else {
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mime = is_file($tmp) ? (string) $finfo->file($tmp) : '';
                        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                        if (!isset($allowed[$mime])) {
                            $errors[] = 'Envie uma imagem JPG, PNG ou WebP.';
                        } else {
                            $storageDir = dirname(__DIR__) . '/storage/portfolio-images';
                            if (!is_dir($storageDir) && !mkdir($storageDir, 0770, true) && !is_dir($storageDir)) {
                                $errors[] = 'Não foi possível preparar o armazenamento da imagem.';
                            } else {
                                $storedName = bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
                                $destination = $storageDir . '/' . $storedName;
                                if (!move_uploaded_file($tmp, $destination)) {
                                    $errors[] = 'Não foi possível salvar a foto do portfólio.';
                                }
                            }
                        }
                    }
                }
            }

            if (!$errors && $storedName !== null) {
                try {
                    $stmt = $pdo->prepare('INSERT INTO supplier_portfolio (supplier_id, title, description, image_path, image_mime, image_size) VALUES (:supplier_id, :title, :description, :image_path, :image_mime, :image_size)');
                    $stmt->execute([
                        'supplier_id' => $supplierId,
                        'title' => $title,
                        'description' => $description !== '' ? $description : null,
                        'image_path' => $storedName,
                        'image_mime' => $mime,
                        'image_size' => $size,
                    ]);
                    Csrf::rotate();
                    redirect('portfolio.php?adicionado=1');
                } catch (Throwable $exception) {
                    if ($destination !== null && is_file($destination)) {
                        @unlink($destination);
                    }
                    $errors[] = 'Não foi possível adicionar o trabalho ao portfólio.';
                }
            }
        } elseif ($action === 'delete') {
            $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
            if ($portfolioId > 0) {
                $stmt = $pdo->prepare('SELECT image_path FROM supplier_portfolio WHERE id = :id AND supplier_id = :supplier_id LIMIT 1');
                $stmt->execute(['id' => $portfolioId, 'supplier_id' => $supplierId]);
                $item = $stmt->fetch();
                if ($item) {
                    $pdo->prepare('DELETE FROM supplier_portfolio WHERE id = :id AND supplier_id = :supplier_id')
                        ->execute(['id' => $portfolioId, 'supplier_id' => $supplierId]);
                    $fileName = basename((string) ($item['image_path'] ?? ''));
                    if ($fileName !== '') {
                        $path = dirname(__DIR__) . '/storage/portfolio-images/' . $fileName;
                        if (is_file($path)) {
                            @unlink($path);
                        }
                    }
                }
            }
            Csrf::rotate();
            redirect('portfolio.php?removido=1');
        }
    }
}

$listStmt = $pdo->prepare('SELECT id, title, description, image_url, image_path, created_at FROM supplier_portfolio WHERE supplier_id = :supplier_id AND active = 1 ORDER BY sort_order, id DESC');
$listStmt->execute(['supplier_id' => $supplierId]);
$items = $listStmt->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portfólio — CondoConecta</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="dashboard-page">
<header class="topbar"><div class="container nav"><a class="brand" href="painel.php">Condo<span>Conecta</span></a><nav><a href="perfil-fornecedor.php">Perfil</a><a class="button button-ghost" href="painel.php">Painel</a></nav></div></header>
<main class="container dashboard-shell">
    <div class="page-heading"><span class="badge">PORTFÓLIO</span><h1>Trabalhos realizados</h1><p>Adicione fotos reais dos serviços executados. As imagens ficam armazenadas fora da pasta pública e são entregues por uma rota controlada.</p></div>
    <?php if (isset($_GET['adicionado'])): ?><div class="alert alert-success">Trabalho adicionado ao portfólio.</div><?php endif; ?>
    <?php if (isset($_GET['removido'])): ?><div class="alert alert-success">Item removido do portfólio.</div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <section class="editor-card">
        <h2>Adicionar trabalho</h2>
        <form method="post" enctype="multipart/form-data" class="form-grid portfolio-form">
            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="add">
            <label>Título<input name="title" maxlength="160" required placeholder="Ex.: Reforma do quadro elétrico"></label>
            <label>Foto JPG, PNG ou WebP<input type="file" name="image" accept="image/jpeg,image/png,image/webp" required></label>
            <label class="form-span">Descrição <span class="optional">opcional</span><textarea name="description" rows="3" maxlength="1500" placeholder="Descreva o serviço executado e o resultado."></textarea></label>
            <div class="form-span"><button class="button button-primary" type="submit">Adicionar ao portfólio</button></div>
        </form>
    </section>

    <?php if ($items): ?><div class="portfolio-grid"><?php foreach ($items as $item): ?>
        <article class="portfolio-card">
            <?php if (!empty($item['image_path'])): ?><img src="portfolio-imagem.php?id=<?= (int) $item['id'] ?>" alt="<?= e((string) $item['title']) ?>" loading="lazy"><?php elseif (!empty($item['image_url'])): ?><img src="<?= e((string) $item['image_url']) ?>" alt="<?= e((string) $item['title']) ?>" loading="lazy" referrerpolicy="no-referrer"><?php else: ?><div class="portfolio-placeholder">📷</div><?php endif; ?>
            <div class="portfolio-body"><h3><?= e((string) $item['title']) ?></h3><?php if (!empty($item['description'])): ?><p><?= nl2br(e((string) $item['description'])) ?></p><?php endif; ?>
                <form method="post" onsubmit="return confirm('Remover este trabalho do portfólio?');"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="portfolio_id" value="<?= (int) $item['id'] ?>"><button class="link-danger" type="submit">Remover</button></form>
            </div>
        </article>
    <?php endforeach; ?></div><?php else: ?><div class="empty-state">Seu portfólio ainda está vazio.</div><?php endif; ?>
</main>
</body>
</html>
