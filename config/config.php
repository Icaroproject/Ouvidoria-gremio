<?php
/**
 * Configurações do sistema — banco de dados, e-mail, sessão.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Banco de dados ────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'dbouvidoria');
define('DB_USER', 'root');
define('DB_PASS', '');

// ── URL base da aplicação ─────────────────────────────────────────────────
// ── URL base ───────────────────────────────────────────────────────────────
// Se mudar o nome da pasta no servidor, altere APENAS aqui.
define('APP_URL',  'http://localhost/projeto_final');
define('BASE_URL', '/projeto_final/');

// ── Configurações de e-mail (SMTP) ────────────────────────────────────────
define('MAIL_HOST',         'smtp.gmail.com');
define('MAIL_PORT',         587);
define('MAIL_USERNAME',     'icaromartinsazevedo@gmail.com');
define('MAIL_PASSWORD',     'moauigwridrpaftz');
define('MAIL_FROM_ADDRESS', 'icaromartinsazevedo@gmail.com');
define('MAIL_FROM_NAME',    'Ouvidoria do Grêmio Escolar - EEEP Dom Walfrido');

// ── Conexão PDO (singleton) ───────────────────────────────────────────────
function conectarPDO(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
