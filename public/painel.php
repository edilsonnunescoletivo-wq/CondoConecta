<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Marketplace;
use App\Core\Verification;
use Throwable;

$user = Auth::requireAuth();
$profile = null;
$dbWarning = null;
$supplierStats = ['categories' => 0, 'regions' => 0, 'portfolio' => 0, 'opportunities' => 0, 'proposals' => 0, 'hirings' => 0, 'rating_count' => 0, 'rating' => null, 'documents' => 0, 'pending_documents' => 0];
$contractorStats = ['open_requests' => 0, 'proposals' => 0, 'hirings' => 0];

try {
    $pdo = Database::connection($config);
    if ($user['role'] === 'fornecedor') {
        Verification::refreshSeal($pdo, (int) $user['id']);
        $stmt = $pdo->prepare('SELECT id, trade_name AS display_name, description, city, state, service_radius_km, emergency_service, verified_at FROM supplier_profiles WHERE user_id = :user_id LIMIT 1');
    } elseif ($user['role'] === 'contratante') {
        $stmt = $pdo->prepare('SELECT display_name, city, state, account_type FROM contractor_profiles WHERE user_id = :user_id LIMIT 1');
    } else {
        $stmt = null;
    }

    if ($stmt) {
        $stmt->execute(['user_id' => $user['id']]);
        $profile = $stmt->fetch() ?: null;
    }

    if ($user['role'] === 'fornecedor' && $profile) {
        $supplierId = (int) $profile['id'];
        $countCategories = $pdo->prepare('SELECT COUNT(*) FROM supplier_categories WHERE supplier_id = :supplier_id');
        $countCategories->execute(['supplier_id' => $supplierId]);
        $supplierStats['categories'] = (int) $countCategories->fetchColumn();

        $countRegions = $pdo->prepare('SELECT COUNT(*) FROM supplier_regions WHERE supplier_id = :supplier_id');
        $countRegions->execute(['supplier_id' => $supplierId]);
        $supplierStats['regions'] = (int) $countRegions->fetchColumn();

        try {
            $countPortfolio = $pdo->prepare('SELECT COUNT(*) FROM supplier_portfolio WHERE supplier_id = :supplier_id AND active = 1');
            $countPortfolio->execute(['supplier_id' => $supplierId]);
            $supplierStats['portfolio'] = (int) $countPortfolio->fetchColumn();
        } catch (Throwable $exception) {
            $supplierStats['portfolio'] = 0;
        }

        $supplierStats['opportunities'] = Marketplace::supplierOpportunityCount($pdo, (int) $user['id']);
        $proposalCount = $pdo->prepare("SELECT COUNT(*) FROM proposals WHERE supplier_user_id = :user_id AND status <> 'retirada'");
        $proposalCount->execute(['user_id' => $user['id']]);
        $supplierStats['proposals'] = (int) $proposalCount->fetchColumn();

        $hiringCount = $pdo->prepare('SELECT COUNT(*) FROM hirings WHERE supplier_user_id = :user_id');
        $hiringCount->execute(['user_id' => $user['id']]);
        $supplierStats['hirings'] = (int) $hiringCount->fetchColumn();

        $ratingStmt = $pdo->prepare('SELECT COUNT(*) AS total, AVG((quality_rating + deadline_rating + service_rating + value_rating) / 4) AS average_rating FROM reviews WHERE supplier_user_id = :user_id');
        $ratingStmt->execute(['user_id' => $user['id']]);
        $ratingData = $ratingStmt->fetch();
        $supplierStats['rating_count'] = (int) ($ratingData['total'] ?? 0);
        $supplierStats['rating'] = $ratingData && $ratingData['average_rating'] !== null ? (float) $ratingData['average_rating'] : null;

        $docStats = $pdo->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN status='pendente' THEN 1 ELSE 0 END) AS pending FROM supplier_documents WHERE supplier_user_id = :user_id");
        $docStats->execute(['user_id' => $user['id']]);
        $docData = $docStats->fetch() ?: [];
        $supplierStats['documents'] = (int) ($docData['total'] ?? 0);
        $supplierStats['pending_documents'] = (int) ($docData['pending'] ?? 0);
    }

    if ($user['role'] === 'contratante') {
        $openCount = $pdo->prepare("SELECT COUNT(*) FROM service_requests WHERE contractor_user_id = :user_id AND status IN ('aberta','em_cotacao')");
        $openCount->execute(['user_id' => $user['id']]);
        $contractorStats['open_requests'] = (int) $openCount->fetchColumn();

        $proposalCount = $pdo->prepare("SELECT COUNT(*) FROM proposals p INNER JOIN service_requests sr ON sr.id = p.service_request_id WHERE sr.contractor_user_id = :user_id AND p.status <> 'retirada'");
        $proposalCount->execute(['user_id' => $user['id']]);
        $contractorStats['proposals'] = (int) $proposalCount->fetchColumn();

        $hiringCount = $pdo->prepare('SELECT COUNT(*) FROM hirings WHERE contractor_user_id = :user_id');
        $hiringCount->execute(['user_id' => $user['id']]);
        $contractorStats['hirings'] = (int) $hiringCount->fetchColumn();
    }
} catch (Throwable $exception) {
    $dbWarning = 'O painel abriu, mas alguns dados não puderam ser carregados. Verifique se todas as migrações do banco foram aplicadas.';
}

