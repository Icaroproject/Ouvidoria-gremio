<?php
require_once __DIR__ . '/../../config/bootstrap.php';
unset($_SESSION['admin'], $_SESSION['usuario']);
clearRememberMeCookies();
flash('sucesso', 'Sessão encerrada com sucesso.');
header('Location: /projeto_final/app/auth/login.php');
exit;
