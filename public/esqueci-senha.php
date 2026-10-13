<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Mailer;
use Throwable;

if (Auth::check()) {
    redirect('painel.php');
}

$email = trim((string) ($_POST['email'] ?? ''));
$error = null;
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        $error = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Informe um e-mail válido.';
    } else {
        try {
            $pdo = Database::connection($config);
            $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE email = :email AND active = 1 LIMIT 1');
            $stmt->execute(['email' => mb_strtolower($email)]);
            $account = $stmt->fetch();

            if ($account) {
                $limitStmt = $pdo->prepare('SELECT COUNT(*) FROM password_reset_tokens WHERE user_id = :user_id AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)');
                $limitStmt->execute(['user_id' => $account['id']]);
                $recent = (int) $limitStmt->fetchColumn();

                if ($recent < 3) {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);

                    $pdo->beginTransaction();
                    $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL')
                        ->execute(['user_id' => $account['id']]);
                    $insert = $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 30 MINUTE))');
                    $insert->execute(['user_id' => $account['id'], 'token_hash' => $tokenHash]);
                    $resetId = (int) $pdo->lastInsertId();
                    $pdo->commit();

                    $baseUrl = (string) ($config['url'] ?? '');
                    if ($baseUrl === '') {
                        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
                        $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
                        $baseUrl = ($https ? 'https' : 'http') . '://' . $host;
                    }
                    $link = rtrim($baseUrl, '/') . '/redefinir-senha.php?token=' . rawurlencode($token);
                    $name = htmlspecialchars((string) $account['name'], ENT_QUOTES, 'UTF-8');
                    $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
                    $html = '<h2>Redefinição de senha</h2>'
                        . '<p>Olá, ' . $name . '.</p>'
                        . '<p>Recebemos uma solicitação para redefinir sua senha no CondoConecta.</p>'
                        . '<p><a href="' . $safeLink . '">Criar nova senha</a></p>'
                        . '<p>Este link expira em 30 minutos e só pode ser usado uma vez.</p>'
                        . '<p>Se você não fez esta solicitação, ignore este e-mail.</p>';
                    $text = "Olá, {$account['name']}.\n\nUse o link abaixo para criar uma nova senha no CondoConecta:\n{$link}\n\nO link expira em 30 minutos e só pode ser usado uma vez.\nSe você não fez esta solicitação, ignore este e-mail.";

                    if (!Mailer::send($config, (string) $account['email'], 'Redefinição de senha — CondoConecta', $html, $text)) {
                        $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id')->execute(['id' => $resetId]);
                        error_log('CondoConecta: falha ao enviar e-mail de recuperação para user_id=' . (int) $account['id']);
                    }
                }
            }

            $sent = true;
            $email = '';
            Csrf::rotate();
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('CondoConecta password reset request: ' . $exception->getMessage());
            $sent = true;
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar senha — <?= e((string) ($config['name'] ?? 'CondoConecta')) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-page">
<header class="topbar"><div class="container nav"><a class="brand" href="index.php">Condo<span>Conecta</span></a><nav><a href="login.php">Entrar</a></nav></div></header>
<main class="auth-shell container">
    <section class="auth-card">
        <div class="auth-heading"><p class="eyebrow">RECUPERAÇÃO</p><h1>Esqueceu sua senha?</h1><p>Informe o e-mail da conta para receber um link de redefinição.</p></div>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($sent): ?><div class="alert alert-success">Se existir uma conta ativa com esse e-mail, enviaremos as instruções de recuperação. Verifique também a caixa de spam.</div><?php endif; ?>
        <form method="post" class="stack-form">
            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
            <label>E-mail<input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required autofocus></label>
            <button class="button button-primary button-full" type="submit">Enviar link de recuperação</button>
        </form>
        <p class="auth-foot"><a href="login.php">Voltar para o login</a></p>
    </section>
</main>
</body>
</html>
