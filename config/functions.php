<?php
require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/config.php';
require_once LIB_PATH . '/src/PHPMailer.php';
require_once LIB_PATH . '/src/SMTP.php';
require_once LIB_PATH . '/src/Exception.php';

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
        header('Location: ' . BASE_URL . 'app/auth/login.php');
        exit;
    }
}

function exigirLoginUsuario(): void
{
    if (!usuarioLogado()) {
        flash('erro', 'Faça login para acessar sua conta.');
        header('Location: ' . BASE_URL . 'app/auth/login.php');
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

// ─── NOTIFICAÇÕES ───────────────────────────────────────────────────────────

function criarNotificacao(PDO $pdo, array $dados): void
{
    // Garante que as tabelas existem silenciosamente
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `notificacoes` (
              `IDnotif` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `IDusu` INT UNSIGNED DEFAULT NULL,
              `IDadm` INT UNSIGNED DEFAULT NULL,
              `tipo` VARCHAR(40) NOT NULL,
              `titulo` VARCHAR(120) NOT NULL,
              `mensagem` TEXT NOT NULL,
              `link` VARCHAR(255) DEFAULT NULL,
              `lida` TINYINT(1) NOT NULL DEFAULT 0,
              `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`IDnotif`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (PDOException $e) {}

    $stmt = $pdo->prepare('
        INSERT INTO notificacoes (IDusu, IDadm, tipo, titulo, mensagem, link)
        VALUES (:idusu, :idadm, :tipo, :titulo, :mensagem, :link)
    ');
    $stmt->execute([
        ':idusu'    => $dados['IDusu']    ?? null,
        ':idadm'    => $dados['IDadm']    ?? null,
        ':tipo'     => $dados['tipo']     ?? 'geral',
        ':titulo'   => $dados['titulo']   ?? '',
        ':mensagem' => $dados['mensagem'] ?? '',
        ':link'     => $dados['link']     ?? null,
    ]);
}

function contarNotificacoesNaoLidas(PDO $pdo, string $coluna, int $id): int
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notificacoes WHERE {$coluna} = :id AND lida = 0");
        $stmt->execute([':id' => $id]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function buscarNotificacoes(PDO $pdo, string $coluna, int $id, int $limite = 20): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM notificacoes
            WHERE {$coluna} = :id
            ORDER BY criado_em DESC
            LIMIT {$limite}
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function marcarNotificacoesLidas(PDO $pdo, string $coluna, int $id): void
{
    try {
        $stmt = $pdo->prepare("UPDATE notificacoes SET lida = 1 WHERE {$coluna} = :id AND lida = 0");
        $stmt->execute([':id' => $id]);
    } catch (PDOException $e) {}
}

// ─── RESPOSTAS ───────────────────────────────────────────────────────────────

function garantirTabelasExtras(PDO $pdo): void
{
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `respostas_manifest` (
              `IDresposta` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `IDmanifest` INT UNSIGNED NOT NULL,
              `IDadm` INT UNSIGNED DEFAULT NULL,
              `IDusu` INT UNSIGNED DEFAULT NULL,
              `mensagem` TEXT NOT NULL,
              `autor_nome` VARCHAR(80) NOT NULL,
              `autor_tipo` ENUM('adm','usuario') NOT NULL,
              `lida_pelo_usuario` TINYINT(1) NOT NULL DEFAULT 0,
              `lida_pelo_adm` TINYINT(1) NOT NULL DEFAULT 0,
              `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`IDresposta`),
              KEY `idx_manifest` (`IDmanifest`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `arquivos_manifest` (
              `IDarquivo` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `IDmanifest` INT UNSIGNED NOT NULL,
              `nome_original` VARCHAR(255) NOT NULL,
              `nome_arquivo` VARCHAR(255) NOT NULL,
              `tamanho` INT UNSIGNED NOT NULL,
              `mime_type` VARCHAR(100) NOT NULL,
              `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`IDarquivo`),
              KEY `idx_manifest` (`IDmanifest`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        // Colunas extras em tbmanifest
        try { $pdo->exec("ALTER TABLE tbmanifest ADD COLUMN data_ocorrencia DATE DEFAULT NULL"); } catch (\PDOException $e) {}
        try { $pdo->exec("ALTER TABLE tbmanifest ADD COLUMN util TINYINT(1) DEFAULT NULL"); } catch (\PDOException $e) {}
        try { $pdo->exec("ALTER TABLE tbusuarios ADD COLUMN foto_perfil VARCHAR(255) DEFAULT NULL"); } catch (\PDOException $e) {}
    } catch (PDOException $e) {}
}

function salvarArquivosManifestacao(PDO $pdo, int $idManifest, array $files): void
{
    $dir = STORAGE_MANIFESTACOES;
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $permitidos = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','txt','mp4','mp3'];
    $maxSize = 10 * 1024 * 1024; // 10 MB por arquivo

    foreach ($files['tmp_name'] as $i => $tmp) {
        if (empty($tmp) || !is_uploaded_file($tmp)) continue;
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($files['size'][$i] > $maxSize) continue;

        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $permitidos)) continue;

        $nomeArquivo = 'mf_' . $idManifest . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($tmp, $dir . $nomeArquivo)) {
            $stmt = $pdo->prepare('
                INSERT INTO arquivos_manifest (IDmanifest, nome_original, nome_arquivo, tamanho, mime_type)
                VALUES (:idm, :nom, :arq, :tam, :mime)
            ');
            $stmt->execute([
                ':idm'  => $idManifest,
                ':nom'  => $files['name'][$i],
                ':arq'  => $nomeArquivo,
                ':tam'  => $files['size'][$i],
                ':mime' => $files['type'][$i],
            ]);
        }
    }
}

function iconeArquivo(string $mime): string
{
    if (str_starts_with($mime, 'image/')) return 'fa-file-image';
    if ($mime === 'application/pdf') return 'fa-file-pdf';
    if (str_contains($mime, 'word')) return 'fa-file-word';
    if (str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet')) return 'fa-file-excel';
    if (str_starts_with($mime, 'video/')) return 'fa-file-video';
    if (str_starts_with($mime, 'audio/')) return 'fa-file-audio';
    return 'fa-file';
}

function formatarTamanho(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 0) . ' KB';
    return $bytes . ' B';
}

// ─── HELPERS DE APRESENTAÇÃO (centralizados — não duplicar nas páginas) ───

function classeStatus(string $status): string {
    return match($status) {
        'Recebida'     => 'status-recebida',
        'Em andamento' => 'status-andamento',
        'Resolvida'    => 'status-resolvida',
        default        => 'status-neutro',
    };
}

function iconeStatus(string $status): string {
    return match($status) {
        'Recebida'     => 'fa-inbox',
        'Em andamento' => 'fa-spinner',
        'Resolvida'    => 'fa-circle-check',
        default        => 'fa-question',
    };
}

function classeStatusAdm(?string $s): string {
    return classeStatus((string)$s);
}

function classeCursoAdm(?string $v): string {
    $v = (string)$v;
    if (str_contains($v, 'Informática')) return 'curso-informatica';
    if (str_contains($v, 'Saúde Bucal')) return 'curso-saude';
    if (str_contains($v, 'Energias'))    return 'curso-energias';
    if (str_contains($v, 'Enfermagem'))  return 'curso-enfermagem';
    return 'curso-neutro';
}

function classeStatusConta(string $s): string {
    return classeStatus($s);
}

function iconeNotificacao(string $tipo): string {
    return match($tipo) {
        'nova_resposta'     => 'fa-comment',
        'nova_manifestacao' => 'fa-paper-plane',
        'status_atualizado' => 'fa-circle-check',
        default             => 'fa-bell',
    };
}

// ─── E-MAIL DE CONFIRMAÇÃO DE PROTOCOLO ──────────────────────────────────

function enviarEmailProtocolo(
    string $emailDestino,
    string $nomeDestinatario,
    string $protocolo,
    string $assunto,
    string $tipo,
    string $linkAcompanhar
): bool {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($emailDestino);
        $mail->isHTML(true);
        $mail->Subject = 'Manifestação recebida — Protocolo ' . $protocolo;

        $saudacao = $nomeDestinatario !== 'Anônimo' ? "Olá, <strong>" . htmlspecialchars($nomeDestinatario, ENT_QUOTES, 'UTF-8') . "</strong>!" : "Olá!";
        $linkEsc  = htmlspecialchars($linkAcompanhar, ENT_QUOTES, 'UTF-8');
        $protEsc  = htmlspecialchars($protocolo,      ENT_QUOTES, 'UTF-8');
        $assuntoEsc = htmlspecialchars($assunto,      ENT_QUOTES, 'UTF-8');
        $tipoEsc  = htmlspecialchars($tipo,           ENT_QUOTES, 'UTF-8');

        $mail->Body = '
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#f4f5f0;font-family:Arial,sans-serif;">
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f0;padding:40px 20px;">
            <tr><td align="center">
              <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

                <!-- Header -->
                <tr>
                  <td style="background:linear-gradient(135deg,#0f4228,#1a6b40);padding:36px 40px;text-align:center;">
                    <p style="margin:0 0 8px;color:rgba(255,255,255,0.7);font-size:12px;letter-spacing:2px;text-transform:uppercase;">OUVIDORIA DO GRÊMIO ESCOLAR</p>
                    <h1 style="margin:0;color:#fff;font-size:26px;font-weight:900;">EEEP Dom Walfrido</h1>
                    <p style="margin:10px 0 0;color:rgba(255,255,255,0.8);font-size:14px;">Teixeira Vieira</p>
                  </td>
                </tr>

                <!-- Ícone de sucesso -->
                <tr>
                  <td style="padding:36px 40px 0;text-align:center;">
                    <div style="width:72px;height:72px;background:#ecfdf5;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:20px;">
                      <span style="font-size:36px;">✅</span>
                    </div>
                    <h2 style="margin:0 0 8px;color:#0f4228;font-size:22px;font-weight:800;">Manifestação recebida!</h2>
                    <p style="margin:0;color:#5a6055;font-size:15px;">' . $saudacao . ' Sua manifestação foi registrada com sucesso.</p>
                  </td>
                </tr>

                <!-- Card do protocolo -->
                <tr>
                  <td style="padding:28px 40px;">
                    <div style="background:#f0fdf4;border:2px solid #bbf7d0;border-radius:16px;padding:24px;text-align:center;">
                      <p style="margin:0 0 6px;color:#047857;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;">Seu protocolo de acompanhamento</p>
                      <p style="margin:0;color:#0f4228;font-size:28px;font-weight:900;letter-spacing:2px;">' . $protEsc . '</p>
                      <p style="margin:8px 0 0;color:#5a6055;font-size:12px;">Guarde este código — você precisará dele para acompanhar o andamento.</p>
                    </div>
                  </td>
                </tr>

                <!-- Detalhes -->
                <tr>
                  <td style="padding:0 40px 28px;">
                    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                      <tr style="background:#f9fafb;">
                        <td style="padding:12px 16px;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;width:40%">Tipo</td>
                        <td style="padding:12px 16px;font-size:14px;color:#1a1f1a;font-weight:600;">' . $tipoEsc . '</td>
                      </tr>
                      <tr>
                        <td style="padding:12px 16px;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;border-top:1px solid #e5e7eb">Assunto</td>
                        <td style="padding:12px 16px;font-size:14px;color:#1a1f1a;border-top:1px solid #e5e7eb">' . $assuntoEsc . '</td>
                      </tr>
                      <tr style="background:#f9fafb;">
                        <td style="padding:12px 16px;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;border-top:1px solid #e5e7eb">Status atual</td>
                        <td style="padding:12px 16px;font-size:14px;border-top:1px solid #e5e7eb"><span style="background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;padding:3px 12px;border-radius:999px;font-size:12px;font-weight:700;">Recebida</span></td>
                      </tr>
                    </table>
                  </td>
                </tr>

                <!-- CTA -->
                <tr>
                  <td style="padding:0 40px 36px;text-align:center;">
                    <a href="' . $linkEsc . '"
                       style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#e8820a,#f5a340);color:#fff;text-decoration:none;border-radius:12px;font-weight:700;font-size:15px;box-shadow:0 4px 16px rgba(232,130,10,.35);">
                      Acompanhar minha manifestação
                    </a>
                    <p style="margin:16px 0 0;color:#9ca3af;font-size:12px;">ou acesse diretamente pelo protocolo em <a href="' . $linkEsc . '" style="color:#1a6b40;">' . APP_URL . '</a></p>
                  </td>
                </tr>

                <!-- Footer do e-mail -->
                <tr>
                  <td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 40px;text-align:center;">
                    <p style="margin:0;color:#9ca3af;font-size:12px;line-height:1.6;">
                      EEEP Dom Walfrido Teixeira Vieira — Ouvidoria do Grêmio Escolar<br>
                      Av. Dr. Paulo de Almeida Sanford — Colina Boa Vista, Sobral - CE<br>
                      <span style="color:#d1d5db;">Este e-mail foi gerado automaticamente, por favor não responda.</span>
                    </p>
                  </td>
                </tr>

              </table>
            </td></tr>
          </table>
        </body>
        </html>';

        $mail->AltBody =
            "Manifestação recebida com sucesso!\n\n" .
            "Protocolo: {$protocolo}\n" .
            "Tipo: {$tipo}\n" .
            "Assunto: {$assunto}\n\n" .
            "Acompanhe pelo link: {$linkAcompanhar}\n\n" .
            "EEEP Dom Walfrido Teixeira Vieira — Ouvidoria do Grêmio Escolar";

        return $mail->send();
    } catch (Exception $e) {
        return false;
    }
}

// ─── E-MAIL DE ATUALIZAÇÃO DE STATUS ─────────────────────────────────────

function enviarEmailStatusAtualizado(
    string $emailDestino,
    string $nomeDestinatario,
    string $protocolo,
    string $statusAnterior,
    string $statusNovo,
    string $linkAcompanhar,
    ?string $mensagemAdm = null
): bool {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($emailDestino);
        $mail->isHTML(true);
        $mail->Subject = 'Status atualizado — Protocolo ' . $protocolo;

        $icone  = $statusNovo === 'Resolvida' ? '✅' : ($statusNovo === 'Em andamento' ? '🔄' : '📬');
        $corBg  = $statusNovo === 'Resolvida' ? '#ecfdf5' : ($statusNovo === 'Em andamento' ? '#eff6ff' : '#fff7ed');
        $corBd  = $statusNovo === 'Resolvida' ? '#bbf7d0' : ($statusNovo === 'Em andamento' ? '#bfdbfe' : '#fed7aa');
        $corTxt = $statusNovo === 'Resolvida' ? '#047857' : ($statusNovo === 'Em andamento' ? '#1d4ed8' : '#c2410c');
        $saudacao = $nomeDestinatario !== 'Anônimo' ? "Olá, <strong>" . htmlspecialchars($nomeDestinatario, ENT_QUOTES, 'UTF-8') . "</strong>!" : "Olá!";
        $linkEsc  = htmlspecialchars($linkAcompanhar, ENT_QUOTES, 'UTF-8');
        $protEsc  = htmlspecialchars($protocolo, ENT_QUOTES, 'UTF-8');
        $stNovo   = htmlspecialchars($statusNovo, ENT_QUOTES, 'UTF-8');
        $stAnt    = htmlspecialchars($statusAnterior, ENT_QUOTES, 'UTF-8');

        $blocoMensagem = '';
        if ($mensagemAdm) {
            $msgEsc = nl2br(htmlspecialchars($mensagemAdm, ENT_QUOTES, 'UTF-8'));
            $blocoMensagem = '
                <tr>
                  <td style="padding:0 40px 28px;">
                    <div style="background:#f0fdf4;border-left:4px solid #1a6b40;border-radius:0 12px 12px 0;padding:18px 20px;">
                      <p style="margin:0 0 6px;font-size:11px;font-weight:700;color:#047857;text-transform:uppercase;letter-spacing:1px;">Mensagem do Grêmio</p>
                      <p style="margin:0;font-size:14px;color:#1a1f1a;line-height:1.7;">' . $msgEsc . '</p>
                    </div>
                  </td>
                </tr>';
        }

        $mail->Body = '
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#f4f5f0;font-family:Arial,sans-serif;">
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f0;padding:40px 20px;">
            <tr><td align="center">
              <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
                <tr>
                  <td style="background:linear-gradient(135deg,#0f4228,#1a6b40);padding:36px 40px;text-align:center;">
                    <p style="margin:0 0 8px;color:rgba(255,255,255,0.7);font-size:12px;letter-spacing:2px;text-transform:uppercase;">OUVIDORIA DO GRÊMIO ESCOLAR</p>
                    <h1 style="margin:0;color:#fff;font-size:26px;font-weight:900;">EEEP Dom Walfrido</h1>
                  </td>
                </tr>
                <tr>
                  <td style="padding:36px 40px 20px;text-align:center;">
                    <div style="font-size:48px;margin-bottom:16px;">' . $icone . '</div>
                    <h2 style="margin:0 0 8px;color:#0f4228;font-size:22px;font-weight:800;">Status atualizado</h2>
                    <p style="margin:0;color:#5a6055;font-size:15px;">' . $saudacao . ' Sua manifestação foi atualizada.</p>
                  </td>
                </tr>
                <tr>
                  <td style="padding:0 40px 24px;">
                    <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:20px;text-align:center;">
                      <p style="margin:0 0 4px;color:#6b7280;font-size:12px;">Protocolo</p>
                      <p style="margin:0 0 16px;color:#0f4228;font-size:22px;font-weight:900;letter-spacing:1px;">' . $protEsc . '</p>
                      <div style="display:inline-flex;align-items:center;gap:12px;flex-wrap:wrap;justify-content:center;">
                        <span style="background:#f3f4f6;color:#6b7280;border:1px solid #e5e7eb;padding:5px 14px;border-radius:999px;font-size:13px;font-weight:600;">' . $stAnt . '</span>
                        <span style="color:#9ca3af;font-size:18px;">→</span>
                        <span style="background:' . $corBg . ';color:' . $corTxt . ';border:1px solid ' . $corBd . ';padding:5px 14px;border-radius:999px;font-size:13px;font-weight:700;">' . $stNovo . '</span>
                      </div>
                    </div>
                  </td>
                </tr>
                ' . $blocoMensagem . '
                <tr>
                  <td style="padding:0 40px 36px;text-align:center;">
                    <a href="' . $linkEsc . '"
                       style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#e8820a,#f5a340);color:#fff;text-decoration:none;border-radius:12px;font-weight:700;font-size:15px;">
                      Ver minha manifestação
                    </a>
                  </td>
                </tr>
                <tr>
                  <td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 40px;text-align:center;">
                    <p style="margin:0;color:#9ca3af;font-size:12px;line-height:1.6;">
                      EEEP Dom Walfrido Teixeira Vieira — Ouvidoria do Grêmio Escolar<br>
                      <span style="color:#d1d5db;">Este e-mail foi gerado automaticamente.</span>
                    </p>
                  </td>
                </tr>
              </table>
            </td></tr>
          </table>
        </body>
        </html>';

        $mail->AltBody =
            "Status da sua manifestação foi atualizado!\n\n" .
            "Protocolo: {$protocolo}\n" .
            "Status anterior: {$statusAnterior}\n" .
            "Status novo: {$statusNovo}\n" .
            ($mensagemAdm ? "\nMensagem do Grêmio:\n{$mensagemAdm}\n" : '') .
            "\nVer detalhes: {$linkAcompanhar}";

        return $mail->send();
    } catch (Exception $e) {
        return false;
    }
}

// ─── HISTÓRICO DE STATUS ──────────────────────────────────────────────────

function registrarHistoricoStatus(
    PDO $pdo,
    int $idManifest,
    ?int $idAdm,
    string $statusAnterior,
    string $statusNovo,
    ?string $observacao = null
): void {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `historico_status` (
              `IDhistorico`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `IDmanifest`      INT UNSIGNED NOT NULL,
              `IDadm`           INT UNSIGNED DEFAULT NULL,
              `status_anterior` VARCHAR(20) NOT NULL,
              `status_novo`     VARCHAR(20) NOT NULL,
              `observacao`      TEXT DEFAULT NULL,
              `criado_em`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`IDhistorico`),
              KEY `idx_hist_manifest` (`IDmanifest`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $stmt = $pdo->prepare("
            INSERT INTO historico_status
                (IDmanifest, IDadm, status_anterior, status_novo, observacao)
            VALUES
                (:idm, :ida, :sant, :snov, :obs)
        ");
        $stmt->execute([
            ':idm'  => $idManifest,
            ':ida'  => $idAdm,
            ':sant' => $statusAnterior,
            ':snov' => $statusNovo,
            ':obs'  => $observacao,
        ]);
    } catch (PDOException $e) {
        // Silencioso — histórico não deve travar o fluxo principal
    }
}

function buscarHistoricoStatus(PDO $pdo, int $idManifest): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT h.*, a.nome AS adm_nome
            FROM historico_status h
            LEFT JOIN tbadm a ON a.IDadm = h.IDadm
            WHERE h.IDmanifest = :id
            ORDER BY h.criado_em ASC
        ");
        $stmt->execute([':id' => $idManifest]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}
