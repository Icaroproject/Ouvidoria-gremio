<?php
/**
 * API: Polling de mensagens do chat
 * GET ?protocolo=GRE-...&desde_id=N
 * Retorna mensagens novas + status atual da manifestação
 */
require_once __DIR__ . '/../../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$protocolo = trim($_GET['protocolo'] ?? '');
$desdeId   = (int)($_GET['desde_id'] ?? 0);

if (!preg_match('/^GRE-\d{8}-[A-Z0-9]{6}$/', $protocolo)) {
    echo json_encode(['ok' => false, 'erro' => 'Protocolo inválido.']);
    exit;
}

try {
    $pdo = conectarPDO();

    // Buscar manifestação
    $stmt = $pdo->prepare('SELECT IDmanifest, status FROM tbmanifest WHERE protocolo = :p LIMIT 1');
    $stmt->execute([':p' => $protocolo]);
    $manifest = $stmt->fetch();

    if (!$manifest) {
        echo json_encode(['ok' => false, 'erro' => 'Protocolo não encontrado.']);
        exit;
    }

    $idManifest = (int)$manifest['IDmanifest'];
    $statusAtual = $manifest['status'];

    // Mensagens novas desde o último ID visto
    $stmtMsg = $pdo->prepare('
        SELECT IDresposta, autor_nome, autor_tipo, mensagem,
               DATE_FORMAT(criado_em, "%d/%m/%Y %H:%i") AS data_fmt
        FROM respostas_manifest
        WHERE IDmanifest = :id AND IDresposta > :desde
        ORDER BY criado_em ASC
    ');
    $stmtMsg->execute([':id' => $idManifest, ':desde' => $desdeId]);
    $mensagens = $stmtMsg->fetchAll();

    // Marcar mensagens do admin como lidas (se usuário estiver olhando)
    if (!empty($mensagens)) {
        $pdo->prepare("
            UPDATE respostas_manifest
            SET lida_pelo_usuario = 1
            WHERE IDmanifest = :id AND autor_tipo = 'adm' AND IDresposta > :desde
        ")->execute([':id' => $idManifest, ':desde' => $desdeId]);
    }

    echo json_encode([
        'ok'          => true,
        'status'      => $statusAtual,
        'mensagens'   => $mensagens,
        'ultimo_id'   => !empty($mensagens) ? (int)end($mensagens)['IDresposta'] : $desdeId,
    ]);

} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'erro' => 'Erro interno.']);
}
