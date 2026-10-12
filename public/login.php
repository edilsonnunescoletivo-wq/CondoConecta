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

$error = null;
$email = trim((string) ($_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        $error = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        try {
            $pdo = Database::connection($config);
            if (Auth::attempt($pdo, $email, $password)) {
                Csrf::rotate();
                redirect('painel.php');
            }
            $error = 'E-mail ou senha inválidos.';
        } catch (Throwable $exception) {
            $error = 'Não foi possível entrar agora. Tente novamente.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar — <?= e((string) ($config['name'] ?? 'CondoConecta')) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-page">
<header class="topbar"><div class="container nav"><a class="brand" href="index.php">Condo<span>Conecta</span></a><nav><a href="cadastro.php">Criar conta</a></nav></div></header>
<main class="auth-shell container">
    <section class="auth-card">
        <div class="auth-heading"><p class="eyebrow">Acesso</p><h1>Entre na sua conta</h1><p>Acesse oportunidades, cotações e contratações do seu perfil.</p></div>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="stack-form">
            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
            <label>E-mail<input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required autofocus></label>
            <label>Senha<input type="password" name="password" autocomplete="current-password" required></label>
            <button class="button button-primary button-full" type="submit">Entrar</button>
        </form>
        <p class="auth-foot">Ainda não tem conta? <a href="cadastro.php">Cadastre-se aqui.</a></p>
    </section>
</main>
</body>
</html>
