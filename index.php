<?php
$tituloPagina = 'Ouvidoria do Grêmio Escolar — Dom Walfrido';
require_once __DIR__ . '/header.php';
?>
<section class="hero">
  <div class="hero-bg-shapes">
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>
    <div class="shape shape-3"></div>
  </div>
  <div class="hero-inner">
    <div class="hero-text">
      <div class="hero-eyebrow"><span class="eyebrow-dot"></span>Canal oficial do Grêmio Escolar</div>
      <h1 class="hero-title">Sua voz <br><em>fortalece</em> a<br>escola.</h1>
      <p class="hero-desc">A Ouvidoria do Grêmio Escolar da EEEP Dom Walfrido Teixeira Vieira é um espaço de escuta, participação e melhoria da vida estudantil.</p>
      <div class="hero-actions">
        <a href="manifestacao.php" class="btn-primary-main"><i class="fa-solid fa-paper-plane"></i> Fazer Manifestação</a>
        <?php if (administradorLogado()): ?>
          <a href="adm.php" class="btn-secondary-main"><i class="fa-solid fa-shield-halved"></i> Painel do Grêmio</a>
        <?php elseif (usuarioLogado()): ?>
          <a href="minha_conta.php" class="btn-secondary-main"><i class="fa-solid fa-user"></i> Minha conta</a>
          <a href="acompanhar.php" class="btn-outline-main"><i class="fa-solid fa-magnifying-glass"></i> Acompanhar</a>
        <?php else: ?>
          <a href="login.php" class="btn-secondary-main"><i class="fa-solid fa-right-to-bracket"></i> Login</a>
          <a href="forgot_password.php" class="btn-outline-main"><i class="fa-solid fa-key"></i> Esqueci a senha</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="hero-logos-block">
      <div class="logos-frame">
        <img src="Logo da escola.png" alt="EEEP Dom Walfrido" class="hero-logo-escola">
        <div class="logos-divider"></div>
        <img src="Negocio do ceara.png" alt="Governo Ceará" class="hero-logo-ceara">
      </div>
      <div class="hero-card-float">
        <div class="float-item"><i class="fa-solid fa-comments"></i><div><strong>Escuta ativa</strong><span>Espaço para sugestões, elogios e denúncias</span></div></div>
        <div class="float-item"><i class="fa-solid fa-user-shield"></i><div><strong>Área do administrador</strong><span>Painel protegido por login</span></div></div>
        <div class="float-item"><i class="fa-solid fa-school"></i><div><strong>Grêmio Escolar</strong><span>Participação estudantil com responsabilidade</span></div></div>
      </div>
    </div>
  </div>
</section>

<section class="section tipos-section">
  <div class="section-header">
    <div class="section-label">PARTICIPE</div>
    <h2 class="section-title">Canais de <em>manifestação</em></h2>
    <p class="section-desc">Toda manifestação enviada ao grêmio é registrada para análise e encaminhamento.</p>
  </div>
  <div class="tipos-grid">
    <div class="tipo-card tipo-sugestao"><div class="tipo-icon"><i class="fa-solid fa-lightbulb"></i></div><h3>Sugestão</h3><p>Compartilhe ideias para melhorar eventos, espaços, comunicação e ações estudantis.</p><a href="manifestacao.php?tipo=sugestao" class="tipo-link">Registrar <i class="fa-solid fa-arrow-right"></i></a></div>
    <div class="tipo-card tipo-elogio"><div class="tipo-icon"><i class="fa-solid fa-hands-clapping"></i></div><h3>Elogio</h3><p>Valorize projetos, professores, representantes e atitudes positivas na escola.</p><a href="manifestacao.php?tipo=elogio" class="tipo-link">Registrar <i class="fa-solid fa-arrow-right"></i></a></div>
    <div class="tipo-card tipo-reclamacao"><div class="tipo-icon"><i class="fa-solid fa-triangle-exclamation"></i></div><h3>Reclamação</h3><p>Informe situações que precisam de correção, apoio ou mediação.</p><a href="manifestacao.php?tipo=reclamacao" class="tipo-link">Registrar <i class="fa-solid fa-arrow-right"></i></a></div>
    <div class="tipo-card tipo-denuncia"><div class="tipo-icon"><i class="fa-solid fa-scale-balanced"></i></div><h3>Denúncia</h3><p>Relate fatos sensíveis com seriedade. O painel do grêmio exige autenticação para análise.</p><a href="manifestacao.php?tipo=denuncia" class="tipo-link">Registrar <i class="fa-solid fa-arrow-right"></i></a></div>
  </div>
</section>
<?php require_once __DIR__ . '/footer.php'; ?>
