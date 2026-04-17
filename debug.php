<?php
require_once __DIR__ . '/config/bootstrap.php';
echo '<pre>';
echo 'BASE_URL: ' . BASE_URL . "\n";
echo 'APP_URL: ' . APP_URL . "\n";
echo 'CSS URL: ' . BASE_URL . 'assets/css/style.css' . "\n";
echo 'CSS exists: ' . (file_exists(__DIR__ . '/assets/css/style.css') ? 'SIM' : 'NÃO') . "\n";
echo 'SCRIPT_NAME: ' . ($_SERVER['SCRIPT_NAME'] ?? '') . "\n";
echo 'HTTP_HOST: ' . ($_SERVER['HTTP_HOST'] ?? '') . "\n";
echo '</pre>';
echo '<link rel="stylesheet" href="' . BASE_URL . 'assets/css/style.css">';
echo '<p style="font-size:2rem">Se esse texto estiver verde, o CSS carregou ✅</p>';
