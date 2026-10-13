<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;

$appName = (string) ($config['name'] ?? 'CondoConecta');
$categories = [
    'Elétrica', 'Hidráulica', 'Bombas e motores', 'Piscinas', 'Elevadores',
    'Geradores', 'Portões e acesso', 'CFTV', 'Jardinagem', 'Pintura',
    'Impermeabilização', 'Extintores e AVCB', 'Dedetização', 'Limpeza de reservatórios'
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="CondoConecta conecta condomínios e administradoras a fornecedores locais qualificados.">
    <title><?= e($appName) ?> — fornecedores para condomínios</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<header class="topbar">
    <div class="container nav">
        <a class="brand" href="#inicio">Condo<span>Conecta</span></a>
        <nav aria-label="Navegação principal">
            <a href="#como-funciona">Como funciona</a>
            <a href="#categorias">Serviços</a>
            <a class="button button-ghost" href="<?= Auth::check() ? 'painel.php' : 'login.php' ?>"><?= Auth::check() ? 'Meu painel' : 'Entrar' ?></a>
        </nav>
    </div>
</header>

<main id="inicio">
    <section class="hero">
        <div class="container hero-grid">
            <div>
                <p class="eyebrow">Marketplace regional para condomínios</p>
                <h1>Encontre o fornecedor certo. <span>Mais perto, mais rápido e com histórico.</span></h1>
                <p class="hero-copy">Solicite orçamentos, compare propostas e contrate fornecedores da região em um único lugar. Para o fornecedor, o cadastro e o envio de propostas são gratuitos.</p>
                <div class="hero-actions">
                    <a class="button button-primary" href="cadastro.php?perfil=contratante">Quero contratar serviços</a>
                    <a class="button button-secondary" href="cadastro.php?perfil=fornecedor">Quero oferecer serviços</a>
                </div>
                <div class="trust-row">
                    <span>✓ Fornecedores regionais</span>
                    <span>✓ Avaliações de contratações reais</span>
                    <span>✓ Cotações organizadas</span>
                </div>
            </div>
            <aside class="request-card" aria-label="Exemplo de solicitação">
                <div class="request-top">
                    <span class="urgent">PRECISO AGORA</span>
                    <span>Guarajuba • BA</span>
                </div>
                <h2>Manutenção de bomba d'água</h2>
                <p>Equipamento parou de funcionar e precisamos de atendimento emergencial.</p>
                <div class="supplier-result">
                    <strong>8 fornecedores disponíveis</strong>
                    <span>na região e categoria selecionadas</span>
                </div>
                <a class="button button-primary button-full" href="cadastro.php?perfil=contratante">Solicitar propostas</a>
            </aside>
        </div>
    </section>

    <section id="como-funciona" class="section">
        <div class="container">
            <p class="eyebrow">Fluxo simples</p>
            <h2 class="section-title">Da necessidade à contratação</h2>
            <div class="steps">
                <article><span>1</span><h3>Publique a necessidade</h3><p>Informe o serviço, local, prazo, urgência, fotos e detalhes importantes.</p></article>
                <article><span>2</span><h3>Receba propostas</h3><p>Fornecedores compatíveis com a categoria e região podem enviar orçamento gratuitamente.</p></article>
                <article><span>3</span><h3>Compare e contrate</h3><p>Analise preço, prazo, garantia, avaliações e documentação antes de decidir.</p></article>
            </div>
        </div>
    </section>

    <section id="categorias" class="section section-soft">
        <div class="container">
            <p class="eyebrow">Categorias iniciais</p>
            <h2 class="section-title">Serviços que um condomínio precisa no dia a dia</h2>
            <div class="category-grid">
                <?php foreach ($categories as $category): ?>
                    <div class="category-chip"><?= e($category) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="cadastro" class="section">
        <div class="container audience-grid">
            <article class="audience-card">
                <span class="badge">FORNECEDOR</span>
                <h2>Divulgue seus serviços gratuitamente</h2>
                <p>Crie seu perfil, escolha as categorias e regiões atendidas, receba oportunidades e envie propostas sem pagar por lead.</p>
                <a class="button button-secondary" href="cadastro.php?perfil=fornecedor">Quero me cadastrar grátis</a>
            </article>
            <article class="audience-card audience-highlight">
                <span class="badge">CONDOMÍNIO / ADMINISTRADORA</span>
                <h2>Centralize suas compras e contratações</h2>
                <p>Tenha cotações, propostas, histórico, avaliações e documentos organizados para decidir com mais segurança.</p>
                <a class="button button-primary" href="cadastro.php?perfil=contratante">Criar conta de contratante</a>
            </article>
        </div>
    </section>
</main>

<footer>
    <div class="container footer-row">
        <strong>CondoConecta</strong>
        <span>Conectando condomínios a bons fornecedores.</span>
    </div>
</footer>
</body>
</html>
