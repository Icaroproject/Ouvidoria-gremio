<?php
require_once __DIR__ . '/includes_functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        flash('erro', 'Informe seu e-mail.');
        header('Location: forgot_password.php');
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('erro', 'Informe um e-mail válido.');
        header('Location: forgot_password.php');
        exit;
    }

    try {
        $pdo = conectarPDO();

        if (emailExisteNoSistema($pdo, $email)) {
            $tokenBruto = criarTokenRecuperacao($pdo, $email);
            $link = APP_URL . '/reset_password.php?token=' . urlencode($tokenBruto);
            enviarEmailRecuperacao($email, $link);
        }

        header('Location: email_enviado.php');
        exit;
    } catch (PDOException $e) {
        flash('erro', 'Não foi possível processar sua solicitação agora. Tente novamente.');
        header('Location: forgot_password.php');
        exit;
    }
}

$tituloPagina = 'Recuperar Senha — Ouvidoria do Grêmio Escolar';
require_once __DIR__ . '/header.php';
?>
<div class="page-header">
  <div class="page-header-inner">
    <span class="section-label">RECUPERAÇÃO DE ACESSO</span>
    <h1>Recuperar <em>senha</em></h1>
    <p>Informe apenas seu e-mail. Se ele estiver cadastrado, enviaremos um link seguro para redefinição da senha.</p>
  </div>
</div>

<section class="auth-section">
  <div class="auth-card">
    <div class="auth-body">
      <h2 class="auth-title">Enviar link de recuperação</h2>
      <p class="auth-sub">Você receberá um link no e-mail informado.</p>

      <form method="post" autocomplete="off" id="formForgotPassword">
        <div class="form-group">
          <label for="email">E-mail cadastrado</label>
          <input type="email" name="email" id="email" class="form-control" placeholder="seuemail@exemplo.com" required>
        </div>

        <button type="submit" class="btn-submit">
          <i class="fa-solid fa-paper-plane"></i> Enviar link
        </button>
      </form>

      <div style="margin-top:18px;text-align:center;">
        <a href="login.php" style="color:var(--verde);font-weight:700;text-decoration:none;">Voltar para o login</a>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
