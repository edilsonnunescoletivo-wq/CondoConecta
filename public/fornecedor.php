<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use Throwable;

$supplierId = (int) ($_GET['id'] ?? 0);
if ($supplierId <= 0) {
    http_response_code(404);
    exit('Fornecedor não encontrado.');
}

$pdo = Database::connection($config);
$stmt = $pdo->prepare("SELECT sp.id, sp.trade_name, sp.description, sp.city, sp.state, sp.service_radius_km, sp.emergency_service, sp.verified_at, u.name
    FROM supplier_profiles sp
    INNER JOIN users u ON u.id = sp.user_id
    WHERE sp.id = :id AND u.active = 1
    LIMIT 1");
$stmt->execute(['id' => $supplierId]);
$supplier = $stmt->fetch();

if (!$supplier) {
    http_response_code(404);
    exit('Fornecedor não encontrado.');
}

$categoryStmt = $pdo->prepare('SELECT c.name FROM supplier_categories sc INNER JOIN categories c ON c.id = sc.category_id WHERE sc.supplier_id = :supplier_id AND c.active = 1 ORDER BY c.name');
$categoryStmt->execute(['supplier_id' => $supplierId]);
$categories = array_column($categoryStmt->fetchAll(), 'name');

$regionStmt = $pdo->prepare('SELECT city, state FROM supplier_regions WHERE supplier_id = :supplier_id ORDER BY state, city');
$regionStmt->execute(['supplier_id' => $supplierId]);
$regions = $regionStmt->fetchAll();

$portfolio = [];
try {
    $portfolioStmt = $pdo->prepare('SELECT title, description, image_url FROM supplier_portfolio WHERE supplier_id = :supplier_id AND active = 1 ORDER BY sort_order, id DESC');
    $portfolioStmt->execute(['supplier_id' => $supplierId]);
    $portfolio = $portfolioStmt->fetchAll();
} catch (Throwable $exception) {
    // Perfil continua acessível mesmo antes da migração do portfólio.
}

$ratingStmt = $pdo->prepare('SELECT COUNT(*) AS total, AVG((quality_rating + deadline_rating + service_rating + value_rating) / 4) AS average_rating FROM reviews r INNER JOIN supplier_profiles sp ON sp.user_id = r.supplier_user_id WHERE sp.id = :supplier_id');
$ratingStmt->execute(['supplier_id' => $supplierId]);
$rating = $ratingStmt->fetch() ?: ['total' => 0, 'average_rating' => null];
$ratingTotal = (int) ($rating['total'] ?? 0);
$ratingAverage = $ratingTotal > 0 ? number_format((float) $rating['average_rating'], 1, ',', '.') : null;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Perfil de fornecedor no CondoConecta.">
    <title><?= e((string) $supplier['trade_name']) ?> — CondoConecta</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="public-profile-page">
<header class="topbar"><div class="container nav"><a class="brand" href="index.php">Condo<span>Conecta</span></a><nav><a href="index.php#como-funciona">Como funciona</a><a class="button button-ghost" href="login.php">Entrar</a></nav></div></header>

<main class="container public-profile-shell">
    <section class="supplier-header">
        <div class="supplier-avatar"><?= e(strtoupper(substr((string) $supplier['trade_name'], 0, 1))) ?></div>
        <div class="supplier-heading">
            <div class="profile-badges"><span class="badge">FORNECEDOR</span><?php if (!empty($supplier['verified_at'])): ?><span class="status-tag status-ok">✓ Verificado</span><?php endif; ?><?php if (!empty($supplier['emergency_service'])): ?><span class="status-tag urgent-tag">🚨 Preciso Agora</span><?php endif; ?></div>
            <h1><?= e($supplier['trade_name']) ?></h1>
            <p class="supplier-location">📍 <?= e($supplier['city']) ?> • <?= e($supplier['state']) ?> · atende em um raio de até <?= (int) $supplier['service_radius_km'] ?> km</p>
            <div class="rating-line"><?php if ($ratingTotal > 0): ?><strong>⭐ <?= e($ratingAverage) ?></strong><span><?= $ratingTotal ?> avaliação<?= $ratingTotal === 1 ? '' : 'ões' ?> de contratações concluídas</span><?php else: ?><span>⭐ Ainda sem avaliações na plataforma</span><?php endif; ?></div>
        </div>
    </section>

    <div class="public-profile-grid">
        <div class="public-profile-main">
            <section class="editor-card"><h2>Sobre</h2><p class="profile-description"><?= !empty($supplier['description']) ? nl2br(e($supplier['description'])) : 'Este fornecedor ainda não adicionou uma apresentação.' ?></p></section>

            <section class="editor-card"><h2>Serviços oferecidos</h2><?php if ($categories): ?><div class="service-tags"><?php foreach ($categories as $category): ?><span><?= e($category) ?></span><?php endforeach; ?></div><?php else: ?><div class="empty-state">Categorias ainda não configuradas.</div><?php endif; ?></section>

            <section class="editor-card"><h2>Portfólio</h2><?php if ($portfolio): ?><div class="portfolio-grid"><?php foreach ($portfolio as $item): ?><article class="portfolio-card"><?php if (!empty($item['image_url'])): ?><img src="<?= e($item['image_url']) ?>" alt="<?= e($item['title']) ?>" loading="lazy" referrerpolicy="no-referrer"><?php else: ?><div class="portfolio-placeholder">📷</div><?php endif; ?><div class="portfolio-body"><h3><?= e($item['title']) ?></h3><?php if (!empty($item['description'])): ?><p><?= nl2br(e($item['description'])) ?></p><?php endif; ?></div></article><?php endforeach; ?></div><?php else: ?><div class="empty-state">Portfólio ainda não publicado.</div><?php endif; ?></section>
        </div>

        <aside class="public-profile-aside">
            <section class="editor-card sticky-card"><h2>Área de atendimento</h2><?php if ($regions): ?><div class="region-list"><?php foreach ($regions as $region): ?><span>📍 <?= e($region['city']) ?>/<?= e($region['state']) ?></span><?php endforeach; ?></div><?php else: ?><p class="editor-help">Regiões ainda não configuradas.</p><?php endif; ?><hr><h3>Contato protegido</h3><p class="editor-help">O CondoConecta preserva telefone e e-mail nesta vitrine pública. O contato será liberado dentro do fluxo de solicitação e proposta.</p><a class="button button-primary button-full" href="login.php">Entrar para contratar</a></section>
        </aside>
    </div>
</main>
</body>
</html>
