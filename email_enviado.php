<?php
require_once __DIR__ . '/includes_functions.php';

$tituloPagina = 'E-mail enviado — Ouvidoria do Grêmio Escolar';
require_once __DIR__ . '/header.php';
?>

<div class="page-header">
  <div class="page-header-inner">
    <span class="section-label">RECUPERAÇÃO DE ACESSO</span>
    <h1>E-mail <em>enviado</em></h1>
    <p>Verifique sua caixa de entrada para continuar a redefinição da senha.</p>
  </div>
</div>

<section class="auth-section">
  <div class="auth-card">
    <div class="auth-body" style="text-align:center;">
      <div style="font-size:58px;margin-bottom:14px;">📧</div>

      <h2 class="auth-title">Link enviado com sucesso</h2>

      <p class="auth-sub" style="max-width:420px;margin:0 auto 18px auto;">
        Se o e-mail informado estiver cadastrado no sistema, você receberá um link para redefinir sua senha com segurança.
      </p>

      <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:16px;padding:16px 18px;max-width:460px;margin:0 auto 22px auto;color:#5a6055;font-size:0.92rem;line-height:1.6;">
        Verifique também a pasta de <strong>spam</strong>, <strong>lixo eletrônico</strong> ou <strong>promoções</strong>.
      </div>

      <div style="display:flex;justify-content:center;gap:12px;flex-wrap:wrap;">
        <a href="login.php" class="btn-submit" style="display:inline-flex;width:auto;padding:12px 22px;text-decoration:none;">
          <i class="fa-solid fa-right-to-bracket"></i> Voltar para login
        </a>

        <a href="forgot_password.php" class="topbar-btn topbar-btn-login" style="text-decoration:none;">
          Tentar novamente
        </a>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>