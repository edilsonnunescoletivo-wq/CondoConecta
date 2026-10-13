<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Core\MercadoPagoSubscriptions;
use Throwable;

header('Content-Type: application/json; charset=utf-8');
$signature = (string)($_SERVER['HTTP_X_SIGNATURE'] ?? '');
$requestId = (string)($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
$dataId = trim((string)($_GET['data.id'] ?? $_GET['data_id'] ?? ''));

if (!MercadoPagoSubscriptions::validateWebhook($signature, $requestId, $dataId)) {
    http_response_code(401);
    echo json_encode(['ok'=>false]);
    exit;
}

try {
    $mp = MercadoPagoSubscriptions::get($dataId);
    $externalId = (string)($mp['id'] ?? '');
    $status = (string)($mp['status'] ?? '');
    if ($externalId === '') throw new RuntimeException('Assinatura sem identificador.');

    $map = [
        'authorized' => 'ativa',
        'pending' => 'inadimplente',
        'paused' => 'inadimplente',
        'cancelled' => 'cancelada',
        'canceled' => 'cancelada',
    ];
    $localStatus = $map[$status] ?? 'inadimplente';
    $pdo = Database::connection($config);
    $stmt = $pdo->prepare('SELECT id, status FROM subscriptions WHERE external_subscription_id=:external_id LIMIT 1');
    $stmt->execute(['external_id'=>$externalId]);
    $subscription = $stmt->fetch();
    if ($subscription) {
        if ($localStatus === 'ativa') {
            $pdo->prepare("UPDATE subscriptions SET status='ativa', current_period_end=NULL WHERE id=:id")->execute(['id'=>$subscription['id']]);
        } elseif ($localStatus === 'cancelada') {
            $pdo->prepare("UPDATE subscriptions SET status='cancelada', canceled_at=CURRENT_TIMESTAMP WHERE id=:id")->execute(['id'=>$subscription['id']]);
        } elseif (($subscription['status'] ?? '') !== 'trial') {
            $pdo->prepare("UPDATE subscriptions SET status='inadimplente' WHERE id=:id")->execute(['id'=>$subscription['id']]);
        }
    }
    http_response_code(200);
    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false]);
}
