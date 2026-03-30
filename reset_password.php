<?php
require_once __DIR__ . '/includes_functions.php';

$token = $_GET['token'] ?? ($_POST['token'] ?? '');

if ($token === '') {
    flash('erro', 'Token inválido.');
    header('Location: login.php');
    exit;
}

$pdo = conectarPDO();
$reset = buscarResetValidoPorToken($pdo, $token);

if (!$reset) {
    flash('erro', 'Link inválido ou expirado.');
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $novaSenha = trim($_POST['nova_senha'] ?? '');
    $confirmarSenha = trim($_POST['confirmar_senha'] ?? '');

    if ($novaSenha === '' || $confirmarSenha === '') {
        flash('erro', 'Preencha os dois campos de senha.');
        header('Location: reset_password.php?token=' . urlencode($token));
        exit;
    }

    if (mb_strlen($novaSenha) < 8) {
        flash('erro', 'A nova senha deve ter pelo menos 8 caracteres.');
        header('Location: reset_password.php?token=' . urlencode($token));
        exit;
    }

    if ($novaSenha !== $confirmarSenha) {
        flash('erro', 'As senhas não coincidem.');
        header('Location: reset_password.php?token=' . urlencode($token));
        exit;
    }

    try {
        $novaSenhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
        atualizarSenhaPorEmail($pdo, $reset['email'], $novaSenhaHash);
        marcarTokenComoUsado($pdo, (int) $reset['id']);

        flash('sucesso', 'Senha redefinida com sucesso. Agora você já pode fazer login.');
        header('Location: login.php');
        exit;
    } catch (PDOException $e) {
        flash('erro', 'Não foi possível atualizar a senha.');
        header('Location: reset_password.php?token=' . urlencode($token));
        exit;
    }
}

$tituloPagina = 'Redefinir Senha — Ouvidoria do Grêmio Escolar';
require_once __DIR__ . '/header.php';
?>
<div class="page-header">
  <div class="page-header-inner">
    <span class="section-label">NOVA SENHA</span>
    <h1>Redefinir <em>senha</em></h1>
    <p>Crie uma nova senha para sua conta.</p>
  </div>
</div>

<section class="auth-section">
  <div class="auth-card">
    <div class="auth-body">
      <h2 class="auth-title">Definir nova senha</h2>
      <p class="auth-sub">Escolha uma senha forte e confirme abaixo.</p>

      <form method="post" autocomplete="off" id="formResetPassword">
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div class="form-group">
          <label for="nova_senha">Nova senha</label>
          <input type="password" name="nova_senha" id="nova_senha" class="form-control" placeholder="Digite a nova senha" required>
        </div>

        <div class="form-group">
          <label for="confirmar_senha">Confirmar nova senha</label>
          <input type="password" name="confirmar_senha" id="confirmar_senha" class="form-control" placeholder="Repita a nova senha" required>
        </div>

        <button type="submit" class="btn-submit">
          <i class="fa-solid fa-key"></i> Salvar nova senha
        </button>
      </form>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
