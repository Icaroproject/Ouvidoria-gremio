<?php
require_once __DIR__ . '/includes_functions.php';
$paginaAtual = basename($_SERVER['PHP_SELF']);
$flashData = flash();
$logado = usuarioLogado() || administradorLogado();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= isset($tituloPagina) ? e($tituloPagina) : 'Ouvidoria do Grêmio Escolar' ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="topbar">
  <button class="topbar-hamburger" id="hamburgerBtn" aria-label="Menu"><span></span><span></span><span></span></button>
  <a href="index.php" class="topbar-brand">
    <div><span class="topbar-brand-name">Dom Walfrido</span><span class="topbar-brand-sub">Ouvidoria do Grêmio Escolar</span></div>
  </a>
  <div class="topbar-divider"></div>
  <nav class="topbar-nav">
    <a href="index.php" class="topbar-link <?= topoAtivo($paginaAtual, 'index.php') ?>"><i class="fa-solid fa-house"></i> Início</a>
    <a href="sobre.php" class="topbar-link <?= topoAtivo($paginaAtual, 'sobre.php') ?>"><i class="fa-solid fa-school"></i> Sobre a Escola</a>
    <a href="manifestacao.php" class="topbar-link <?= topoAtivo($paginaAtual, 'manifestacao.php') ?>"><i class="fa-solid fa-paper-plane"></i> Fazer Manifestação <span class="nav-badge">Novo</span></a>
    <a href="acompanhar.php" class="topbar-link <?= topoAtivo($paginaAtual, 'acompanhar.php') ?>"><i class="fa-solid fa-magnifying-glass"></i> Acompanhar</a>
    <?php if (usuarioLogado()): ?>
      <a href="minha_conta.php" class="topbar-link <?= topoAtivo($paginaAtual, 'minha_conta.php') ?>"><i class="fa-solid fa-user"></i> Minha conta</a>
    <?php elseif (!administradorLogado()): ?>
      <a href="login.php" class="topbar-link <?= topoAtivo($paginaAtual, 'login.php') ?>"><i class="fa-solid fa-right-to-bracket"></i> Login</a>
    <?php endif; ?>
    <?php if (administradorLogado()): ?>
      <a href="adm.php" class="topbar-link <?= topoAtivo($paginaAtual, 'adm.php') ?>"><i class="fa-solid fa-shield-halved"></i> Painel do Grêmio</a>
    <?php endif; ?>
  </nav>
  <div class="topbar-actions">
    <?php if (administradorLogado()): ?>
      <a href="adm.php" class="topbar-btn topbar-btn-login"><i class="fa-solid fa-user-shield"></i> <span><?= e($_SESSION['admin']['nome']) ?></span></a>
      <a href="logout.php" class="topbar-btn topbar-btn-cadastro"><i class="fa-solid fa-right-from-bracket"></i> <span>Sair</span></a>
    <?php elseif (usuarioLogado()): ?>
      <a href="minha_conta.php" class="topbar-btn topbar-btn-login"><i class="fa-solid fa-user"></i> <span><?= e($_SESSION['usuario']['nome']) ?></span></a>
      <a href="logout.php" class="topbar-btn topbar-btn-cadastro"><i class="fa-solid fa-right-from-bracket"></i> <span>Logout</span></a>
    <?php else: ?>
      <a href="login.php" class="topbar-btn topbar-btn-login"><i class="fa-solid fa-right-to-bracket"></i> <span>Login</span></a>
      <a href="login.php#cadastro" class="topbar-btn topbar-btn-cadastro"><i class="fa-solid fa-user-plus"></i> <span>Cadastrar-se</span></a>
    <?php endif; ?>
  </div>
</header>

<aside class="drawer" id="drawerSidebar">
  <div class="drawer-header">
    <div class="drawer-logos">
      <img src="Logo da escola.png" alt="Logo EEEP Dom Walfrido" class="drawer-logo-img" onerror="this.style.display='none'">
      <img src="Negocio do ceara.png" alt="Governo do Ceará" class="drawer-logo-img" onerror="this.style.display='none'">
    </div>
    <button class="drawer-close" id="drawerClose"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <div class="drawer-brand"><span class="drawer-brand-name">Dom Walfrido</span><span class="drawer-brand-sub">Ouvidoria do Grêmio Escolar</span></div>
  <nav class="drawer-nav">
    <div class="drawer-section-label">NAVEGAÇÃO</div>
    <a href="index.php" class="drawer-item <?= topoAtivo($paginaAtual, 'index.php') ?>"><span class="drawer-item-icon"><i class="fa-solid fa-house"></i></span><span class="drawer-item-label">Início</span></a>
    <a href="sobre.php" class="drawer-item <?= topoAtivo($paginaAtual, 'sobre.php') ?>"><span class="drawer-item-icon"><i class="fa-solid fa-school"></i></span><span class="drawer-item-label">Sobre a Escola</span></a>
    <a href="manifestacao.php" class="drawer-item <?= topoAtivo($paginaAtual, 'manifestacao.php') ?>"><span class="drawer-item-icon"><i class="fa-solid fa-paper-plane"></i></span><span class="drawer-item-label">Fazer Manifestação</span><span class="drawer-badge">Novo</span></a>
    <a href="acompanhar.php" class="drawer-item <?= topoAtivo($paginaAtual, 'acompanhar.php') ?>"><span class="drawer-item-icon"><i class="fa-solid fa-magnifying-glass"></i></span><span class="drawer-item-label">Acompanhar Protocolo</span></a>

    <?php if (usuarioLogado()): ?>
      <a href="minha_conta.php" class="drawer-item <?= topoAtivo($paginaAtual, 'minha_conta.php') ?>"><span class="drawer-item-icon"><i class="fa-solid fa-user"></i></span><span class="drawer-item-label">Minha conta</span></a>
      <a href="minha_conta.php#manifestacoes" class="drawer-item"><span class="drawer-item-icon"><i class="fa-solid fa-list-check"></i></span><span class="drawer-item-label">Minhas manifestações</span></a>
      <a href="logout.php" class="drawer-item"><span class="drawer-item-icon"><i class="fa-solid fa-right-from-bracket"></i></span><span class="drawer-item-label">Logout</span></a>
    <?php elseif (administradorLogado()): ?>
      <div class="drawer-section-label">ADMINISTRAÇÃO</div>
      <a href="adm.php" class="drawer-item <?= topoAtivo($paginaAtual, 'adm.php') ?>"><span class="drawer-item-icon"><i class="fa-solid fa-shield-halved"></i></span><span class="drawer-item-label">Painel do Grêmio</span></a>
      <a href="logout.php" class="drawer-item"><span class="drawer-item-icon"><i class="fa-solid fa-right-from-bracket"></i></span><span class="drawer-item-label">Sair</span></a>
    <?php else: ?>
      <div class="drawer-section-label">ACESSO</div>
      <a href="login.php" class="drawer-item <?= topoAtivo($paginaAtual, 'login.php') ?>"><span class="drawer-item-icon"><i class="fa-solid fa-right-to-bracket"></i></span><span class="drawer-item-label">Login</span></a>
      <a href="login.php#cadastro" class="drawer-item"><span class="drawer-item-icon"><i class="fa-solid fa-user-plus"></i></span><span class="drawer-item-label">Cadastrar-se</span></a>
      <a href="forgot_password.php" class="drawer-item <?= topoAtivo($paginaAtual, 'forgot_password.php') ?>"><span class="drawer-item-icon"><i class="fa-solid fa-key"></i></span><span class="drawer-item-label">Esqueci a senha</span></a>
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
