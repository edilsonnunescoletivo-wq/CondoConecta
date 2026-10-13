<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$envFile = $root . '/.env';

if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

$autoload = $root . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class) use ($root): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file = $root . '/app/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

$config = require $root . '/config/app.php';

$secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name(getenv('SESSION_NAME') ?: 'condoconecta_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

$currentScript = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

// Normaliza valores monetários enviados na proposta antes do processamento da tela.
// Aceita formatos brasileiros (1.234,56) e internacionais (1234.56) sem alterar
// a compatibilidade com o processamento legado da solicitação.
if ($currentScript === 'solicitacao.php' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && array_key_exists('amount', $_POST)) {
    $normalizedAmount = \App\Core\Money::parse((string) $_POST['amount']);
    if ($normalizedAmount !== null) {
        $_POST['amount'] = number_format($normalizedAmount, 2, ',', '');
    }
}

// Rotas comerciais que exigem teste ou assinatura válida do contratante.
if ($currentScript === 'nova-solicitacao.php' && \App\Core\Auth::check()) {
    $sessionUser = \App\Core\Auth::user();
    if (($sessionUser['role'] ?? '') === 'contratante' && ($sessionUser['terms_version'] ?? null) === \App\Core\Terms::VERSION) {
        try {
            $subscriptionPdo = \App\Core\Database::connection($config);
            [$subscriptionAllowed] = \App\Core\Subscription::canCreateRequest($subscriptionPdo, (int) $sessionUser['id']);
            if (!$subscriptionAllowed) {
                redirect('planos.php?bloqueio=1');
            }
        } catch (\Throwable $exception) {
            // Antes da migração comercial, não derruba a aplicação.
        }
    }
}
