<?php
require_once __DIR__ . '/includes_functions.php';
unset($_SESSION['admin'], $_SESSION['usuario']);
clearRememberMeCookies();
flash('sucesso', 'Sessão encerrada com sucesso.');
header('Location: login.php');
exit;
