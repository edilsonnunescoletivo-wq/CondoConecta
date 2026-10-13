<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit;
}

$pdo = Database::connection($config);
$stmt = $pdo->prepare("SELECT image_path, image_mime
                       FROM supplier_portfolio
                       WHERE id = :id AND active = 1 AND image_path IS NOT NULL
                       LIMIT 1");
$stmt->execute(['id' => $id]);
$image = $stmt->fetch();

if (!$image) {
    http_response_code(404);
    exit;
}

$fileName = basename((string) $image['image_path']);
$path = dirname(__DIR__) . '/storage/portfolio-images/' . $fileName;
if ($fileName === '' || !is_file($path)) {
    http_response_code(404);
    exit;
}

$allowed = ['image/jpeg', 'image/png', 'image/webp'];
$mime = (string) ($image['image_mime'] ?? '');
if (!in_array($mime, $allowed, true)) {
    http_response_code(415);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($path);
