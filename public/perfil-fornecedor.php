<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use PDO;
use Throwable;

$user = Auth::requireAuth();
if (($user['role'] ?? '') !== 'fornecedor') {
    redirect('painel.php');
}

$pdo = Database::connection($config);
$errors = [];
$success = null;

$profileStmt = $pdo->prepare('SELECT * FROM supplier_profiles WHERE user_id = :user_id LIMIT 1');
$profileStmt->execute(['user_id' => $user['id']]);
$profile = $profileStmt->fetch();
if (!$profile) {
    redirect('painel.php');
}

$supplierId = (int) $profile['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } else {
        $action = (string) ($_POST['action'] ?? 'save_profile');

        try {
            if ($action === 'save_profile') {
                $tradeName = trim((string) ($_POST['trade_name'] ?? ''));
                $legalName = trim((string) ($_POST['legal_name'] ?? ''));
                $cpfCnpj = preg_replace('/\D+/', '', (string) ($_POST['cpf_cnpj'] ?? '')) ?: null;
                $description = trim((string) ($_POST['description'] ?? ''));
                $city = trim((string) ($_POST['city'] ?? ''));
                $state = strtoupper(trim((string) ($_POST['state'] ?? 'BA')));
                $serviceRadius = (int) ($_POST['service_radius_km'] ?? 30);
                $emergencyService = isset($_POST['emergency_service']) ? 1 : 0;
                $categoryIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['categories'] ?? [])))));
                $regionsRaw = trim((string) ($_POST['regions'] ?? ''));

                if ($tradeName === '') $errors[] = 'Informe o nome de exibição da empresa ou profissional.';
                if ($city === '') $errors[] = 'Informe a cidade base.';
                if (!preg_match('/^[A-Z]{2}$/', $state)) $errors[] = 'Informe uma UF válida.';
                if ($serviceRadius < 1 || $serviceRadius > 300) $errors[] = 'O raio de atendimento deve ficar entre 1 e 300 km.';
                if ($description !== '' && strlen($description) > 2000) $errors[] = 'A descrição deve ter no máximo 2.000 caracteres.';
                if (!$categoryIds) $errors[] = 'Selecione pelo menos uma categoria de serviço.';

                $regions = [];
                foreach (preg_split('/\R+/', $regionsRaw) ?: [] as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    $parts = array_map('trim', explode('/', $line, 2));
                    $regionCity = $parts[0] ?? '';
                    $regionState = strtoupper($parts[1] ?? $state);
                    if ($regionCity === '' || !preg_match('/^[A-Z]{2}$/', $regionState)) {
                        $errors[] = 'Use uma região por linha no formato Cidade/UF, por exemplo: Guarajuba/BA.';
                        break;
                    }
                    $key = strtolower($regionState . '|' . $regionCity);
                    $regions[$key] = ['city' => $regionCity, 'state' => $regionState];
                }
                if (!$regions) $errors[] = 'Informe pelo menos uma cidade ou região atendida.';

                if (!$errors) {
                    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
                    $validStmt = $pdo->prepare("SELECT id FROM categories WHERE active = 1 AND id IN ($placeholders)");
                    $validStmt->execute($categoryIds);
                    $validIds = array_map('intval', array_column($validStmt->fetchAll(), 'id'));
                    sort($validIds);
                    $compareIds = $categoryIds;
                    sort($compareIds);
                    if ($validIds !== $compareIds) {
                        throw new RuntimeException('Uma das categorias selecionadas não está disponível.');
                    }

                    $pdo->beginTransaction();
                    $update = $pdo->prepare('UPDATE supplier_profiles SET legal_name = :legal_name, trade_name = :trade_name, cpf_cnpj = :cpf_cnpj, description = :description, city = :city, state = :state, service_radius_km = :radius, emergency_service = :emergency WHERE id = :id');
                    $update->execute([
                        'legal_name' => $legalName !== '' ? $legalName : null,
                        'trade_name' => $tradeName,
                        'cpf_cnpj' => $cpfCnpj,
                        'description' => $description !== '' ? $description : null,
                        'city' => $city,
                        'state' => $state,
                        'radius' => $serviceRadius,
                        'emergency' => $emergencyService,
                        'id' => $supplierId,
                    ]);

                    $pdo->prepare('DELETE FROM supplier_categories WHERE supplier_id = :supplier_id')->execute(['supplier_id' => $supplierId]);
                    $insertCategory = $pdo->prepare('INSERT INTO supplier_categories (supplier_id, category_id) VALUES (:supplier_id, :category_id)');
                    foreach ($validIds as $categoryId) {
                        $insertCategory->execute(['supplier_id' => $supplierId, 'category_id' => $categoryId]);
                    }

                    $pdo->prepare('DELETE FROM supplier_regions WHERE supplier_id = :supplier_id')->execute(['supplier_id' => $supplierId]);
                    $insertRegion = $pdo->prepare('INSERT INTO supplier_regions (supplier_id, city, state) VALUES (:supplier_id, :city, :state)');
                    foreach ($regions as $region) {
                        $insertRegion->execute(['supplier_id' => $supplierId, 'city' => $region['city'], 'state' => $region['state']]);
                    }

                    $pdo->commit();
                    Csrf::rotate();
                    redirect('perfil-fornecedor.php?salvo=1');
                }
            } elseif ($action === 'add_portfolio') {
                $title = trim((string) ($_POST['portfolio_title'] ?? ''));
                $description = trim((string) ($_POST['portfolio_description'] ?? ''));
                $imageUrl = trim((string) ($_POST['image_url'] ?? ''));

                if ($title === '') $errors[] = 'Informe um título para o trabalho do portfólio.';
                if (strlen($title) > 160) $errors[] = 'O título do portfólio deve ter no máximo 160 caracteres.';
                if ($description !== '' && strlen($description) > 1500) $errors[] = 'A descrição do portfólio deve ter no máximo 1.500 caracteres.';
                if ($imageUrl !== '' && (!filter_var($imageUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $imageUrl))) {
                    $errors[] = 'A imagem deve usar uma URL http ou https válida.';
                }

                if (!$errors) {
                    $stmt = $pdo->prepare('INSERT INTO supplier_portfolio (supplier_id, title, description, image_url) VALUES (:supplier_id, :title, :description, :image_url)');
                    $stmt->execute([
                        'supplier_id' => $supplierId,
                        'title' => $title,
                        'description' => $description !== '' ? $description : null,
                        'image_url' => $imageUrl !== '' ? $imageUrl : null,
                    ]);
                    Csrf::rotate();
                    redirect('perfil-fornecedor.php?portfolio=1#portfolio');
                }
            } elseif ($action === 'delete_portfolio') {
                $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
                if ($portfolioId > 0) {
                    $stmt = $pdo->prepare('DELETE FROM supplier_portfolio WHERE id = :id AND supplier_id = :supplier_id');
                    $stmt->execute(['id' => $portfolioId, 'supplier_id' => $supplierId]);
                }
                Csrf::rotate();
                redirect('perfil-fornecedor.php?removido=1#portfolio');
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Não foi possível salvar as alterações. Verifique se a migração do portfólio foi aplicada e tente novamente.';
        }
    }
}