$isSupplier = $user['role'] === 'fornecedor';
$displayName = (string) ($profile['display_name'] ?? $user['name']);
$location = $profile ? trim((string) ($profile['city'] ?? '') . ' • ' . (string) ($profile['state'] ?? ''), ' •') : '';
$supplierProfileComplete = $isSupplier && $profile && $supplierStats['categories'] > 0 && $supplierStats['regions'] > 0 && trim((string) ($profile['description'] ?? '')) !== '';
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Painel — <?= e((string) ($config['name'] ?? 'CondoConecta')) ?></title><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="dashboard-page">
<header class="topbar"><div class="container nav"><a class="brand" href="index.php">Condo<span>Conecta</span></a><nav class="dashboard-nav"><span class="nav-user"><?= e($user['name']) ?></span><form method="post" action="logout.php"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="button button-ghost" type="submit">Sair</button></form></nav></div></header>
<main class="container dashboard-shell">
    <?php if (isset($_GET['novo'])): ?><div class="alert alert-success">Conta criada com sucesso. Bem-vindo ao CondoConecta!</div><?php endif; ?>
    <?php if ($dbWarning): ?><div class="alert alert-error"><?= e($dbWarning) ?></div><?php endif; ?>
    <section class="dashboard-hero"><div><span class="badge"><?= $isSupplier ? 'FORNECEDOR' : ($user['role'] === 'contratante' ? 'CONTRATANTE' : 'ADMINISTRADOR') ?></span><h1>Olá, <?= e($displayName) ?>.</h1><p><?= $isSupplier ? 'Seu espaço para receber oportunidades e enviar propostas.' : ($user['role'] === 'contratante' ? 'Seu espaço para solicitar serviços, comparar propostas e acompanhar contratações.' : 'Administre a confiança e a operação da plataforma.') ?></p><?php if ($location): ?><span class="location-pill">📍 <?= e($location) ?></span><?php endif; ?></div><?php if ($isSupplier): ?><div class="account-status"><strong>Cadastro gratuito</strong><span>Sem cobrança por lead ou proposta no MVP</span><?php if ($supplierProfileComplete): ?><span class="status-tag status-ok">Perfil de serviços configurado</span><?php else: ?><span class="status-tag status-pending">Complete seu perfil para receber oportunidades</span><?php endif; ?></div><?php endif; ?></section>

    <?php if ($isSupplier): ?>
        <section class="dashboard-grid">
            <article class="dash-card dash-primary"><span class="dash-icon">⚡</span><h2>Oportunidades</h2><strong class="dash-number"><?= $supplierStats['opportunities'] ?></strong><p>Solicitações compatíveis com suas categorias e regiões.</p><a class="button button-primary" href="oportunidades.php">Ver oportunidades</a></article>
            <article class="dash-card"><span class="dash-icon">📄</span><h2>Minhas propostas</h2><strong class="dash-number"><?= $supplierStats['proposals'] ?></strong><p>Acompanhe propostas em análise e resultados.</p><a class="button button-secondary" href="minhas-propostas.php">Abrir propostas</a></article>
            <article class="dash-card"><span class="dash-icon">🤝</span><h2>Contratações</h2><strong class="dash-number"><?= $supplierStats['hirings'] ?></strong><p>Acompanhe os serviços em que sua proposta foi escolhida.</p><a class="button button-secondary" href="minhas-contratacoes.php">Abrir contratações</a></article>
            <article class="dash-card"><span class="dash-icon">🧰</span><h2>Perfil de serviços</h2><p>Defina categorias, regiões atendidas, apresentação e portfólio.</p><div class="profile-progress"><span><?= $supplierStats['categories'] ?> categoria(s)</span><span>•</span><span><?= $supplierStats['regions'] ?> região(ões)</span><span>•</span><span><?= $supplierStats['portfolio'] ?> trabalho(s)</span></div><div class="card-actions"><a class="button button-secondary" href="perfil-fornecedor.php">Configurar perfil</a><?php if ($profile): ?><a class="button button-ghost" href="fornecedor.php?id=<?= (int) $profile['id'] ?>" target="_blank" rel="noopener">Ver público</a><?php endif; ?></div></article>
            <article class="dash-card"><span class="dash-icon">✅</span><h2>Fornecedor Verificado</h2><p><?= $supplierStats['documents'] ?> documento(s) enviado(s)<?= $supplierStats['pending_documents'] ? ' · '.$supplierStats['pending_documents'].' pendente(s)' : '' ?>.</p><span class="status-tag <?= !empty($profile['verified_at']) ? 'status-ok' : 'status-pending' ?>"><?= !empty($profile['verified_at']) ? '✓ Verificado' : 'Não verificado' ?></span><div class="card-actions"><a class="button button-secondary" href="fornecedor-documentos.php">Gerenciar documentos</a></div></article>
            <article class="dash-card"><span class="dash-icon">🚨</span><h2>Preciso Agora</h2><p>Chamados emergenciais compatíveis aparecem em destaque nas oportunidades.</p><span class="status-tag <?= !empty($profile['emergency_service']) ? 'status-ok' : 'status-muted' ?>"><?= !empty($profile['emergency_service']) ? 'Ativado' : 'Desativado' ?></span><?php if ($profile): ?><div class="card-actions"><a class="button button-ghost" href="perfil-fornecedor.php">Alterar</a></div><?php endif; ?></article>
            <article class="dash-card"><span class="dash-icon">⭐</span><h2>Avaliações</h2><strong class="dash-number"><?= $supplierStats['rating'] !== null ? e(number_format((float) $supplierStats['rating'], 1, ',', '.')) : '—' ?></strong><p><?= $supplierStats['rating_count'] ?> avaliação(ões) de contratações concluídas.</p></article>
        </section>
    <?php elseif ($user['role'] === 'contratante'): ?>
        <section class="dashboard-grid">
            <article class="dash-card dash-primary"><span class="dash-icon">＋</span><h2>Nova solicitação</h2><p>Descreva o que precisa e receba propostas de fornecedores da região.</p><a class="button button-primary" href="nova-solicitacao.php">Criar solicitação</a></article>
            <article class="dash-card"><span class="dash-icon">📊</span><h2>Cotações abertas</h2><strong class="dash-number"><?= $contractorStats['open_requests'] ?></strong><p>Você recebeu <?= $contractorStats['proposals'] ?> proposta(s) no total.</p><a class="button button-secondary" href="minhas-solicitacoes.php">Acompanhar cotações</a></article>
            <article class="dash-card"><span class="dash-icon">🤝</span><h2>Contratações</h2><strong class="dash-number"><?= $contractorStats['hirings'] ?></strong><p>Acompanhe serviços contratados, execução e avaliações.</p><a class="button button-secondary" href="minhas-contratacoes.php">Abrir contratações</a></article>
            <article class="dash-card"><span class="dash-icon">🔎</span><h2>Fornecedores</h2><p>Os perfis dos fornecedores ficam acessíveis a partir das propostas recebidas.</p></article>
            <article class="dash-card"><span class="dash-icon">🚨</span><h2>Preciso Agora</h2><p>Publique uma emergência para fornecedores que habilitaram atendimento imediato.</p><a class="button button-secondary" href="nova-solicitacao.php?urgencia=agora">Criar chamado urgente</a></article>
            <article class="dash-card"><span class="dash-icon">💳</span><h2>Plano</h2><p>A gestão dos planos pagos será conectada posteriormente.</p><span class="status-tag status-muted">Configuração pendente</span></article>
        </section>
    <?php else: ?>
        <section class="dashboard-grid"><article class="dash-card dash-primary"><span class="dash-icon">✅</span><h2>Fornecedor Verificado</h2><p>Revise documentos, aprove ou rejeite arquivos e gerencie o selo público.</p><a class="button button-primary" href="admin-verificacao.php">Abrir fila de verificação</a></article><article class="dash-card"><span class="dash-icon">⚙️</span><h2>Administração</h2><p>Novos controles administrativos serão adicionados nas próximas fases.</p></article></section>
    <?php endif; ?>
</main></body></html>
