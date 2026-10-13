<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use PDO;
use Throwable;

if (Auth::check()) {
    redirect('painel.php');
}

$allowedProfiles = ['fornecedor', 'contratante'];
$profile = (string) ($_POST['profile'] ?? $_GET['perfil'] ?? '');
if (!in_array($profile, $allowedProfiles, true)) {
    $profile = '';
}

$errors = [];
$values = [
    'name' => trim((string) ($_POST['name'] ?? '')),
    'email' => trim((string) ($_POST['email'] ?? '')),
    'phone' => trim((string) ($_POST['phone'] ?? '')),
    'display_name' => trim((string) ($_POST['display_name'] ?? '')),
    'legal_name' => trim((string) ($_POST['legal_name'] ?? '')),
    'cpf_cnpj' => trim((string) ($_POST['cpf_cnpj'] ?? '')),
    'city' => trim((string) ($_POST['city'] ?? '')),
    'state' => strtoupper(trim((string) ($_POST['state'] ?? 'BA'))),
    'account_type' => (string) ($_POST['account_type'] ?? 'condominio'),
    'description' => trim((string) ($_POST['description'] ?? '')),
    'service_radius_km' => (string) ($_POST['service_radius_km'] ?? '30'),
    'emergency_service' => isset($_POST['emergency_service']) ? '1' : '0',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    }

    if ($profile === '') {
        $errors[] = 'Escolha se você quer contratar ou oferecer serviços.';
    }

    if (mb_strlen($values['name']) < 3) {
        $errors[] = 'Informe seu nome completo.';
    }

    $email = mb_strtolower($values['email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Informe um e-mail válido.';
    }

    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
    if (strlen($password) < 8) {
        $errors[] = 'A senha deve ter pelo menos 8 caracteres.';
    }
    if ($password !== $passwordConfirmation) {
        $errors[] = 'A confirmação da senha não confere.';
    }

    if (mb_strlen($values['display_name']) < 2) {
        $errors[] = $profile === 'fornecedor' ? 'Informe o nome comercial do fornecedor.' : 'Informe o nome do condomínio ou organização.';
    }
    if (mb_strlen($values['city']) < 2) {
        $errors[] = 'Informe a cidade.';
    }
    if (!preg_match('/^[A-Z]{2}$/', $values['state'])) {
        $errors[] = 'Informe a UF com duas letras.';
    }

    $contractorTypes = ['condominio', 'administradora', 'sindico_profissional', 'empresa'];
    if ($profile === 'contratante' && !in_array($values['account_type'], $contractorTypes, true)) {
        $errors[] = 'Tipo de contratante inválido.';
    }

    $radius = filter_var($values['service_radius_km'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);
    if ($profile === 'fornecedor' && $radius === false) {
        $errors[] = 'O raio de atendimento deve estar entre 1 e 500 km.';
    }

    if (!$errors) {
        try {
            $pdo = Database::connection($config);
            $pdo->beginTransaction();

            $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $check->execute(['email' => $email]);
            if ($check->fetch()) {
                $errors[] = 'Já existe uma conta cadastrada com este e-mail.';
                $pdo->rollBack();
            } else {
                $userStmt = $pdo->prepare('INSERT INTO users (role, name, email, password_hash, phone) VALUES (:role, :name, :email, :password_hash, :phone)');
                $userStmt->execute([
                    'role' => $profile,
                    'name' => $values['name'],
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'phone' => $values['phone'] !== '' ? $values['phone'] : null,
                ]);
                $userId = (int) $pdo->lastInsertId();

                if ($profile === 'fornecedor') {
                    $profileStmt = $pdo->prepare('INSERT INTO supplier_profiles (user_id, legal_name, trade_name, cpf_cnpj, description, city, state, service_radius_km, emergency_service) VALUES (:user_id, :legal_name, :trade_name, :cpf_cnpj, :description, :city, :state, :radius, :emergency)');
                    $profileStmt->execute([
                        'user_id' => $userId,
                        'legal_name' => $values['legal_name'] !== '' ? $values['legal_name'] : null,
                        'trade_name' => $values['display_name'],
                        'cpf_cnpj' => $values['cpf_cnpj'] !== '' ? $values['cpf_cnpj'] : null,
                        'description' => $values['description'] !== '' ? $values['description'] : null,
                        'city' => $values['city'],
                        'state' => $values['state'],
                        'radius' => (int) $radius,
                        'emergency' => $values['emergency_service'] === '1' ? 1 : 0,
                    ]);
                } else {
                    $profileStmt = $pdo->prepare('INSERT INTO contractor_profiles (user_id, account_type, legal_name, display_name, cpf_cnpj, city, state) VALUES (:user_id, :account_type, :legal_name, :display_name, :cpf_cnpj, :city, :state)');
                    $profileStmt->execute([
                        'user_id' => $userId,
                        'account_type' => $values['account_type'],
                        'legal_name' => $values['legal_name'] !== '' ? $values['legal_name'] : null,
                        'display_name' => $values['display_name'],
                        'cpf_cnpj' => $values['cpf_cnpj'] !== '' ? $values['cpf_cnpj'] : null,
                        'city' => $values['city'],
                        'state' => $values['state'],
                    ]);
                }

                $pdo->commit();
                Csrf::rotate();
                Auth::establish(['id' => $userId, 'role' => $profile, 'name' => $values['name'], 'email' => $email]);
                redirect('painel.php?novo=1');
            }
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Não foi possível concluir o cadastro agora. Tente novamente.';
        }
    }
}

$appName = (string) ($config['name'] ?? 'CondoConecta');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastro — <?= e($appName) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-page">
<header class="topbar"><div class="container nav"><a class="brand" href="index.php">Condo<span>Conecta</span></a><nav><a href="login.php">Já tenho conta</a></nav></div></header>
<main class="auth-shell container">
    <?php if ($profile === ''): ?>
        <section class="profile-choice">
            <p class="eyebrow">Comece por aqui</p>
            <h1>Como você quer usar o CondoConecta?</h1>
            <div class="audience-grid">
                <article class="audience-card">
                    <span class="badge">FORNECEDOR</span>
                    <h2>Quero oferecer serviços</h2>
                    <p>Cadastro gratuito, recebimento de oportunidades e envio de propostas sem cobrança por lead.</p>
                    <a class="button button-secondary" href="cadastro.php?perfil=fornecedor">Cadastrar fornecedor grátis</a>
                </article>
                <article class="audience-card audience-highlight">
                    <span class="badge">CONTRATANTE</span>
                    <h2>Quero contratar serviços</h2>
                    <p>Para condomínios, administradoras, síndicos profissionais e empresas que precisam organizar cotações.</p>
                    <a class="button button-primary" href="cadastro.php?perfil=contratante">Cadastrar contratante</a>
                </article>
            </div>
        </section>
    <?php else: ?>
        <section class="auth-card auth-card-wide">
            <div class="auth-heading">
                <span class="badge"><?= $profile === 'fornecedor' ? 'FORNECEDOR • GRÁTIS' : 'CONTRATANTE' ?></span>
                <h1><?= $profile === 'fornecedor' ? 'Cadastre seus serviços' : 'Cadastre sua organização' ?></h1>
                <p><?= $profile === 'fornecedor' ? 'Você não paga para criar o perfil nem para enviar propostas.' : 'Comece com sua conta e depois escolha o plano adequado ao seu uso.' ?></p>
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-error"><strong>Revise os dados:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>

            <form method="post" class="form-grid" novalidate>
                <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                <input type="hidden" name="profile" value="<?= e($profile) ?>">

                <label>Seu nome completo<input name="name" value="<?= e($values['name']) ?>" autocomplete="name" required></label>
                <label>E-mail<input type="email" name="email" value="<?= e($values['email']) ?>" autocomplete="email" required></label>
                <label>WhatsApp / telefone<input name="phone" value="<?= e($values['phone']) ?>" autocomplete="tel"></label>
                <label><?= $profile === 'fornecedor' ? 'Nome comercial' : 'Nome do condomínio / organização' ?><input name="display_name" value="<?= e($values['display_name']) ?>" required></label>

                <?php if ($profile === 'contratante'): ?>
                    <label>Tipo de conta<select name="account_type"><option value="condominio" <?= $values['account_type'] === 'condominio' ? 'selected' : '' ?>>Condomínio</option><option value="administradora" <?= $values['account_type'] === 'administradora' ? 'selected' : '' ?>>Administradora</option><option value="sindico_profissional" <?= $values['account_type'] === 'sindico_profissional' ? 'selected' : '' ?>>Síndico profissional</option><option value="empresa" <?= $values['account_type'] === 'empresa' ? 'selected' : '' ?>>Empresa</option></select></label>
                <?php else: ?>
                    <label>Raio inicial de atendimento (km)<input type="number" name="service_radius_km" min="1" max="500" value="<?= e($values['service_radius_km']) ?>"></label>
                <?php endif; ?>

                <label>Razão social <span class="optional">opcional</span><input name="legal_name" value="<?= e($values['legal_name']) ?>"></label>
                <label>CPF/CNPJ <span class="optional">opcional</span><input name="cpf_cnpj" value="<?= e($values['cpf_cnpj']) ?>"></label>
                <label>Cidade<input name="city" value="<?= e($values['city']) ?>" required></label>
                <label>UF<input name="state" maxlength="2" value="<?= e($values['state']) ?>" required></label>

                <?php if ($profile === 'fornecedor'): ?>
                    <label class="form-span">Descrição dos serviços <span class="optional">opcional</span><textarea name="description" rows="3"><?= e($values['description']) ?></textarea></label>
                    <label class="check-row form-span"><input type="checkbox" name="emergency_service" value="1" <?= $values['emergency_service'] === '1' ? 'checked' : '' ?>> Aceito receber oportunidades de atendimento emergencial “Preciso Agora”.</label>
                <?php endif; ?>

                <label>Senha<input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
                <label>Confirmar senha<input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></label>

                <div class="form-span form-actions"><button class="button button-primary" type="submit">Criar minha conta</button><a href="cadastro.php">Trocar tipo de cadastro</a></div>
            </form>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
