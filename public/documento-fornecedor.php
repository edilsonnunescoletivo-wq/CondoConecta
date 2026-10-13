<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;

$user = Auth::requireAuth();
$documentId = (int) ($_GET['id'] ?? 0);
if ($documentId <= 0) {
    http_response_code(404);
    exit('Documento não encontrado.');
}

$pdo = Database::connection($config);
$stmt = $pdo->prepare('SELECT id, supplier_user_id, file_path, original_name, mime_type FROM supplier_documents WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $documentId]);
$document = $stmt->fetch();
if (!$document) {
    http_response_code(404);
    exit('Documento não encontrado.');
}

$isOwner = ($user['role'] ?? '') === 'fornecedor' && (int) $document['supplier_user_id'] === (int) $user['id'];
$isAdmin = ($user['role'] ?? '') === 'admin';
if (!$isOwner && !$isAdmin) {
    http_response_code(403);
    exit('Acesso negado.');
}

$baseDir = realpath(dirname(__DIR__) . '/storage/supplier-documents');
$path = $baseDir ? realpath($baseDir . '/' . basename((string) $document['file_path'])) : false;
if (!$baseDir || !$path || !str_starts_with($path, $baseDir . DIRECTORY_SEPARATOR) || !is_file($path)) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

$mime = (string) ($document['mime_type'] ?: 'application/octet-stream');
$name = preg_replace('/[^\pL\pN._ -]+/u', '_', (string) ($document['original_name'] ?: 'documento')) ?: 'documento';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $name) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($path);
exit;
