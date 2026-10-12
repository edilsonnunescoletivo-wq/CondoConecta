<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Marketplace;

$user = Auth::requireAuth();
$pdo = Database::connection($config);
$photoId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT p.*, sr.contractor_user_id, sr.id AS request_id
                       FROM service_request_photos p
                       INNER JOIN service_requests sr ON sr.id = p.service_request_id
                       WHERE p.id = :id LIMIT 1");
$stmt->execute(['id' => $photoId]);
$photo = $stmt->fetch();
if (!$photo) {
    http_response_code(404);
    exit;
}

$allowed = false;
if (($user['role'] ?? '') === 'admin') {
    $allowed = true;
} elseif (($user['role'] ?? '') === 'contratante' && (int) $photo['contractor_user_id'] === (int) $user['id']) {
    $allowed = true;
} elseif (($user['role'] ?? '') === 'fornecedor') {
    $proposalStmt = $pdo->prepare('SELECT 1 FROM proposals WHERE service_request_id = :request_id AND supplier_user_id = :supplier_user_id LIMIT 1');
    $proposalStmt->execute(['request_id' => $photo['request_id'], 'supplier_user_id' => $user['id']]);
    $allowed = (bool) $proposalStmt->fetchColumn() || Marketplace::supplierCanAccessRequest($pdo, (int) $user['id'], (int) $photo['request_id']);
}

if (!$allowed) {
    http_response_code(403);
    exit;
}

$file = dirname(__DIR__) . '/storage/request-photos/' . basename((string) $photo['file_path']);
if (!is_file($file)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . (string) $photo['mime_type']);
header('Content-Length: ' . (string) filesize($file));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($file);
exit;
