<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Notifications;

$user = Auth::requireAuth();
$pdo = Database::connection($config);
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect('notificacoes.php');
}

$stmt = $pdo->prepare('SELECT link FROM notifications WHERE id = :id AND user_id = :user_id LIMIT 1');
$stmt->execute(['id' => $id, 'user_id' => $user['id']]);
$notification = $stmt->fetch();
if (!$notification) {
    redirect('notificacoes.php');
}

Notifications::markRead($pdo, (int) $user['id'], $id);
$link = (string) ($notification['link'] ?? '');
if ($link !== '' && preg_match('/^[a-z0-9-]+\.php(?:\?[a-z0-9_=&%-]+)?$/i', $link)) {
    redirect($link);
}

redirect('notificacoes.php');
