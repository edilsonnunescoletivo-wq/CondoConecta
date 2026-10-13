<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\MercadoPagoSubscriptions;
use App\Core\Subscription;
use Throwable;

$user = Auth::requireAuth();
if (($user['role'] ?? '') !== 'contratante') redirect('painel.php');
$pdo = Database::connection($config);
$slug = trim((string)($_GET['plano'] ?? ''));
$stmt = $pdo->prepare("SELECT * FROM plans WHERE slug=:slug AND active=1 LIMIT 1");
$stmt->execute(['slug'=>$slug]);
$plan = $stmt->fetch();
if (!$plan) redirect('planos.php');

$error = null;
try {
    if (!MercadoPagoSubscriptions::enabled()) throw new RuntimeException('A integração do Mercado Pago ainda não está configurada neste ambiente.');
    $current = Subscription::current($pdo, (int)$user['id']) ?? Subscription::ensureTrial($pdo, (int)$user['id']);
    $reference = 'CC-U'.(int)$user['id'].'-P'.(int)$plan['id'].'-'.time();
    $baseUrl = rtrim((string)($config['url'] ?? ''), '/');
    if ($baseUrl === '') throw new RuntimeException('APP_URL precisa estar configurada para iniciar a assinatura.');
    $mp = MercadoPagoSubscriptions::createPending((string)$user['email'], 'CondoConecta - Plano '.(string)$plan['name'], (float)$plan['monthly_price'], $reference, $baseUrl.'/planos.php?retorno=1');
    $externalId = (string)($mp['id'] ?? '');
    $initPoint = (string)($mp['init_point'] ?? '');
    if ($externalId === '' || $initPoint === '') throw new RuntimeException('O Mercado Pago não retornou o link de assinatura.');

    $pdo->prepare("UPDATE subscriptions SET plan_id=:plan_id, provider='mercadopago', external_reference=:reference, external_subscription_id=:external_id WHERE id=:id")
        ->execute(['plan_id'=>$plan['id'],'reference'=>$reference,'external_id'=>$externalId,'id'=>$current['id']]);
    redirect($initPoint);
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Assinatura — CondoConecta</title><link rel="stylesheet" href="assets/css/app.css"></head><body class="auth-page"><main class="auth-shell container"><section class="auth-card"><h1>Não foi possível iniciar a assinatura</h1><div class="alert alert-error"><?=e((string)$error)?></div><a class="button button-primary" href="planos.php">Voltar aos planos</a></section></main></body></html>
