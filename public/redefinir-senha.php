<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use Throwable;

if (Auth::check()) {
    redirect('painel.php');
}

$token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
$tokenValidFormat = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
$error = null;
$tokenRecord = null;

try {
    $pdo = Database::connection($config);
    if ($tokenValidFormat) {
        $lookup = $pdo->prepare("SELECT prt.id, prt.user_id, prt.expires_at
                                 FROM password_reset_tokens prt
                                 INNER JOIN users u ON u.id = prt.user_id AND u.active = 1
                                 WHERE prt.token_hash = :token_hash
                                   AND prt.used_at IS NULL
                                   AND prt.expires_at > NOW()
                                 LIMIT 1");
        $lookup->execute(['token_hash' => hash('sha256', $token)]);
        $tokenRecord = $lookup->fetch() ?: null;
    }
} catch (Throwable $exception) {
    $error = 'Não foi possível validar este link agora.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenRecord) {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        $error = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');

        if (strlen($password) < 8) {
            $error = 'A nova senha deve ter pelo menos 8 caracteres.';
        } elseif (strlen($password) > 255) {
            $error = 'A senha informada é muito longa.';
        } elseif (!hash_equals($password, $confirmation)) {
            $error = 'A confirmação da senha não confere.';
        } else {
            try {
                $pdo->beginTransaction();
                $lock = $pdo->prepare("SELECT id, user_id
                                       FROM password_reset_tokens
                                       WHERE id = :id
                                         AND token_hash = :token_hash
                                         AND used_at IS NULL
                                         AND expires_at > NOW()
                                       FOR UPDATE");
                $lock->execute([
                    'id' => $tokenRecord['id'],
                    'token_hash' => hash('sha256', $token),
                ]);
                $locked = $lock->fetch();

                if (!$locked) {
                    throw new RuntimeException('Token indisponível.');
                }

                $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :user_id')
                    ->execute([
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'user_id' => $locked['user_id'],
                    ]);
                $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL')
                    ->execute(['user_id' => $locked['user_id']]);
                $pdo->commit();

                Csrf::rotate();
                redirect('login.php?senha=alterada');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Este link expirou ou já foi utilizado. Solicite uma nova recuperação.';
                $tokenRecord = null;
            }
        }
    }
}

$linkUnavailable = !$tokenValidFormat || $tokenRecord === null;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nova senha — <?= e((string) ($config['name'] ?? 'CondoConecta')) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-page">
<header class="topbar"><div class="container nav"><a class="brand" href="index.php">Condo<span>Conecta</span></a><nav><a href="login.php">Entrar</a></nav></div></header>
<main class="auth-shell container">
    <section class="auth-card">
        <div class="auth-heading"><p class="eyebrow">SEGURANÇA</p><h1>Crie uma nova senha</h1><p>O link de recuperação é válido por 30 minutos e pode ser usado apenas uma vez.</p></div>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

        <?php if ($linkUnavailable): ?>
            <div class="alert alert-error">Este link é inválido, expirou ou já foi utilizado.</div>
            <a class="button button-primary button-full" href="esqueci-senha.php">Solicitar novo link</a>
        <?php else: ?>
            <form method="post" class="stack-form">
                <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <label>Nova senha<input type="password" name="password" minlength="8" maxlength="255" autocomplete="new-password" required autofocus></label>
                <label>Confirmar nova senha<input type="password" name="password_confirmation" minlength="8" maxlength="255" autocomplete="new-password" required></label>
                <button class="button button-primary button-full" type="submit">Salvar nova senha</button>
            </form>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
