<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Subscription;

$user = Auth::requireAuth();
if (($user['role'] ?? '') !== 'contratante') redirect('painel.php');
$pdo = Database::connection($config);
$profileStmt = $pdo->prepare('SELECT account_type FROM contractor_profiles WHERE user_id=:user_id LIMIT 1');
$profileStmt->execute(['user_id'=>$user['id']]);
$accountType = (string)($profileStmt->fetchColumn() ?: 'condominio');
$current = Subscription::current($pdo, (int)$user['id']) ?? Subscription::ensureTrial($pdo, (int)$user['id']);
$audience = $accountType === 'administradora' ? 'administradora' : 'condominio';
$stmt = $pdo->prepare("SELECT * FROM plans WHERE active=1 AND (audience=:audience OR audience='todos') ORDER BY monthly_price");
$stmt->execute(['audience'=>$audience]);
$plans = $stmt->fetchAll();
$statusLabels=['trial'=>'Período de teste','ativa'=>'Ativa','inadimplente'=>'Pagamento pendente','cancelada'=>'Cancelada'];
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Planos — CondoConecta</title><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="dashboard-page"><header class="topbar"><div class="container nav"><a class="brand" href="painel.php">Condo<span>Conecta</span></a><nav><a class="button button-ghost" href="painel.php">Painel</a></nav></div></header>
<main class="container dashboard-shell"><section class="page-heading"><span class="badge">PLANOS</span><h1>Escolha o plano do contratante</h1><p>Fornecedores continuam com cadastro e envio de propostas gratuitos. Os planos abaixo são para condomínios, síndicos, empresas e administradoras.</p></section>
<div class="alert alert-success"><strong>Status atual:</strong> <?=e($statusLabels[$current['status']] ?? $current['status'])?> · <?=e((string)$current['plan_name'])?><?php if(($current['status']??'')==='trial' && !empty($current['trial_ends_at'])):?> · teste até <?=e(date('d/m/Y',strtotime((string)$current['trial_ends_at'])))?><?php endif;?></div>
<section class="dashboard-grid"><?php foreach($plans as $plan):?><article class="dash-card <?=($plan['slug']??'')==='profissional'?'dash-primary':''?>"><span class="badge"><?=e(strtoupper((string)$plan['name']))?></span><h2><?=e((string)$plan['name'])?></h2><strong class="dash-number">R$ <?=e(number_format((float)$plan['monthly_price'],2,',','.'))?><small>/mês</small></strong><p><?= $plan['monthly_request_limit']===null ? 'Solicitações mensais sem limite configurado.' : 'Até '.(int)$plan['monthly_request_limit'].' novas solicitações por mês.' ?></p><p><?= (int)$plan['trial_days'] ?> dias de teste para novas contas elegíveis.</p><a class="button button-primary button-full" href="assinar.php?plano=<?=urlencode((string)$plan['slug'])?>">Assinar este plano</a></article><?php endforeach;?></section>
<p class="muted">A cobrança recorrente é processada pelo parceiro de pagamentos. O acesso pago só é ativado após confirmação do status da assinatura.</p></main></body></html>
