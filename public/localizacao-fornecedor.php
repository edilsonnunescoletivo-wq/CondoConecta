<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

$user = Auth::requireAuth();
if (($user['role'] ?? '') !== 'fornecedor') {
    redirect('painel.php');
}

$pdo = Database::connection($config);
$stmt = $pdo->prepare('SELECT id, trade_name, city, state, service_radius_km, latitude, longitude FROM supplier_profiles WHERE user_id = :user_id LIMIT 1');
$stmt->execute(['user_id' => $user['id']]);
$profile = $stmt->fetch();
if (!$profile) {
    redirect('painel.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } else {
        $action = (string) ($_POST['action'] ?? 'save');
        if ($action === 'clear') {
            $update = $pdo->prepare('UPDATE supplier_profiles SET latitude = NULL, longitude = NULL WHERE id = :id');
            $update->execute(['id' => $profile['id']]);
            Csrf::rotate();
            redirect('localizacao-fornecedor.php?removida=1');
        }

        $latitude = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $radius = (int) ($_POST['service_radius_km'] ?? $profile['service_radius_km']);

        if ($latitude === false || $latitude < -90 || $latitude > 90) {
            $errors[] = 'Não foi possível validar a latitude informada.';
        }
        if ($longitude === false || $longitude < -180 || $longitude > 180) {
            $errors[] = 'Não foi possível validar a longitude informada.';
        }
        if ($radius < 1 || $radius > 300) {
            $errors[] = 'O raio de atendimento deve ficar entre 1 e 300 km.';
        }

        if (!$errors) {
            $update = $pdo->prepare('UPDATE supplier_profiles SET latitude = :latitude, longitude = :longitude, service_radius_km = :radius WHERE id = :id');
            $update->execute([
                'latitude' => round((float) $latitude, 7),
                'longitude' => round((float) $longitude, 7),
                'radius' => $radius,
                'id' => $profile['id'],
            ]);
            Csrf::rotate();
            redirect('localizacao-fornecedor.php?salva=1');
        }
    }
}

$stmt->execute(['user_id' => $user['id']]);
$profile = $stmt->fetch();
$hasLocation = $profile['latitude'] !== null && $profile['longitude'] !== null;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Localização de atendimento — CondoConecta</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="dashboard-page">
<header class="topbar"><div class="container nav"><a class="brand" href="painel.php">Condo<span>Conecta</span></a><nav><a href="oportunidades.php">Oportunidades</a><a class="button button-ghost" href="painel.php">Painel</a></nav></div></header>
<main class="container form-page-shell">
    <div class="page-heading"><span class="badge">LOCALIZAÇÃO</span><h1>Defina seu raio real de atendimento</h1><p>A localização é opcional e usada somente para calcular distância entre sua base e os chamados. Ela não aparece no perfil público.</p></div>

    <?php if (isset($_GET['salva'])): ?><div class="alert alert-success">Localização atualizada. O matching por distância está ativo para o seu perfil.</div><?php endif; ?>
    <?php if (isset($_GET['removida'])): ?><div class="alert alert-success">Localização removida. O sistema voltou a usar cidade/região como referência.</div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <section class="card-panel market-form">
        <h2><?= e((string) $profile['trade_name']) ?></h2>
        <p class="muted">Base cadastrada: <?= e((string) $profile['city']) ?> • <?= e((string) $profile['state']) ?></p>
        <div class="urgency-help"><strong>Privacidade</strong><span>O CondoConecta guarda a coordenada para cálculo interno. Contratantes veem apenas sua cidade/região e a distância aproximada quando aplicável.</span></div>

        <form method="post" class="stack-form" id="location-form">
            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="latitude" id="latitude" value="<?= $hasLocation ? e((string) $profile['latitude']) : '' ?>">
            <input type="hidden" name="longitude" id="longitude" value="<?= $hasLocation ? e((string) $profile['longitude']) : '' ?>">
            <label>Raio máximo de atendimento (km)<input type="number" name="service_radius_km" min="1" max="300" required value="<?= (int) $profile['service_radius_km'] ?>"></label>
            <div id="location-status" class="alert <?= $hasLocation ? 'alert-success' : '' ?>"><?= $hasLocation ? 'Localização de referência já configurada.' : 'Localização ainda não configurada. O matching usa cidade/região.' ?></div>
            <div class="form-actions">
                <button class="button button-secondary" type="button" id="get-location">Usar minha localização atual</button>
                <button class="button button-primary" type="submit" id="save-location" <?= $hasLocation ? '' : 'disabled' ?>>Salvar localização e raio</button>
            </div>
        </form>

        <?php if ($hasLocation): ?>
        <form method="post" style="margin-top:18px" onsubmit="return confirm('Remover a localização e voltar ao matching por cidade/região?');">
            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="clear">
            <button class="button button-ghost" type="submit">Remover localização</button>
        </form>
        <?php endif; ?>
    </section>
</main>
<script>
(() => {
    const button = document.getElementById('get-location');
    const save = document.getElementById('save-location');
    const status = document.getElementById('location-status');
    const lat = document.getElementById('latitude');
    const lng = document.getElementById('longitude');
    button.addEventListener('click', () => {
        if (!navigator.geolocation) {
            status.textContent = 'Este navegador não oferece geolocalização.';
            status.className = 'alert alert-error';
            return;
        }
        status.textContent = 'Obtendo localização…';
        status.className = 'alert';
        navigator.geolocation.getCurrentPosition((position) => {
            lat.value = position.coords.latitude.toFixed(7);
            lng.value = position.coords.longitude.toFixed(7);
            status.textContent = `Localização capturada com precisão aproximada de ${Math.round(position.coords.accuracy)} m. Clique em salvar.`;
            status.className = 'alert alert-success';
            save.disabled = false;
        }, () => {
            status.textContent = 'Localização não autorizada ou indisponível. Você pode continuar usando cidade/região.';
            status.className = 'alert alert-error';
        }, {enableHighAccuracy: true, timeout: 12000, maximumAge: 300000});
    });
})();
</script>
</body>
</html>
