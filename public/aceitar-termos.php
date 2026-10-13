<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Terms;

$user = Auth::requireAuth(false);
$pdo = Database::connection($config);

if (Terms::accepted($pdo, (int)$user['id'])) {
    Auth::markTermsAccepted();
    redirect('painel.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        $error = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } elseif (empty($_POST['accept_terms'])) {
        $error = 'É necessário aceitar os Termos de Uso para continuar.';
    } else {
        Terms::record($pdo, (int)$user['id'], 'atualizacao');
        Auth::markTermsAccepted();
        Csrf::rotate();
        redirect('painel.php?termos=aceitos');
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Aceitar Termos — CondoConecta</title><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="auth-page"><header class="topbar"><div class="container nav"><a class="brand" href="index.php">Condo<span>Conecta</span></a></div></header><main class="auth-shell container"><section class="auth-card auth-card-wide"><div class="auth-heading"><span class="badge">ATUALIZAÇÃO OBRIGATÓRIA</span><h1>Leia e aceite os Termos de Uso</h1><p>Para continuar usando o CondoConecta, confirme a versão atual dos termos.</p></div><?php if($error):?><div class="alert alert-error"><?=e($error)?></div><?php endif;?><div class="editor-card" style="max-height:360px;overflow:auto"><p><strong>Resumo:</strong> o CondoConecta intermedeia o encontro entre prestadores e contratantes. As partes são responsáveis pela contratação, execução, conferência de dados, documentos, pagamentos, tributos, retenções, recolhimentos e demais obrigações do serviço. A plataforma mantém suas próprias obrigações legais de segurança e proteção de dados.</p><p><a href="termos.php" target="_blank" rel="noopener">Abrir Termos completos — versão <?=e(Terms::VERSION)?></a></p></div><form method="post" class="stack-form"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><label class="check-row"><input type="checkbox" name="accept_terms" value="1" required><span>Li e aceito os <a href="termos.php" target="_blank" rel="noopener">Termos de Uso</a>, inclusive as responsabilidades tributárias, contratuais e pela conferência das informações.</span></label><button class="button button-primary" type="submit">Aceitar e continuar</button></form></section></main></body></html>
