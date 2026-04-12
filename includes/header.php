<?php
require_once __DIR__ . '/../config/bootstrap.php';
$paginaAtual = basename($_SERVER['PHP_SELF']);
$flashData   = flash();

// Base URL relativa ao document root — funciona de qualquer profundidade de pasta
$_scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
// Base URL absoluta — definida em config/config.php → BASE_URL = '/projeto_final/'
$_base = BASE_URL;

// Contagem de notificações não lidas
$_pdo_hdr = null;
$_notifsNaoLidas = 0;
try {
    $_pdo_hdr = conectarPDO();
    garantirTabelasExtras($_pdo_hdr);
    if (usuarioLogado()) {
        $_notifsNaoLidas = contarNotificacoesNaoLidas($_pdo_hdr, 'IDusu', (int)$_SESSION['usuario']['id']);
    } elseif (administradorLogado()) {
        $_notifsNaoLidas = contarNotificacoesNaoLidas($_pdo_hdr, 'IDadm', (int)$_SESSION['admin']['id']);
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= isset($tituloPagina) ? e($tituloPagina) : 'Ouvidoria do Grêmio Escolar' ?></title>
  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <!-- Custom CSS (sempre depois do Bootstrap para sobrescrever) -->
  <link rel="stylesheet" href="<?= $_base ?>assets/css/style.css">
</head>
<body>

<header class="topbar">
  <button class="topbar-hamburger" id="hamburgerBtn" aria-label="Menu"><span></span><span></span><span></span></button>
  <a href="<?= $_base ?>index.php" class="topbar-brand">
    <div><span class="topbar-brand-name">Dom Walfrido</span><span class="topbar-brand-sub">Ouvidoria do Grêmio Escolar</span></div>
  </a>
  <div class="topbar-divider"></div>
  <nav class="topbar-nav">
    <a href="<?= $_base ?>index.php"        class="topbar-link <?= topoAtivo($paginaAtual,'index.php') ?>"><i class="fa-solid fa-house"></i> Início</a>
    <a href="<?= $_base ?>app/sobre.php"        class="topbar-link <?= topoAtivo($paginaAtual,'sobre.php') ?>"><i class="fa-solid fa-school"></i> Sobre a Escola</a>
    <a href="<?= $_base ?>app/manifestacao.php" class="topbar-link <?= topoAtivo($paginaAtual,'manifestacao.php') ?>"><i class="fa-solid fa-paper-plane"></i> Fazer Manifestação <span class="nav-badge">Novo</span></a>
    <a href="<?= $_base ?>app/acompanhar.php"   class="topbar-link <?= topoAtivo($paginaAtual,'acompanhar.php') ?>"><i class="fa-solid fa-magnifying-glass"></i> Acompanhar</a>
    <?php if (usuarioLogado()): ?>
      <a href="<?= $_base ?>app/painel/minha_conta.php" class="topbar-link <?= topoAtivo($paginaAtual,'minha_conta.php') ?>"><i class="fa-solid fa-user"></i> Minha conta</a>
    <?php elseif (!administradorLogado()): ?>
      <a href="<?= $_base ?>app/auth/login.php" class="topbar-link <?= topoAtivo($paginaAtual,'login.php') ?>"><i class="fa-solid fa-right-to-bracket"></i> Login</a>
    <?php endif; ?>
    <?php if (administradorLogado()): ?>
      <a href="<?= $_base ?>app/painel/dashboard.php" class="topbar-link <?= topoAtivo($paginaAtual,'dashboard.php') ?>"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
      <a href="<?= $_base ?>app/painel/adm.php" class="topbar-link <?= topoAtivo($paginaAtual,'adm.php') ?>"><i class="fa-solid fa-shield-halved"></i> Painel do Grêmio</a>
    <?php endif; ?>
  </nav>

  <div class="topbar-actions">
    <?php if (administradorLogado() || usuarioLogado()): ?>
      <!-- Sino de notificações -->
      <div class="notif-bell-wrap dropdown">
        <button class="notif-bell btn p-0" id="notifDropdown" data-bs-toggle="dropdown" data-notif-url="<?= $_base ?>app/notificacoes.php" aria-expanded="false"
                data-coluna="<?= administradorLogado() ? 'IDadm' : 'IDusu' ?>"
                data-id="<?= administradorLogado() ? (int)$_SESSION['admin']['id'] : (int)$_SESSION['usuario']['id'] ?>">
          <i class="fa-solid fa-bell"></i>
          <?php if ($_notifsNaoLidas > 0): ?>
            <span class="notif-badge"><?= $_notifsNaoLidas > 9 ? '9+' : $_notifsNaoLidas ?></span>
          <?php endif; ?>
        </button>
        <div class="dropdown-menu dropdown-menu-end notif-dropdown" aria-labelledby="notifDropdown">
          <div class="notif-header d-flex justify-content-between align-items-center px-3 py-2">
            <strong>Notificações</strong>
            <a href="<?= $_base ?>app/notificacoes.php?marcar_lidas=1" class="text-muted small notif-marcar-lidas">Marcar todas como lidas</a>
          </div>
          <div class="notif-lista" id="notifLista">
            <div class="text-center py-3 text-muted small"><i class="fa-solid fa-spinner fa-spin"></i> Carregando...</div>
          </div>
          <div class="notif-footer text-center py-2">
            <a href="<?= $_base ?>app/notificacoes.php" class="small text-decoration-none" style="color:var(--verde)">Ver todas as notificações</a>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if (administradorLogado()): ?>
      <a href="<?= $_base ?>app/painel/adm.php"    class="topbar-btn topbar-btn-login"><i class="fa-solid fa-user-shield"></i> <span><?= e($_SESSION['admin']['nome']) ?></span></a>
      <a href="<?= $_base ?>app/auth/logout.php" class="topbar-btn topbar-btn-cadastro"><i class="fa-solid fa-right-from-bracket"></i> <span>Sair</span></a>
    <?php elseif (usuarioLogado()): ?>
      <a href="<?= $_base ?>app/painel/minha_conta.php" class="topbar-btn topbar-btn-login"><i class="fa-solid fa-user"></i> <span><?= e($_SESSION['usuario']['nome']) ?></span></a>
      <a href="<?= $_base ?>app/auth/logout.php"      class="topbar-btn topbar-btn-cadastro"><i class="fa-solid fa-right-from-bracket"></i> <span>Logout</span></a>
    <?php else: ?>
      <a href="<?= $_base ?>app/auth/login.php"          class="topbar-btn topbar-btn-login"><i class="fa-solid fa-right-to-bracket"></i> <span>Login</span></a>
      <a href="<?= $_base ?>app/auth/login.php#cadastro" class="topbar-btn topbar-btn-cadastro"><i class="fa-solid fa-user-plus"></i> <span>Cadastrar-se</span></a>
    <?php endif; ?>
  </div>
</header>

<!-- Drawer mobile -->
<aside class="drawer" id="drawerSidebar">
  <div class="drawer-header">
    <div class="drawer-logos">
      <img src="<?= $_base ?>assets/images/logo-escola.png" alt="Logo EEEP Dom Walfrido" class="drawer-logo-img" onerror="this.style.display='none'">
      <img src="<?= $_base ?>assets/images/logo-ceara.png" alt="Governo do Ceará" class="drawer-logo-img" onerror="this.style.display='none'">
    </div>
    <button class="drawer-close" id="drawerClose"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <div class="drawer-brand"><span class="drawer-brand-name">Dom Walfrido</span><span class="drawer-brand-sub">Ouvidoria do Grêmio Escolar</span></div>
  <nav class="drawer-nav">
    <div class="drawer-section-label">NAVEGAÇÃO</div>
    <a href="<?= $_base ?>index.php"        class="drawer-item <?= topoAtivo($paginaAtual,'index.php') ?>"><span class="drawer-item-icon"><i class="fa-solid fa-house"></i></span><span class="drawer-item-label">Início</span></a>
    <a href="<?= $_base ?>app/sobre.php"        class="drawer-item <?= topoAtivo($paginaAtual,'sobre.php') ?>"><span class="drawer-item-icon"><i class="fa-solid fa-school"></i></span><span class="drawer-item-label">Sobre a Escola</span></a>
    <a href="<?= $_base ?>app/manifestacao.php" class="drawer-item <?= topoAtivo($paginaAtual,'manifestacao.php') ?>"><span class="drawer-item-icon"><i class="fa-solid fa-paper-plane"></i></span><span class="drawer-item-label">Fazer Manifestação</span><span class="drawer-badge">Novo</span></a>
    <a href="<?= $_base ?>app/acompanhar.php"   class="drawer-item <?= topoAtivo($paginaAtual,'acompanhar.php') ?>"><span class="drawer-item-icon"><i class="fa-solid fa-magnifying-glass"></i></span><span class="drawer-item-label">Acompanhar Protocolo</span></a>
    <?php if (administradorLogado() || usuarioLogado()): ?>
      <a href="<?= $_base ?>app/notificacoes.php" class="drawer-item <?= topoAtivo($paginaAtual,'notificacoes.php') ?>">
        <span class="drawer-item-icon"><i class="fa-solid fa-bell"></i></span>
        <span class="drawer-item-label">Notificações</span>
        <?php if ($_notifsNaoLidas > 0): ?><span class="drawer-badge"><?= $_notifsNaoLidas ?></span><?php endif; ?>
      </a>
    <?php endif; ?>

    <?php if (usuarioLogado()): ?>
      <a href="<?= $_base ?>app/painel/minha_conta.php" class="drawer-item <?= topoAtivo($paginaAtual,'minha_conta.php') ?>"><span class="drawer-item-icon"><i class="fa-solid fa-user"></i></span><span class="drawer-item-label">Minha conta</span></a>
      <a href="<?= $_base ?>app/painel/minha_conta.php#manifestacoes" class="drawer-item"><span class="drawer-item-icon"><i class="fa-solid fa-list-check"></i></span><span class="drawer-item-label">Minhas manifestações</span></a>
      <a href="<?= $_base ?>app/auth/logout.php" class="drawer-item"><span class="drawer-item-icon"><i class="fa-solid fa-right-from-bracket"></i></span><span class="drawer-item-label">Logout</span></a>
    <?php elseif (administradorLogado()): ?>
      <div class="drawer-section-label">ADMINISTRAÇÃO</div>
      <a href="<?= $_base ?>app/painel/dashboard.php" class="drawer-item <?= topoAtivo($paginaAtual,'dashboard.php') ?>"><span class="drawer-item-icon"><i class="fa-solid fa-chart-line"></i></span><span class="drawer-item-label">Dashboard</span></a>
      <a href="<?= $_base ?>app/painel/adm.php" class="drawer-item <?= topoAtivo($paginaAtual,'adm.php') ?>"><span class="drawer-item-icon"><i class="fa-solid fa-shield-halved"></i></span><span class="drawer-item-label">Painel do Grêmio</span></a>
      <a href="<?= $_base ?>app/auth/logout.php" class="drawer-item"><span class="drawer-item-icon"><i class="fa-solid fa-right-from-bracket"></i></span><span class="drawer-item-label">Sair</span></a>
    <?php else: ?>
      <div class="drawer-section-label">ACESSO</div>
      <a href="<?= $_base ?>app/auth/login.php"          class="drawer-item <?= topoAtivo($paginaAtual,'login.php') ?>"><span class="drawer-item-icon"><i class="fa-solid fa-right-to-bracket"></i></span><span class="drawer-item-label">Login</span></a>
      <a href="<?= $_base ?>app/auth/login.php#cadastro" class="drawer-item"><span class="drawer-item-icon"><i class="fa-solid fa-user-plus"></i></span><span class="drawer-item-label">Cadastrar-se</span></a>
      <a href="<?= $_base ?>app/auth/forgot_password.php" class="drawer-item <?= topoAtivo($paginaAtual,'forgot_password.php') ?>"><span class="drawer-item-icon"><i class="fa-solid fa-key"></i></span><span class="drawer-item-label">Esqueci a senha</span></a>
    <?php endif; ?>
  </nav>
  <div class="drawer-footer">
    <div class="drawer-contact"><i class="fa-solid fa-phone"></i><span>(88) 93677-4295</span></div>
    <div class="drawer-contact"><i class="fa-solid fa-envelope"></i><span>walfridoteixeira@escola.ce.gov.br</span></div>
  </div>
</aside>
<div class="drawer-overlay" id="drawerOverlay"></div>

<main class="main-content">
<?php if ($flashData): ?>
  <div class="flash-wrap">
    <div class="flash-msg flash-<?= e($flashData['type']) ?>">
      <i class="fa-solid <?= $flashData['type'] === 'sucesso' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
      <span><?= e($flashData['message']) ?></span>
    </div>
  </div>
<?php endif; ?>