$profileStmt->execute(['user_id' => $user['id']]);
$profile = $profileStmt->fetch();
$categories = $pdo->query('SELECT id, name FROM categories WHERE active = 1 ORDER BY name')->fetchAll();
$selectedStmt = $pdo->prepare('SELECT category_id FROM supplier_categories WHERE supplier_id = :supplier_id');
$selectedStmt->execute(['supplier_id' => $supplierId]);
$selectedCategories = array_map('intval', array_column($selectedStmt->fetchAll(), 'category_id'));
$regionStmt = $pdo->prepare('SELECT city, state FROM supplier_regions WHERE supplier_id = :supplier_id ORDER BY state, city');
$regionStmt->execute(['supplier_id' => $supplierId]);
$regions = $regionStmt->fetchAll();
$regionsText = implode("\n", array_map(static fn(array $region): string => $region['city'] . '/' . $region['state'], $regions));

$portfolio = [];
try {
    $portfolioStmt = $pdo->prepare('SELECT id, title, description, image_url, created_at FROM supplier_portfolio WHERE supplier_id = :supplier_id AND active = 1 ORDER BY sort_order, id DESC');
    $portfolioStmt->execute(['supplier_id' => $supplierId]);
    $portfolio = $portfolioStmt->fetchAll();
} catch (Throwable $exception) {
    // A instalação pode ainda não ter aplicado a migração do portfólio.
}

