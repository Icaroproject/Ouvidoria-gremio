<?php
require_once __DIR__ . '/includes_config.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';
require_once __DIR__ . '/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function flash(?string $type = null, ?string $message = null): ?array
{
    if ($type !== null && $message !== null) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        return null;
    }

    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function usuarioLogado(): bool
{
    return isset($_SESSION['usuario']) && !empty($_SESSION['usuario']['id']);
}

function administradorLogado(): bool
{
    return isset($_SESSION['admin']) && !empty($_SESSION['admin']['id']);
}

function exigirLoginAdm(): void
{
    if (!administradorLogado()) {
        flash('erro', 'Faça login como administrador para acessar o painel do Grêmio Escolar.');
        header('Location: login.php');
        exit;
    }
}

function exigirLoginUsuario(): void
{
    if (!usuarioLogado()) {
        flash('erro', 'Faça login para acessar sua conta.');
        header('Location: login.php');
        exit;
    }
}

function senhaConfere(string $senhaInformada, string $senhaBanco): bool
{
    if (password_verify($senhaInformada, $senhaBanco)) {
        return true;
    }

    return hash_equals($senhaBanco, $senhaInformada);
}

function topoAtivo(string $arquivoAtual, string $arquivoLink): string
{
    return basename($arquivoAtual) === $arquivoLink ? 'active' : '';
}

function tipoSlugParaDescricao(string $slug): string
{
    $mapa = [
        'sugestao' => 'Sugestão',
        'elogio' => 'Elogio',
        'reclamacao' => 'Reclamação',
        'denuncia' => 'Denúncia',
    ];

    return $mapa[$slug] ?? '';
}

function gerarProtocoloManifestacao(): string
{
    return 'GRE-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function validarCPF(string $cpf): bool
{
    $cpf = preg_replace('/\D/', '', $cpf);

    if (strlen($cpf) !== 11) {
        return false;
    }

    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += (int) $cpf[$i] * (10 - $i);
    }

    $dig1 = ($soma * 10) % 11;
    if ($dig1 === 10) {
        $dig1 = 0;
    }

    if ($dig1 !== (int) $cpf[9]) {
        return false;
    }

    $soma = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma += (int) $cpf[$i] * (11 - $i);
    }

    $dig2 = ($soma * 10) % 11;
    if ($dig2 === 10) {
        $dig2 = 0;
    }

    return $dig2 === (int) $cpf[10];
}

function cpfJaCadastrado(PDO $pdo, string $cpf, ?int $ignorarId = null): bool
{
    $cpf = preg_replace('/\D/', '', $cpf);

    if ($ignorarId) {
        $stmt = $pdo->prepare('SELECT IDusu FROM tbusuarios WHERE cpf = :cpf AND IDusu <> :id LIMIT 1');
        $stmt->execute([':cpf' => $cpf, ':id' => $ignorarId]);
    } else {
        $stmt = $pdo->prepare('SELECT IDusu FROM tbusuarios WHERE cpf = :cpf LIMIT 1');
        $stmt->execute([':cpf' => $cpf]);
    }

    return (bool) $stmt->fetch();
}

function emailExisteNoSistema(PDO $pdo, string $email): bool
{
    $stmtAdm = $pdo->prepare('SELECT IDadm FROM tbadm WHERE email = :email LIMIT 1');
    $stmtAdm->execute([':email' => $email]);
    if ($stmtAdm->fetch()) {
        return true;
    }

    $stmtUsu = $pdo->prepare('SELECT IDusu FROM tbusuarios WHERE email = :email LIMIT 1');
    $stmtUsu->execute([':email' => $email]);
    return (bool) $stmtUsu->fetch();
}

function criarTokenRecuperacao(PDO $pdo, string $email): string
{
    $tokenBruto = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $tokenBruto);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $stmt = $pdo->prepare('INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires_at)');
    $stmt->execute([
        ':email' => $email,
        ':token' => $tokenHash,
        ':expires_at' => $expiresAt,
    ]);

    return $tokenBruto;
}

function buscarResetValidoPorToken(PDO $pdo, string $tokenBruto): array|false
{
    $tokenHash = hash('sha256', $tokenBruto);

    $stmt = $pdo->prepare('
        SELECT *
        FROM password_resets
        WHERE token = :token
          AND used_at IS NULL
          AND expires_at >= NOW()
        ORDER BY id DESC
        LIMIT 1
    ');
    $stmt->execute([':token' => $tokenHash]);

    return $stmt->fetch();
}

function marcarTokenComoUsado(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

function atualizarSenhaPorEmail(PDO $pdo, string $email, string $novaSenhaHash): void
{
    $stmtAdm = $pdo->prepare('UPDATE tbadm SET senha = :senha WHERE email = :email');
    $stmtAdm->execute([
        ':senha' => $novaSenhaHash,
        ':email' => $email,
    ]);

    $stmtUsu = $pdo->prepare('UPDATE tbusuarios SET senha = :senha WHERE email = :email');
    $stmtUsu->execute([
        ':senha' => $novaSenhaHash,
        ':email' => $email,
    ]);
}

function enviarEmailRecuperacao(string $emailDestino, string $link): bool
{
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($emailDestino);
        $mail->isHTML(true);
        $mail->Subject = 'Recuperação de senha - Ouvidoria do Grêmio Escolar';

        $mail->Body = '
            <div style="font-family: Arial, sans-serif; color: #1a1f1a; line-height:1.6;">
                <h2 style="color:#1a6b40;">Recuperação de senha</h2>
                <p>Recebemos uma solicitação para redefinir a senha da sua conta.</p>
                <p>Clique no botão abaixo para continuar:</p>
                <p><a href="' . e($link) . '" style="display:inline-block;padding:12px 20px;background:#e8820a;color:#fff;text-decoration:none;border-radius:8px;font-weight:700;">Redefinir senha</a></p>
                <p>Se preferir, copie este link:</p>
                <p><a href="' . e($link) . '">' . e($link) . '</a></p>
                <p>Se você não solicitou esta alteração, ignore este e-mail.</p>
            </div>
        ';
        $mail->AltBody = "Recuperação de senha\n\nAcesse o link abaixo para redefinir sua senha:\n" . $link;

        return $mail->send();
    } catch (Exception $e) {
        return false;
    }
}

function setRememberMeCookies(string $email, string $tipoAcesso): void
{
    $expira = time() + (60 * 60 * 24 * 30);
    setcookie('remember_email', $email, $expira, '/');
    setcookie('remember_tipo', $tipoAcesso, $expira, '/');

    if (session_status() === PHP_SESSION_ACTIVE) {
        setcookie(session_name(), session_id(), $expira, '/');
    }
}

function clearRememberMeCookies(): void
{
    setcookie('remember_email', '', time() - 3600, '/');
    setcookie('remember_tipo', '', time() - 3600, '/');
}
