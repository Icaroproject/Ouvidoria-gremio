<?php
require_once __DIR__ . '/includes_functions.php';
exigirLoginAdm();

$pdo = conectarPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'atualizar_manifestacao') {
    $idManifest = (int) ($_POST['id_manifest'] ?? 0);
    $novoStatus = trim($_POST['status'] ?? '');
    $novoFeedback = trim($_POST['feedback'] ?? '');

    $statusPermitidos = ['Recebida', 'Em andamento', 'Resolvida'];

    if ($idManifest <= 0) {
        flash('erro', 'Manifestação inválida.');
        header('Location: adm.php');
        exit;
    }

    if (!in_array($novoStatus, $statusPermitidos, true)) {
        flash('erro', 'Status inválido.');
        header('Location: adm.php');
        exit;
    }

    $stmtUpdate = $pdo->prepare("
        UPDATE tbmanifest
        SET
            status = :status,
            feedback = :feedback,
            IDadm = :idadm
        WHERE IDmanifest = :idmanifest
    ");

    $stmtUpdate->bindValue(':status', $novoStatus, PDO::PARAM_STR);
    $stmtUpdate->bindValue(':feedback', $novoFeedback !== '' ? $novoFeedback : null, $novoFeedback !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmtUpdate->bindValue(':idadm', (int) ($_SESSION['admin']['id'] ?? 0), PDO::PARAM_INT);
    $stmtUpdate->bindValue(':idmanifest', $idManifest, PDO::PARAM_INT);
    $stmtUpdate->execute();

    flash('sucesso', 'Manifestação atualizada com sucesso.');
    header('Location: adm.php');
    exit;
}

$filtroTipo = trim($_GET['tipo'] ?? '');
$filtroStatus = trim($_GET['status'] ?? '');
$filtroTurma = trim($_GET['turma_setor'] ?? '');

$sql = "
    SELECT
        m.*,
        t.descricao AS tipo_descricao
    FROM tbmanifest m
    INNER JOIN tipos t ON t.IDtipo = m.IDtipo
    WHERE 1=1
";

$params = [];

if ($filtroTipo !== '') {
    $sql .= " AND t.descricao = :tipo";
    $params[':tipo'] = $filtroTipo;
}

if ($filtroStatus !== '') {
    $sql .= " AND m.status = :status";
    $params[':status'] = $filtroStatus;
}

if ($filtroTurma !== '') {
    $sql .= " AND m.turma_setor = :turma_setor";
    $params[':turma_setor'] = $filtroTurma;
}

$sql .= " ORDER BY m.criado_em DESC, m.IDmanifest DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$manifestacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtResumo = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Recebida' THEN 1 ELSE 0 END) AS recebidas,
        SUM(CASE WHEN status = 'Em andamento' THEN 1 ELSE 0 END) AS andamento,
        SUM(CASE WHEN status = 'Resolvida' THEN 1 ELSE 0 END) AS resolvidas
    FROM tbmanifest
");
$resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC) ?: [
    'total' => 0,
    'recebidas' => 0,
    'andamento' => 0,
    'resolvidas' => 0,
];

function classeCursoAdm(?string $valor): string {
    $valor = (string) $valor;
    if (str_contains($valor, 'Informática')) return 'curso-informatica';
    if (str_contains($valor, 'Saúde Bucal')) return 'curso-saude';
    if (str_contains($valor, 'Energias')) return 'curso-energias';
    if (str_contains($valor, 'Enfermagem')) return 'curso-enfermagem';
    return 'curso-neutro';
}

function classeStatusAdm(?string $status): string {
    return match ((string) $status) {
        'Recebida' => 'status-recebida',
        'Em andamento' => 'status-andamento',
        'Resolvida' => 'status-resolvida',
        default => 'status-neutro',
    };
}

$tituloPagina = 'Área ADM — Ouvidoria do Grêmio Escolar';
require_once __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header-inner">
        <span class="section-label">PAINEL ADMINISTRATIVO</span>
        <h1>Área <em>ADM</em></h1>
        <p>Gerencie as manifestações da Ouvidoria do Grêmio Escolar da EEEP Dom Walfrido Teixeira Vieira.</p>
    </div>
</div>

<section class="form-section">
    <div class="form-card">

        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:28px;">
            <div>
                <h2 style="font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:900;color:var(--verde-escuro);margin:0 0 8px 0;">
                    Painel de manifestações
                </h2>
                <p style="margin:0;color:var(--texto-suave);font-size:0.95rem;">
                    Acompanhe, filtre e atualize os registros enviados pela comunidade escolar.
                </p>
            </div>

            <a href="logout.php" class="topbar-btn topbar-btn-login" style="text-decoration:none;">
                Sair
            </a>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:28px;">
            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:18px;padding:18px;">
                <div style="font-size:0.85rem;color:#6b7280;">Total</div>
                <div style="font-size:2rem;font-weight:800;color:var(--verde-escuro);"><?= (int) $resumo['total'] ?></div>
            </div>
            <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:18px;padding:18px;">
                <div style="font-size:0.85rem;color:#9a3412;">Recebidas</div>
                <div style="font-size:2rem;font-weight:800;color:#c2410c;"><?= (int) $resumo['recebidas'] ?></div>
            </div>
            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:18px;padding:18px;">
                <div style="font-size:0.85rem;color:#1d4ed8;">Em andamento</div>
                <div style="font-size:2rem;font-weight:800;color:#1d4ed8;"><?= (int) $resumo['andamento'] ?></div>
            </div>
            <div style="background:#ecfdf5;border:1px solid #bbf7d0;border-radius:18px;padding:18px;">
                <div style="font-size:0.85rem;color:#047857;">Resolvidas</div>
                <div style="font-size:2rem;font-weight:800;color:#047857;"><?= (int) $resumo['resolvidas'] ?></div>
            </div>
        </div>

        <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:28px;">
            <select name="tipo" class="form-control" style="max-width:220px;">
                <option value="">Todos os tipos</option>
                <option value="Sugestão" <?= $filtroTipo === 'Sugestão' ? 'selected' : '' ?>>Sugestão</option>
                <option value="Elogio" <?= $filtroTipo === 'Elogio' ? 'selected' : '' ?>>Elogio</option>
                <option value="Reclamação" <?= $filtroTipo === 'Reclamação' ? 'selected' : '' ?>>Reclamação</option>
                <option value="Denúncia" <?= $filtroTipo === 'Denúncia' ? 'selected' : '' ?>>Denúncia</option>
            </select>

            <select name="status" class="form-control" style="max-width:220px;">
                <option value="">Todos os status</option>
                <option value="Recebida" <?= $filtroStatus === 'Recebida' ? 'selected' : '' ?>>Recebida</option>
                <option value="Em andamento" <?= $filtroStatus === 'Em andamento' ? 'selected' : '' ?>>Em andamento</option>
                <option value="Resolvida" <?= $filtroStatus === 'Resolvida' ? 'selected' : '' ?>>Resolvida</option>
            </select>

            <select name="turma_setor" class="form-control" style="max-width:260px;">
                <option value="">Todas as turmas/cursos</option>

                <optgroup label="Informática">
                    <?php foreach (['1°Informática', '2°Informática', '3°Informática'] as $opcao): ?>
                        <option value="<?= e($opcao) ?>" <?= $filtroTurma === $opcao ? 'selected' : '' ?>>
                            <?= e($opcao) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>

                <optgroup label="Saúde Bucal">
                    <?php foreach (['1°Saúde Bucal', '2°Saúde Bucal', '3°Saúde Bucal'] as $opcao): ?>
                        <option value="<?= e($opcao) ?>" <?= $filtroTurma === $opcao ? 'selected' : '' ?>>
                            <?= e($opcao) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>

                <optgroup label="Energias Renováveis">
                    <?php foreach (['1°Energias renováveis', '2°Energias renováveis', '3°Energias renováveis'] as $opcao): ?>
                        <option value="<?= e($opcao) ?>" <?= $filtroTurma === $opcao ? 'selected' : '' ?>>
                            <?= e($opcao) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>

                <optgroup label="Enfermagem">
                    <?php foreach (['1°Enfermagem', '2°Enfermagem', '3°Enfermagem'] as $opcao): ?>
                        <option value="<?= e($opcao) ?>" <?= $filtroTurma === $opcao ? 'selected' : '' ?>>
                            <?= e($opcao) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            </select>

            <button type="submit" class="btn-submit" style="width:auto;padding:12px 20px;">
                Filtrar
            </button>

            <a href="adm.php" class="topbar-btn topbar-btn-login" style="text-decoration:none;">
                Limpar
            </a>
        </form>

        <?php if (empty($manifestacoes)): ?>
            <div style="padding:24px;border:1px dashed #d1d5db;border-radius:18px;background:#fafafa;color:#6b7280;text-align:center;">
                Nenhuma manifestação encontrada com os filtros selecionados.
            </div>
        <?php else: ?>
            <div style="display:grid;gap:20px;">
                <?php foreach ($manifestacoes as $manifestacao): ?>
                    <div style="border:1px solid #e5e7eb;border-radius:22px;padding:22px;background:#fff;box-shadow:0 8px 24px rgba(0,0,0,0.04);">
                        
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
                            <div>
                                <div style="font-size:0.78rem;letter-spacing:1px;text-transform:uppercase;color:#6b7280;margin-bottom:6px;">
                                    Protocolo
                                </div>
                                <div style="font-size:1.1rem;font-weight:800;color:var(--verde-escuro);">
                                    <?= e($manifestacao['protocolo'] ?? 'Sem protocolo') ?>
                                </div>
                            </div>

                            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                <span class="badge-curso <?= e(classeCursoAdm($manifestacao['turma_setor'] ?? '')) ?>">
                                    <?= e($manifestacao['turma_setor'] ?: 'Não informado') ?>
                                </span>

                                <span class="<?= e(classeStatusAdm($manifestacao['status'] ?? '')) ?>" style="display:inline-flex;align-items:center;padding:6px 12px;border-radius:999px;font-size:0.8rem;font-weight:700;">
                                    <?= e($manifestacao['status'] ?? 'Não definido') ?>
                                </span>
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-bottom:18px;">
                            <div>
                                <strong>Tipo:</strong><br>
                                <span><?= e($manifestacao['tipo_descricao'] ?? 'Não informado') ?></span>
                            </div>
                            <div>
                                <strong>Nome:</strong><br>
                                <span><?= e($manifestacao['nome_manifestante'] ?? 'Anônimo') ?></span>
                            </div>
                            <div>
                                <strong>Perfil:</strong><br>
                                <span><?= e($manifestacao['perfil_manifestante'] ?? 'Não informado') ?></span>
                            </div>
                            <div>
                                <strong>Contato:</strong><br>
                                <span><?= e($manifestacao['contato'] ?? 'Não informado') ?></span>
                            </div>
                            <div>
                                <strong>Setor relacionado:</strong><br>
                                <span><?= e($manifestacao['setor_relacionado'] ?? 'Não informado') ?></span>
                            </div>
                            <div>
                                <strong>Data:</strong><br>
                                <span>
                                    <?= !empty($manifestacao['criado_em']) ? date('d/m/Y H:i', strtotime($manifestacao['criado_em'])) : 'Não informada' ?>
                                </span>
                            </div>
                        </div>

                        <div style="margin-bottom:16px;">
                            <strong>Assunto:</strong>
                            <div style="margin-top:6px;padding:14px;border-radius:14px;background:#f9fafb;border:1px solid #e5e7eb;">
                                <?= e($manifestacao['assunto'] ?? '') ?>
                            </div>
                        </div>

                        <div style="margin-bottom:18px;">
                            <strong>Descrição:</strong>
                            <div style="margin-top:6px;padding:14px;border-radius:14px;background:#f9fafb;border:1px solid #e5e7eb;white-space:pre-wrap;">
                                <?= e($manifestacao['manifest'] ?? '') ?>
                            </div>
                        </div>

                        <form method="post" style="border-top:1px solid #e5e7eb;padding-top:18px;">
                            <input type="hidden" name="acao" value="atualizar_manifestacao">
                            <input type="hidden" name="id_manifest" value="<?= (int) $manifestacao['IDmanifest'] ?>">

                            <div style="display:grid;grid-template-columns:220px 1fr;gap:16px;align-items:start;">
                                <div class="form-group" style="margin:0;">
                                    <label for="status_<?= (int) $manifestacao['IDmanifest'] ?>">Status</label>
                                    <select
                                        id="status_<?= (int) $manifestacao['IDmanifest'] ?>"
                                        name="status"
                                        class="form-control"
                                    >
                                        <option value="Recebida" <?= ($manifestacao['status'] ?? '') === 'Recebida' ? 'selected' : '' ?>>Recebida</option>
                                        <option value="Em andamento" <?= ($manifestacao['status'] ?? '') === 'Em andamento' ? 'selected' : '' ?>>Em andamento</option>
                                        <option value="Resolvida" <?= ($manifestacao['status'] ?? '') === 'Resolvida' ? 'selected' : '' ?>>Resolvida</option>
                                    </select>
                                </div>

                                <div class="form-group" style="margin:0;">
                                    <label for="feedback_<?= (int) $manifestacao['IDmanifest'] ?>">Feedback / resposta do ADM</label>
                                    <textarea
                                        id="feedback_<?= (int) $manifestacao['IDmanifest'] ?>"
                                        name="feedback"
                                        class="form-control"
                                        rows="4"
                                        placeholder="Digite aqui a resposta, encaminhamento ou retorno para a manifestação"
                                    ><?= e($manifestacao['feedback'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <div style="display:flex;justify-content:flex-end;margin-top:14px;">
                                <button type="submit" class="btn-submit" style="width:auto;padding:12px 20px;">
                                    Salvar atualização
                                </button>
                            </div>
                        </form>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</section>

<style>
.badge-curso,
.badge-curso-preview {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 700;
    line-height: 1.2;
    border: 1px solid transparent;
}

.curso-informatica {
    background: #e8f1ff;
    color: #1d4ed8;
    border-color: #bfd3ff;
}

.curso-saude {
    background: #ecfdf5;
    color: #047857;
    border-color: #b7ebd2;
}

.curso-energias {
    background: #fff7e6;
    color: #b45309;
    border-color: #f7d9a3;
}

.curso-enfermagem {
    background: #fdf2f8;
    color: #be185d;
    border-color: #f3bfd5;
}

.curso-neutro {
    background: #f3f4f6;
    color: #4b5563;
    border-color: #d1d5db;
}

.status-recebida {
    background: #fff7ed;
    color: #c2410c;
    border: 1px solid #fed7aa;
}

.status-andamento {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}

.status-resolvida {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #bbf7d0;
}

.status-neutro {
    background: #f3f4f6;
    color: #4b5563;
    border: 1px solid #d1d5db;
}
</style>

<?php require_once __DIR__ . '/footer.php'; ?>