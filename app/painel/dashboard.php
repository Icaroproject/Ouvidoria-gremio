<?php
require_once __DIR__ . '/../../config/bootstrap.php';
exigirLoginAdm();

$pdo   = conectarPDO();
$idAdm = (int)$_SESSION['admin']['id'];

// ── Filtro de período ─────────────────────────────────────────────────────
$dashInicio = trim($_GET['dash_inicio'] ?? '');
$dashFim    = trim($_GET['dash_fim']    ?? '');
$wherePeriodo = '';
$paramsPeriodo = [];
if ($dashInicio) { $wherePeriodo .= " AND DATE(criado_em) >= :di"; $paramsPeriodo[':di'] = $dashInicio; }
if ($dashFim)    { $wherePeriodo .= " AND DATE(criado_em) <= :df"; $paramsPeriodo[':df'] = $dashFim; }

function queryDash(PDO $pdo, string $sql, array $params = []): mixed {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// ── Estatísticas gerais ──────────────────────────────────────────────────
$resumo = queryDash($pdo, "
    SELECT
        COUNT(*)                                     AS total,
        SUM(status = 'Recebida')                     AS recebidas,
        SUM(status = 'Em andamento')                 AS andamento,
        SUM(status = 'Resolvida')                    AS resolvidas,
        SUM(nome_manifestante = 'Anônimo')           AS anonimas,
        SUM(nome_manifestante <> 'Anônimo')          AS identificadas,
        SUM(DATE(criado_em) = CURDATE())             AS hoje,
        SUM(criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS semana
    FROM tbmanifest WHERE 1=1 $wherePeriodo
", $paramsPeriodo)->fetch() ?: [];

// ── Por tipo ─────────────────────────────────────────────────────────────
$porTipo = queryDash($pdo, "
    SELECT t.descricao AS tipo, COUNT(*) AS total
    FROM tbmanifest m
    INNER JOIN tipos t ON t.IDtipo = m.IDtipo
    WHERE 1=1 $wherePeriodo
    GROUP BY t.descricao ORDER BY total DESC
", $paramsPeriodo)->fetchAll();

// ── Por status ────────────────────────────────────────────────────────────
$porStatus = queryDash($pdo, "
    SELECT status, COUNT(*) AS total FROM tbmanifest WHERE 1=1 $wherePeriodo GROUP BY status
", $paramsPeriodo)->fetchAll();

// ── Por curso/turma ───────────────────────────────────────────────────────
$porCurso = queryDash($pdo, "
    SELECT
        CASE
            WHEN turma_setor LIKE '%Informática%'       THEN 'Informática'
            WHEN turma_setor LIKE '%Saúde Bucal%'       THEN 'Saúde Bucal'
            WHEN turma_setor LIKE '%Energias%'          THEN 'Energias Renováveis'
            WHEN turma_setor LIKE '%Enfermagem%'        THEN 'Enfermagem'
            ELSE 'Não informado'
        END AS curso,
        COUNT(*) AS total
    FROM tbmanifest WHERE 1=1 $wherePeriodo
    GROUP BY curso ORDER BY total DESC
", $paramsPeriodo)->fetchAll();

// ── Evolução últimos 30 dias (ou período selecionado) ─────────────────────
$evolucao = queryDash($pdo, "
    SELECT DATE(criado_em) AS dia, COUNT(*) AS total
    FROM tbmanifest
    WHERE criado_em >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) $wherePeriodo
    GROUP BY dia ORDER BY dia ASC
", $paramsPeriodo)->fetchAll();

// ── Últimas 5 manifestações ───────────────────────────────────────────────
$ultimas = $pdo->query("
    SELECT m.IDmanifest, m.protocolo, m.assunto, m.status,
           m.nome_manifestante, m.criado_em, t.descricao AS tipo
    FROM tbmanifest m
    INNER JOIN tipos t ON t.IDtipo = m.IDtipo
    ORDER BY m.criado_em DESC
    LIMIT 5
")->fetchAll();

// ── Respostas pendentes do ADM ────────────────────────────────────────────
$pendentes = 0;
try {
    $pendentes = (int)$pdo->query("
        SELECT COUNT(DISTINCT IDmanifest) FROM respostas_manifest
        WHERE autor_tipo='usuario' AND lida_pelo_adm=0
    ")->fetchColumn();
} catch(Exception $e){}

// ── Notificações não lidas ────────────────────────────────────────────────
$notifsAdm = contarNotificacoesNaoLidas($pdo, 'IDadm', $idAdm);

$tituloPagina = 'Dashboard — Ouvidoria do Grêmio Escolar';
require_once __DIR__ . '/../../includes/header.php';

// Preparar dados para os gráficos (JSON)
$jsonTipos    = json_encode(array_column($porTipo,   'tipo'));
$jsonTiposVal = json_encode(array_map('intval', array_column($porTipo, 'total')));
$jsonStatus   = json_encode(array_column($porStatus, 'status'));
$jsonStatusVal= json_encode(array_map('intval', array_column($porStatus, 'total')));
$jsonCursos   = json_encode(array_column($porCurso,  'curso'));
$jsonCursosVal= json_encode(array_map('intval', array_column($porCurso, 'total')));

// Evolução: preencher todos os dias dos últimos 30
$dias = []; $vals = [];
$evMap = array_column($evolucao, 'total', 'dia');
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $dias[] = date('d/m', strtotime($d));
    $vals[] = (int)($evMap[$d] ?? 0);
}
$jsonDias = json_encode($dias);
$jsonVals = json_encode($vals);
?>

<div class="page-header">
  <div class="page-header-inner">
    <span class="section-label">PAINEL ADMINISTRATIVO</span>
    <h1>Dash<em>board</em></h1>
    <p>Visão geral das manifestações da Ouvidoria do Grêmio Escolar.</p>
  </div>
</div>

<section class="form-section">
<div class="container-fluid px-3 px-md-4" style="max-width:1300px;">

  <!-- Filtro de período -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-12 col-sm-auto">
          <label class="form-label small fw-semibold mb-1">Período — início</label>
          <input type="date" name="dash_inicio" class="form-control form-control-sm" value="<?= e($dashInicio) ?>">
        </div>
        <div class="col-12 col-sm-auto">
          <label class="form-label small fw-semibold mb-1">Fim</label>
          <input type="date" name="dash_fim" class="form-control form-control-sm" value="<?= e($dashFim) ?>">
        </div>
        <div class="col-auto d-flex gap-2">
          <button type="submit" class="btn btn-sm" style="background:var(--verde);color:#fff">
            <i class="fa-solid fa-chart-line me-1"></i>Aplicar
          </button>
          <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">Limpar</a>
        </div>
        <?php if ($dashInicio || $dashFim): ?>
          <div class="col-auto align-self-end">
            <span class="badge" style="background:#e8f5ee;color:#1a6b40;font-size:.8rem;padding:6px 12px;border-radius:999px;">
              <i class="fa-solid fa-calendar-check me-1"></i>
              <?= $dashInicio ? date('d/m/Y', strtotime($dashInicio)) : '…' ?>
              →
              <?= $dashFim ? date('d/m/Y', strtotime($dashFim)) : '…' ?>
            </span>
          </div>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Cards de resumo rápido -->
  <div class="row g-3 mb-4">
    <?php
    $cards = [
      ['label'=>'Total','val'=>$resumo['total']??0,       'icon'=>'fa-inbox',          'bg'=>'#f8fafc', 'cor'=>'var(--verde-escuro)', 'borda'=>'#e5e7eb'],
      ['label'=>'Recebidas','val'=>$resumo['recebidas']??0,'icon'=>'fa-envelope-open', 'bg'=>'#fff7ed', 'cor'=>'#c2410c',            'borda'=>'#fed7aa'],
      ['label'=>'Em andamento','val'=>$resumo['andamento']??0,'icon'=>'fa-spinner',    'bg'=>'#eff6ff', 'cor'=>'#1d4ed8',            'borda'=>'#bfdbfe'],
      ['label'=>'Resolvidas','val'=>$resumo['resolvidas']??0,'icon'=>'fa-circle-check','bg'=>'#ecfdf5', 'cor'=>'#047857',            'borda'=>'#bbf7d0'],
      ['label'=>'Hoje','val'=>$resumo['hoje']??0,         'icon'=>'fa-calendar-day',   'bg'=>'#fdf4ff', 'cor'=>'#7e22ce',            'borda'=>'#e9d5ff'],
      ['label'=>'Esta semana','val'=>$resumo['semana']??0,'icon'=>'fa-calendar-week',  'bg'=>'#fff1f2', 'cor'=>'#be123c',            'borda'=>'#fecdd3'],
      ['label'=>'Pendentes resposta','val'=>$pendentes,   'icon'=>'fa-comment-dots',   'bg'=>'#fffbeb', 'cor'=>'#b45309',            'borda'=>'#fde68a'],
      ['label'=>'Notif. não lidas','val'=>$notifsAdm,     'icon'=>'fa-bell',           'bg'=>'#f0fdf4', 'cor'=>'var(--verde)',        'borda'=>'#bbf7d0'],
    ];
    foreach ($cards as $c): ?>
    <div class="col-6 col-md-3 col-xl-3">
      <div class="card border-0 shadow-sm h-100 dash-card"
           style="background:<?= $c['bg'] ?>;border:1px solid <?= $c['borda'] ?> !important;">
        <div class="card-body p-3 d-flex align-items-center gap-3">
          <div class="dash-card-icon" style="background:<?= $c['borda'] ?>;color:<?= $c['cor'] ?>">
            <i class="fa-solid <?= $c['icon'] ?>"></i>
          </div>
          <div>
            <div class="dash-card-label"><?= $c['label'] ?></div>
            <div class="dash-card-val" style="color:<?= $c['cor'] ?>"><?= (int)$c['val'] ?></div>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Linha 1: Gráfico de linha (evolução) + Donut (status) -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-lg-8">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body p-4">
          <h6 class="dash-chart-title"><i class="fa-solid fa-chart-line me-2" style="color:var(--verde)"></i>Manifestações — últimos 30 dias</h6>
          <div class="dash-chart-wrap">
            <canvas id="chartEvolucao"></canvas>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body p-4">
          <h6 class="dash-chart-title"><i class="fa-solid fa-chart-pie me-2" style="color:var(--laranja)"></i>Por status</h6>
          <div class="dash-chart-wrap dash-chart-wrap--sm">
            <canvas id="chartStatus"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Linha 2: Barras (por tipo) + Barras horizontais (por curso) -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body p-4">
          <h6 class="dash-chart-title"><i class="fa-solid fa-chart-bar me-2" style="color:#7e22ce"></i>Por tipo de manifestação</h6>
          <div class="dash-chart-wrap">
            <canvas id="chartTipos"></canvas>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body p-4">
          <h6 class="dash-chart-title"><i class="fa-solid fa-school me-2" style="color:#0369a1"></i>Por curso / turma</h6>
          <div class="dash-chart-wrap">
            <canvas id="chartCursos"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Linha 3: Anônimo vs Identificado + Últimas manifestações -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body p-4">
          <h6 class="dash-chart-title"><i class="fa-solid fa-user-shield me-2" style="color:#be185d"></i>Anônimo vs Identificado</h6>
          <div class="dash-chart-wrap dash-chart-wrap--sm">
            <canvas id="chartAnonimo"></canvas>
          </div>
          <div class="d-flex justify-content-center gap-4 mt-3">
            <div class="text-center">
              <div class="fw-bold" style="font-size:1.5rem;color:#3b5bdb"><?= (int)($resumo['anonimas']??0) ?></div>
              <div class="text-muted small">Anônimas</div>
            </div>
            <div class="text-center">
              <div class="fw-bold" style="font-size:1.5rem;color:var(--verde)"><?= (int)($resumo['identificadas']??0) ?></div>
              <div class="text-muted small">Identificadas</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-8">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
          <h6 class="mb-0 dash-chart-title mb-0"><i class="fa-solid fa-clock-rotate-left me-2" style="color:var(--verde)"></i>Últimas manifestações</h6>
          <a href="<?= $_base ?>app/painel/adm.php" class="btn btn-sm" style="background:var(--verde);color:#fff;border-radius:999px;font-size:.78rem">Ver todas</a>
        </div>
        <div class="card-body p-0">
          <?php if (empty($ultimas)): ?>
            <div class="text-center py-4 text-muted small">Nenhuma manifestação ainda.</div>
          <?php else: ?>
            <div class="list-group list-group-flush">
              <?php foreach ($ultimas as $u): ?>
                <a href="<?= $_base ?>app/painel/adm.php#manifest-<?= (int)$u['IDmanifest'] ?>"
                   class="list-group-item list-group-item-action px-4 py-3 border-0 border-bottom">
                  <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="flex-grow-1 overflow-hidden">
                      <div class="fw-semibold text-truncate" style="font-size:.88rem;color:var(--verde-escuro)"><?= e($u['assunto']) ?></div>
                      <div class="text-muted" style="font-size:.78rem">
                        <span class="me-2"><?= e($u['tipo']) ?></span>·
                        <span class="ms-2"><?= e($u['nome_manifestante']) ?></span>·
                        <span class="ms-2"><?= date('d/m/Y H:i', strtotime($u['criado_em'])) ?></span>
                      </div>
                    </div>
                    <span class="<?= classeStatus($u['status']) ?> flex-shrink-0"
                          style="padding:3px 10px;border-radius:999px;font-size:.72rem;font-weight:700;display:inline-block">
                      <?= e($u['status']) ?>
                    </span>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

</div>
</section>


<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.color = '#5a6055';

const verde      = '#1a6b40';
const laranja    = '#e8820a';
const azul       = '#1d4ed8';
const roxo       = '#7e22ce';
const vermelho   = '#be123c';
const amarelo    = '#b45309';
const cinzaFundo = '#f4f5f0';

// ── Gráfico de linha: evolução 30 dias ──────────────────────────────────
new Chart(document.getElementById('chartEvolucao'), {
  type: 'line',
  data: {
    labels: <?= $jsonDias ?>,
    datasets: [{
      label: 'Manifestações',
      data: <?= $jsonVals ?>,
      borderColor: verde,
      backgroundColor: 'rgba(26,107,64,.10)',
      borderWidth: 2.5,
      pointBackgroundColor: verde,
      pointRadius: 3,
      pointHoverRadius: 6,
      fill: true,
      tension: 0.4,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 }, grid: { color: '#f0f0f0' } },
      x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } }
    }
  }
});

// ── Donut: por status ────────────────────────────────────────────────────
new Chart(document.getElementById('chartStatus'), {
  type: 'doughnut',
  data: {
    labels: <?= $jsonStatus ?>,
    datasets: [{
      data: <?= $jsonStatusVal ?>,
      backgroundColor: [vermelho, azul, verde, '#9ca3af'],
      borderWidth: 2,
      borderColor: '#fff',
      hoverOffset: 8,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '65%',
    plugins: {
      legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14, font: { size: 12 } } }
    }
  }
});

// ── Barras: por tipo ─────────────────────────────────────────────────────
new Chart(document.getElementById('chartTipos'), {
  type: 'bar',
  data: {
    labels: <?= $jsonTipos ?>,
    datasets: [{
      label: 'Manifestações',
      data: <?= $jsonTiposVal ?>,
      backgroundColor: [verde, laranja, azul, roxo],
      borderRadius: 8,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f0f0f0' } },
      x: { grid: { display: false } }
    }
  }
});

// ── Barras horizontais: por curso ────────────────────────────────────────
new Chart(document.getElementById('chartCursos'), {
  type: 'bar',
  data: {
    labels: <?= $jsonCursos ?>,
    datasets: [{
      label: 'Manifestações',
      data: <?= $jsonCursosVal ?>,
      backgroundColor: [azul, verde, amarelo, vermelho, '#9ca3af'],
      borderRadius: 8,
      borderSkipped: false,
    }]
  },
  options: {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f0f0f0' } },
      y: { grid: { display: false } }
    }
  }
});

// ── Donut: anônimo vs identificado ───────────────────────────────────────
new Chart(document.getElementById('chartAnonimo'), {
  type: 'doughnut',
  data: {
    labels: ['Anônimas', 'Identificadas'],
    datasets: [{
      data: [<?= (int)($resumo['anonimas']??0) ?>, <?= (int)($resumo['identificadas']??0) ?>],
      backgroundColor: [azul, verde],
      borderWidth: 2,
      borderColor: '#fff',
      hoverOffset: 8,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '65%',
    plugins: {
      legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14, font: { size: 12 } } }
    }
  }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