if (isset($_GET['salvo'])) $success = 'Perfil de serviços atualizado com sucesso.';
if (isset($_GET['portfolio'])) $success = 'Trabalho adicionado ao portfólio.';
if (isset($_GET['removido'])) $success = 'Item removido do portfólio.';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Perfil do fornecedor — <?= e((string) ($config['name'] ?? 'CondoConecta')) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="dashboard-page">
<header class="topbar"><div class="container nav"><a class="brand" href="painel.php">Condo<span>Conecta</span></a><nav><a href="painel.php">Painel</a><a class="button button-ghost" href="fornecedor.php?id=<?= $supplierId ?>" target="_blank" rel="noopener">Ver perfil público</a></nav></div></header>
<main class="container dashboard-shell">
    <section class="page-heading"><div><span class="badge">PERFIL DO FORNECEDOR</span><h1>Configure como os condomínios encontram você</h1><p>Escolha serviços, regiões e informações públicas. Quanto mais completo o perfil, melhor será o futuro matching de oportunidades.</p></div></section>

    <?php if ($errors): ?><div class="alert alert-error"><strong>Revise os dados:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

    <form class="profile-editor" method="post">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="save_profile">
        <section class="editor-card">
            <h2>Dados públicos</h2>
            <div class="form-grid">
                <label>Nome de exibição<input name="trade_name" maxlength="180" required value="<?= e((string) ($profile['trade_name'] ?? '')) ?>"></label>
                <label>Razão social <span class="optional">opcional</span><input name="legal_name" maxlength="180" value="<?= e((string) ($profile['legal_name'] ?? '')) ?>"></label>
                <label>CPF/CNPJ <span class="optional">opcional nesta etapa</span><input name="cpf_cnpj" maxlength="20" value="<?= e((string) ($profile['cpf_cnpj'] ?? '')) ?>"></label>
                <label>Cidade base<input name="city" maxlength="100" required value="<?= e((string) ($profile['city'] ?? '')) ?>"></label>
                <label>UF<input name="state" maxlength="2" required value="<?= e((string) ($profile['state'] ?? 'BA')) ?>"></label>
                <label>Raio de atendimento (km)<input type="number" min="1" max="300" name="service_radius_km" required value="<?= (int) ($profile['service_radius_km'] ?? 30) ?>"></label>
                <label class="form-span">Apresentação<textarea name="description" rows="5" maxlength="2000" placeholder="Conte sua experiência, diferenciais, tipos de atendimento e estrutura disponível."><?= e((string) ($profile['description'] ?? '')) ?></textarea></label>
                <label class="check-row form-span"><input type="checkbox" name="emergency_service" value="1" <?= !empty($profile['emergency_service']) ? 'checked' : '' ?>><span><strong>Atendo chamados emergenciais</strong><br>Permite participar do futuro modo “Preciso Agora”.</span></label>
            </div>
        </section>

        <section class="editor-card">
            <h2>Categorias de serviço</h2>
            <p class="editor-help">Selecione tudo que sua empresa realmente executa. Isso será usado para encontrar oportunidades compatíveis.</p>
            <div class="checkbox-grid">
                <?php foreach ($categories as $category): ?>
                    <label class="choice-check"><input type="checkbox" name="categories[]" value="<?= (int) $category['id'] ?>" <?= in_array((int) $category['id'], $selectedCategories, true) ? 'checked' : '' ?>><span><?= e($category['name']) ?></span></label>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="editor-card">
            <h2>Regiões atendidas</h2>
            <p class="editor-help">Informe uma cidade ou localidade por linha no formato <strong>Cidade/UF</strong>. Ex.: Guarajuba/BA, Praia do Forte/BA, Camaçari/BA.</p>
            <textarea class="regions-field" name="regions" rows="7" required placeholder="Guarajuba/BA&#10;Praia do Forte/BA&#10;Camaçari/BA"><?= e($regionsText) ?></textarea>
        </section>

        <div class="form-actions"><button class="button button-primary" type="submit">Salvar perfil de serviços</button><a href="painel.php">Voltar ao painel</a></div>
    </form>

    <section class="editor-card portfolio-section" id="portfolio">
        <div class="section-row"><div><span class="badge">PORTFÓLIO</span><h2>Mostre trabalhos já realizados</h2><p class="editor-help">Nesta primeira versão, a foto pode ser vinculada por URL. Upload direto será adicionado junto com o módulo de documentos.</p></div></div>
        <form method="post" class="form-grid portfolio-form">
            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="add_portfolio">
            <label>Título do trabalho<input name="portfolio_title" maxlength="160" required placeholder="Ex.: Reforma do quadro elétrico"></label>
            <label>URL da foto <span class="optional">opcional</span><input type="url" name="image_url" maxlength="500" placeholder="https://..."></label>
            <label class="form-span">Descrição <span class="optional">opcional</span><textarea name="portfolio_description" rows="3" maxlength="1500" placeholder="Descreva o serviço executado e o resultado."></textarea></label>
            <div class="form-span"><button class="button button-secondary" type="submit">Adicionar ao portfólio</button></div>
        </form>

        <?php if ($portfolio): ?><div class="portfolio-grid"><?php foreach ($portfolio as $item): ?>
            <article class="portfolio-card">
                <?php if (!empty($item['image_url'])): ?><img src="<?= e($item['image_url']) ?>" alt="<?= e($item['title']) ?>" loading="lazy" referrerpolicy="no-referrer"><?php else: ?><div class="portfolio-placeholder">📷</div><?php endif; ?>
                <div class="portfolio-body"><h3><?= e($item['title']) ?></h3><?php if (!empty($item['description'])): ?><p><?= nl2br(e($item['description'])) ?></p><?php endif; ?>
                    <form method="post" onsubmit="return confirm('Remover este item do portfólio?');"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="delete_portfolio"><input type="hidden" name="portfolio_id" value="<?= (int) $item['id'] ?>"><button class="link-danger" type="submit">Remover</button></form>
                </div>
            </article>
        <?php endforeach; ?></div><?php else: ?><div class="empty-state">Seu portfólio ainda está vazio. Adicione o primeiro trabalho realizado.</div><?php endif; ?>
    </section>
</main>
</body>
</html>
