<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use Throwable;

$user = Auth::requireAuth();
$profile = null;
$dbWarning = null;

try {
    $pdo = Database::connection($config);
    if ($user['role'] === 'fornecedor') {
        $stmt = $pdo->prepare('SELECT trade_name AS display_name, city, state, service_radius_km, emergency_service, verified_at FROM supplier_profiles WHERE user_id = :user_id LIMIT 1');
    } elseif ($user['role'] === 'contratante') {
        $stmt = $pdo->prepare('SELECT display_name, city, state, account_type FROM contractor_profiles WHERE user_id = :user_id LIMIT 1');
    } else {
        $stmt = null;
    }

    if ($stmt) {
        $stmt->execute(['user_id' => $user['id']]);
        $profile = $stmt->fetch() ?: null;
    }
} catch (Throwable $exception) {
    $dbWarning = 'O painel abriu, mas os dados complementares do perfil não puderam ser carregados.';
}

$isSupplier = $user['role'] === 'fornecedor';
$displayName = (string) ($profile['display_name'] ?? $user['name']);
$location = $profile ? trim((string) ($profile['city'] ?? '') . ' • ' . (string) ($profile['state'] ?? ''), ' •') : '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel — <?= e((string) ($config['name'] ?? 'CondoConecta')) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="dashboard-page">
<header class="topbar">
    <div class="container nav">
        <a class="brand" href="index.php">Condo<span>Conecta</span></a>
        <nav class="dashboard-nav">
            <span class="nav-user"><?= e($user['name']) ?></span>
            <form method="post" action="logout.php"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="button button-ghost" type="submit">Sair</button></form>
        </nav>
    </div>
</header>

<main class="container dashboard-shell">
    <?php if (isset($_GET['novo'])): ?><div class="alert alert-success">Conta criada com sucesso. Bem-vindo ao CondoConecta!</div><?php endif; ?>
    <?php if ($dbWarning): ?><div class="alert alert-error"><?= e($dbWarning) ?></div><?php endif; ?>

    <section class="dashboard-hero">
        <div>
            <span class="badge"><?= $isSupplier ? 'FORNECEDOR' : ($user['role'] === 'contratante' ? 'CONTRATANTE' : 'ADMINISTRADOR') ?></span>
            <h1>Olá, <?= e($displayName) ?>.</h1>
            <p><?= $isSupplier ? 'Seu espaço para receber oportunidades e enviar propostas.' : 'Seu espaço para solicitar serviços, comparar propostas e acompanhar contratações.' ?></p>
            <?php if ($location): ?><span class="location-pill">📍 <?= e($location) ?></span><?php endif; ?>
        </div>
        <?php if ($isSupplier): ?>
            <div class="account-status"><strong>Cadastro gratuito</strong><span>Sem cobrança por lead ou proposta no MVP</span></div>
        <?php endif; ?>
    </section>

    <?php if ($isSupplier): ?>
        <section class="dashboard-grid">
            <article class="dash-card dash-primary"><span class="dash-icon">⚡</span><h2>Oportunidades</h2><strong class="dash-number">0</strong><p>Serviços compatíveis com suas categorias e região aparecerão aqui.</p><button class="button button-primary" type="button" disabled>Ver oportunidades</button></article>
            <article class="dash-card"><span class="dash-icon">📄</span><h2>Minhas propostas</h2><strong class="dash-number">0</strong><p>Acompanhe propostas enviadas, aceitas e recusadas.</p></article>
            <article class="dash-card"><span class="dash-icon">🧰</span><h2>Perfil de serviços</h2><p>Próxima etapa: escolher categorias, regiões atendidas e adicionar portfólio.</p><span class="status-tag status-pending">Pendente de completar</span></article>
            <article class="dash-card"><span class="dash-icon">✅</span><h2>Verificação</h2><p>Envie documentos e aumente a confiança do seu perfil.</p><span class="status-tag <?= !empty($profile['verified_at']) ? 'status-ok' : 'status-pending' ?>"><?= !empty($profile['verified_at']) ? 'Verificado' : 'Não verificado' ?></span></article>
            <article class="dash-card"><span class="dash-icon">🚨</span><h2>Preciso Agora</h2><p>Receba chamados emergenciais próximos da sua área de atendimento.</p><span class="status-tag <?= !empty($profile['emergency_service']) ? 'status-ok' : 'status-muted' ?>"><?= !empty($profile['emergency_service']) ? 'Ativado' : 'Desativado' ?></span></article>
            <article class="dash-card"><span class="dash-icon">⭐</span><h2>Avaliações</h2><strong class="dash-number">—</strong><p>Somente contratações concluídas pela plataforma poderão gerar avaliação.</p></article>
        </section>
    <?php elseif ($user['role'] === 'contratante'): ?>
        <section class="dashboard-grid">
            <article class="dash-card dash-primary"><span class="dash-icon">＋</span><h2>Nova solicitação</h2><p>Descreva o que precisa e receba propostas de fornecedores da região.</p><button class="button button-primary" type="button" disabled>Criar solicitação</button></article>
            <article class="dash-card"><span class="dash-icon">📊</span><h2>Cotações abertas</h2><strong class="dash-number">0</strong><p>Acompanhe solicitações aguardando propostas.</p></article>
            <article class="dash-card"><span class="dash-icon">🤝</span><h2>Contratações</h2><strong class="dash-number">0</strong><p>Serviços contratados e histórico ficarão organizados aqui.</p></article>
            <article class="dash-card"><span class="dash-icon">🔎</span><h2>Fornecedores</h2><p>Pesquise fornecedores por categoria, localização e avaliação.</p></article>
            <article class="dash-card"><span class="dash-icon">🚨</span><h2>Preciso Agora</h2><p>Use para situações emergenciais e encontre fornecedores disponíveis na região.</p><span class="status-tag status-pending">Próxima fase</span></article>
            <article class="dash-card"><span class="dash-icon">💳</span><h2>Plano</h2><p>O cadastro está criado. A gestão dos planos pagos será conectada posteriormente.</p><span class="status-tag status-muted">Configuração pendente</span></article>
        </section>
    <?php else: ?>
        <section class="dashboard-grid"><article class="dash-card"><h2>Administração</h2><p>O painel administrativo será implementado em uma etapa específica.</p></article></section>
    <?php endif; ?>
</main>
</body>
</html>
