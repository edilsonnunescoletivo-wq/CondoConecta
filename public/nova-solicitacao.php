<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Marketplace;
use Throwable;

$user = Auth::requireAuth();
if (($user['role'] ?? '') !== 'contratante') {
    redirect('painel.php');
}

$pdo = Database::connection($config);
$errors = [];

$profileStmt = $pdo->prepare('SELECT display_name, city, state, address FROM contractor_profiles WHERE user_id = :user_id LIMIT 1');
$profileStmt->execute(['user_id' => $user['id']]);
$profile = $profileStmt->fetch() ?: [];

$categories = $pdo->query('SELECT id, name FROM categories WHERE active = 1 ORDER BY name')->fetchAll();

$urgencyDefault = (($_GET['urgencia'] ?? '') === 'agora') ? 'agora' : 'normal';
$values = [
    'category_id' => '',
    'title' => '',
    'description' => '',
    'urgency' => $urgencyDefault,
    'city' => (string) ($profile['city'] ?? ''),
    'state' => (string) ($profile['state'] ?? 'BA'),
    'address_reference' => (string) ($profile['address'] ?? ''),
    'desired_date' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $default) {
        $values[$key] = trim((string) ($_POST[$key] ?? $default));
    }

    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    }

    $categoryId = (int) $values['category_id'];
    $validCategoryIds = array_map(static fn(array $row): int => (int) $row['id'], $categories);
    if (!in_array($categoryId, $validCategoryIds, true)) {
        $errors[] = 'Selecione uma categoria válida.';
    }

    if (mb_strlen($values['title']) < 5 || mb_strlen($values['title']) > 180) {
        $errors[] = 'Informe um título entre 5 e 180 caracteres.';
    }
    if (mb_strlen($values['description']) < 15) {
        $errors[] = 'Descreva a necessidade com pelo menos 15 caracteres.';
    }
    if (!in_array($values['urgency'], ['normal', 'urgente', 'agora'], true)) {
        $errors[] = 'Selecione um nível de urgência válido.';
    }
    if ($values['city'] === '' || mb_strlen($values['city']) > 100) {
        $errors[] = 'Informe a cidade do atendimento.';
    }
    $values['state'] = strtoupper($values['state']);
    if (!preg_match('/^[A-Z]{2}$/', $values['state'])) {
        $errors[] = 'Informe a UF com duas letras.';
    }
    if ($values['desired_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['desired_date'])) {
        $errors[] = 'Informe uma data desejada válida.';
    }

    $photos = [];
    if (isset($_FILES['photos']) && is_array($_FILES['photos']['name'] ?? null)) {
        $count = count($_FILES['photos']['name']);
        if ($count > 5) {
            $errors[] = 'Envie no máximo 5 fotos por solicitação.';
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        for ($i = 0; $i < min($count, 5); $i++) {
            $error = (int) ($_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                $errors[] = 'Uma das fotos não pôde ser enviada.';
                continue;
            }

            $tmp = (string) ($_FILES['photos']['tmp_name'][$i] ?? '');
            $size = (int) ($_FILES['photos']['size'][$i] ?? 0);
            if ($size <= 0 || $size > 5 * 1024 * 1024) {
                $errors[] = 'Cada foto deve ter no máximo 5 MB.';
                continue;
            }

            $mime = is_file($tmp) ? (string) $finfo->file($tmp) : '';
            if (!isset($allowed[$mime])) {
                $errors[] = 'As fotos devem estar em JPG, PNG ou WebP.';
                continue;
            }

            $photos[] = [
                'tmp' => $tmp,
                'name' => mb_substr(basename((string) ($_FILES['photos']['name'][$i] ?? 'foto')), 0, 180),
                'size' => $size,
                'mime' => $mime,
                'extension' => $allowed[$mime],
            ];
        }
    }

    if (!$errors) {
        $movedFiles = [];
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO service_requests
                (contractor_user_id, category_id, title, description, urgency, city, state, address_reference, desired_date, status)
                VALUES (:contractor_user_id, :category_id, :title, :description, :urgency, :city, :state, :address_reference, :desired_date, 'aberta')");
            $stmt->execute([
                'contractor_user_id' => $user['id'],
                'category_id' => $categoryId,
                'title' => $values['title'],
                'description' => $values['description'],
                'urgency' => $values['urgency'],
                'city' => $values['city'],
                'state' => $values['state'],
                'address_reference' => $values['address_reference'] !== '' ? $values['address_reference'] : null,
                'desired_date' => $values['desired_date'] !== '' ? $values['desired_date'] : null,
            ]);
            $requestId = (int) $pdo->lastInsertId();

            if ($photos) {
                $storageDir = dirname(__DIR__) . '/storage/request-photos';
                if (!is_dir($storageDir) && !mkdir($storageDir, 0770, true) && !is_dir($storageDir)) {
                    throw new RuntimeException('Não foi possível preparar a pasta de fotos.');
                }

                $photoStmt = $pdo->prepare('INSERT INTO service_request_photos (service_request_id, original_name, file_path, mime_type, file_size) VALUES (:request_id, :original_name, :file_path, :mime_type, :file_size)');
                foreach ($photos as $photo) {
                    $storedName = bin2hex(random_bytes(20)) . '.' . $photo['extension'];
                    $destination = $storageDir . '/' . $storedName;
                    if (!move_uploaded_file($photo['tmp'], $destination)) {
                        throw new RuntimeException('Não foi possível salvar uma das fotos.');
                    }
                    $movedFiles[] = $destination;
                    $photoStmt->execute([
                        'request_id' => $requestId,
                        'original_name' => $photo['name'],
                        'file_path' => $storedName,
                        'mime_type' => $photo['mime'],
                        'file_size' => $photo['size'],
                    ]);
                }
            }

            $pdo->commit();
            $matchCount = Marketplace::countMatchingSuppliers($pdo, $categoryId, $values['city'], $values['state'], $values['urgency']);
            redirect('minhas-solicitacoes.php?criada=' . $requestId . '&match=' . $matchCount);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($movedFiles as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            $errors[] = 'Não foi possível criar a solicitação. Verifique a configuração do banco e tente novamente.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nova solicitação — <?= e((string) ($config['name'] ?? 'CondoConecta')) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="dashboard-page">
<header class="topbar"><div class="container nav"><a class="brand" href="painel.php">Condo<span>Conecta</span></a><nav><a href="minhas-solicitacoes.php">Minhas solicitações</a><a class="button button-ghost" href="painel.php">Painel</a></nav></div></header>
<main class="container form-page-shell">
    <div class="page-heading"><div><span class="badge">CONTRATANTE</span><h1>Nova solicitação de serviço</h1><p>Descreva a necessidade. O CondoConecta mostrará a oportunidade aos fornecedores compatíveis com a categoria e região.</p></div></div>

    <?php if ($errors): ?><div class="alert alert-error"><strong>Revise os dados:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="market-form card-panel">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <div class="form-grid">
            <label>Categoria
                <select name="category_id" required><option value="">Selecione</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (string) $category['id'] === $values['category_id'] ? 'selected' : '' ?>><?= e((string) $category['name']) ?></option><?php endforeach; ?></select>
            </label>
            <label>Urgência
                <select name="urgency" required>
                    <option value="normal" <?= $values['urgency'] === 'normal' ? 'selected' : '' ?>>Normal</option>
                    <option value="urgente" <?= $values['urgency'] === 'urgente' ? 'selected' : '' ?>>Urgente</option>
                    <option value="agora" <?= $values['urgency'] === 'agora' ? 'selected' : '' ?>>Preciso Agora</option>
                </select>
            </label>
            <label class="form-span">Título
                <input type="text" name="title" maxlength="180" required placeholder="Ex.: Bomba da piscina parou de funcionar" value="<?= e($values['title']) ?>">
            </label>
            <label class="form-span">Descrição
                <textarea name="description" rows="6" required placeholder="Explique o problema, equipamento, sintomas e qualquer informação importante."><?= e($values['description']) ?></textarea>
            </label>
            <label>Cidade
                <input type="text" name="city" maxlength="100" required value="<?= e($values['city']) ?>">
            </label>
            <label>UF
                <input type="text" name="state" maxlength="2" required value="<?= e($values['state']) ?>">
            </label>
            <label class="form-span">Local / referência <span class="optional">opcional</span>
                <input type="text" name="address_reference" maxlength="220" value="<?= e($values['address_reference']) ?>" placeholder="Ex.: casa de bombas, bloco A, área da piscina">
            </label>
            <label>Data desejada <span class="optional">opcional</span>
                <input type="date" name="desired_date" value="<?= e($values['desired_date']) ?>">
            </label>
            <label>Fotos <span class="optional">até 5 arquivos</span>
                <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
            </label>
        </div>
        <div class="urgency-help">
            <strong>Como funciona o Preciso Agora?</strong>
            <span>A oportunidade é exibida apenas a fornecedores compatíveis que habilitaram atendimento emergencial naquela região.</span>
        </div>
        <div class="form-actions"><button class="button button-primary" type="submit">Publicar solicitação</button><a href="painel.php">Cancelar</a></div>
    </form>
</main>
</body>
</html>
