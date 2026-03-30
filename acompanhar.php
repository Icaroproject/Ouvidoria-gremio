<?php
require_once __DIR__ . '/includes_functions.php';

$protocolo = trim($_GET['protocolo'] ?? ($_POST['protocolo'] ?? ''));
$manifestacao = null;
$erro = null;

// API endpoint para validação em tempo real via AJAX
if (isset($_GET['ajax_check']) && $_GET['ajax_check'] === '1') {
    header('Content-Type: application/json');
    $p = trim($_GET['protocolo'] ?? '');
    if ($p === '') {
        echo json_encode(['status' => 'empty']);
        exit;
    }
    if (!preg_match('/^GRE-\d{8}-[A-Z0-9]{6}$/', $p)) {
        echo json_encode(['status' => 'invalid']);
        exit;
    }
    try {
        $pdo = conectarPDO();
        $stmt = $pdo->prepare('SELECT IDmanifest, protocolo, status FROM tbmanifest WHERE protocolo = :protocolo LIMIT 1');
        $stmt->execute([':protocolo' => $p]);
        $row = $stmt->fetch();
        if ($row) {
            echo json_encode(['status' => 'found', 'manifest_status' => $row['status']]);
        } else {
            echo json_encode(['status' => 'not_found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

// Marcação de "foi útil"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['util'])) {
    $idManifest = (int) ($_POST['id_manifest'] ?? 0);
    $util = $_POST['util'] === 'sim' ? 1 : 0;
    if ($idManifest > 0) {
        try {
            $pdo = conectarPDO();
            // Adiciona coluna util se não existir (silencioso)
            @$pdo->exec("ALTER TABLE tbmanifest ADD COLUMN util TINYINT(1) DEFAULT NULL");
            $stmt = $pdo->prepare('UPDATE tbmanifest SET util = :util WHERE IDmanifest = :id');
            $stmt->execute([':util' => $util, ':id' => $idManifest]);
            flash('sucesso', $util ? 'Obrigado pelo seu feedback!' : 'Feedback registrado.');
        } catch (PDOException $e) {}
    }
    header('Location: acompanhar.php?protocolo=' . urlencode($protocolo));
    exit;
}

if ($protocolo !== '') {
    try {
        $pdo = conectarPDO();
        $stmt = $pdo->prepare('
            SELECT m.*, t.descricao AS tipo_descricao
            FROM tbmanifest m
            INNER JOIN tipos t ON t.IDtipo = m.IDtipo
            WHERE m.protocolo = :protocolo
            LIMIT 1
        ');
        $stmt->execute([':protocolo' => $protocolo]);
        $manifestacao = $stmt->fetch();
        if (!$manifestacao) {
            $erro = 'Protocolo não encontrado. Verifique o código e tente novamente.';
        }
    } catch (PDOException $e) {
        $erro = 'Não foi possível consultar o banco de dados.';
    }
}

function classeStatus(string $status): string {
    return match ($status) {
        'Recebida' => 'status-recebida',
        'Em andamento' => 'status-andamento',
        'Resolvida' => 'status-resolvida',
        default => 'status-neutro',
    };
}
function iconeStatus(string $status): string {
    return match ($status) {
        'Recebida' => 'fa-inbox',
        'Em andamento' => 'fa-spinner',
        'Resolvida' => 'fa-circle-check',
        default => 'fa-question',
    };
}

$tituloPagina = 'Acompanhar Protocolo — Ouvidoria do Grêmio Escolar';
require_once __DIR__ . '/header.php';
?>

<div class="page-header">
  <div class="page-header-inner">
    <span class="section-label">ACOMPANHAMENTO</span>
    <h1>Acompanhar <em>manifestação</em></h1>
    <p>Informe o código de protocolo para verificar o status da sua manifestação.</p>
  </div>
</div>

<section class="auth-section">
  <div class="form-card" style="max-width:680px;margin:0 auto;">

    <h2 style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:900;color:var(--verde-escuro);margin-bottom:8px;">
      Consultar protocolo
    </h2>
    <p style="color:var(--texto-suave);margin-bottom:28px;font-size:0.93rem;">
      O protocolo tem o formato <strong>GRE-AAAAMMDD-XXXXXX</strong> e foi exibido após o envio da manifestação.
    </p>

    <form method="get" autocomplete="off" id="formAcompanhar">
      <div class="form-group" style="position:relative;">
        <label for="protocolo_input">Código do protocolo</label>
        <input
          type="text"
          id="protocolo_input"
          name="protocolo"
          class="form-control"
          placeholder="Ex.: GRE-20260330-A1B2C3"
          value="<?= e($protocolo) ?>"
          style="text-transform:uppercase;letter-spacing:1px;font-weight:700;font-size:1rem;"
          maxlength="22"
          autocomplete="off"
        >
        <div id="protocolo_feedback" style="margin-top:8px;font-size:0.84rem;font-weight:700;min-height:20px;display:flex;align-items:center;gap:6px;"></div>
      </div>

      <button type="submit" class="btn-submit" id="btnConsultar">
        <i class="fa-solid fa-magnifying-glass"></i> Consultar status
      </button>
    </form>

    <?php if ($erro): ?>
      <div style="margin-top:28px;padding:18px 20px;border-radius:16px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;display:flex;align-items:center;gap:12px;">
        <i class="fa-solid fa-circle-xmark" style="font-size:1.4rem;flex-shrink:0;"></i>
        <span><?= e($erro) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($manifestacao): ?>
      <?php
        $status = $manifestacao['status'] ?? 'Recebida';
        $passos = ['Recebida', 'Em andamento', 'Resolvida'];
        $passoAtual = array_search($status, $passos);
      ?>

      <div class="acomp-card">

        <div class="acomp-header">
          <div>
            <div class="acomp-label">PROTOCOLO</div>
            <div class="acomp-protocolo"><?= e($manifestacao['protocolo']) ?></div>
            <div class="acomp-data">Enviado em <?= date('d/m/Y \à\s H:i', strtotime($manifestacao['criado_em'])) ?></div>
          </div>
          <div class="acomp-status <?= e(classeStatus($status)) ?>">
            <i class="fa-solid <?= e(iconeStatus($status)) ?>"></i>
            <?= e($status) ?>
          </div>
        </div>

        <!-- Timeline de progresso -->
        <div class="acomp-timeline">
          <?php foreach ($passos as $i => $passo): ?>
            <div class="acomp-step <?= $i <= $passoAtual ? 'acomp-step-done' : '' ?> <?= $i === $passoAtual ? 'acomp-step-current' : '' ?>">
              <div class="acomp-step-circle">
                <?php if ($i < $passoAtual): ?>
                  <i class="fa-solid fa-check"></i>
                <?php elseif ($i === $passoAtual): ?>
                  <i class="fa-solid fa-circle-dot"></i>
                <?php else: ?>
                  <span><?= $i + 1 ?></span>
                <?php endif; ?>
              </div>
              <div class="acomp-step-label"><?= e($passo) ?></div>
            </div>
            <?php if ($i < count($passos) - 1): ?>
              <div class="acomp-step-line <?= $i < $passoAtual ? 'acomp-step-line-done' : '' ?>"></div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>

        <!-- Dados da manifestação -->
        <div class="acomp-grid">
          <div class="acomp-field">
            <div class="acomp-field-label">Tipo</div>
            <div class="acomp-field-value"><?= e($manifestacao['tipo_descricao'] ?? 'Não informado') ?></div>
          </div>
          <div class="acomp-field">
            <div class="acomp-field-label">Assunto</div>
            <div class="acomp-field-value"><?= e($manifestacao['assunto'] ?? '') ?></div>
          </div>
          <?php if (!empty($manifestacao['setor_relacionado'])): ?>
          <div class="acomp-field">
            <div class="acomp-field-label">Setor relacionado</div>
            <div class="acomp-field-value"><?= e($manifestacao['setor_relacionado']) ?></div>
          </div>
          <?php endif; ?>
          <?php if (!empty($manifestacao['turma_setor'])): ?>
          <div class="acomp-field">
            <div class="acomp-field-label">Turma / Curso</div>
            <div class="acomp-field-value"><?= e($manifestacao['turma_setor']) ?></div>
          </div>
          <?php endif; ?>
        </div>

        <?php if (!empty($manifestacao['feedback'])): ?>
          <div class="acomp-feedback-box">
            <div class="acomp-feedback-title"><i class="fa-solid fa-reply"></i> Resposta do Grêmio</div>
            <div class="acomp-feedback-text"><?= nl2br(e($manifestacao['feedback'])) ?></div>
          </div>
        <?php endif; ?>

        <!-- Foi útil? -->
        <div class="acomp-util-box">
          <div class="acomp-util-title">Esta resposta foi útil para você?</div>
          <form method="post" class="acomp-util-form">
            <input type="hidden" name="protocolo" value="<?= e($protocolo) ?>">
            <input type="hidden" name="id_manifest" value="<?= (int) $manifestacao['IDmanifest'] ?>">
            <?php $utilAtual = $manifestacao['util'] ?? null; ?>
            <button type="submit" name="util" value="sim"
              class="btn-util <?= $utilAtual === '1' ? 'btn-util-ativo' : '' ?>">
              <i class="fa-solid fa-thumbs-up"></i> Sim, foi útil
            </button>
            <button type="submit" name="util" value="nao"
              class="btn-util btn-util-neg <?= $utilAtual === '0' ? 'btn-util-ativo-neg' : '' ?>">
              <i class="fa-solid fa-thumbs-down"></i> Não foi útil
            </button>
          </form>
        </div>

      </div>
    <?php endif; ?>

  </div>
</section>

<style>
.acomp-card {
  margin-top: 32px;
  border: 1px solid var(--cinza-borda);
  border-radius: 22px;
  overflow: hidden;
  background: #fff;
  box-shadow: 0 10px 40px rgba(0,0,0,0.07);
}
.acomp-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  padding: 28px 28px 20px;
  background: linear-gradient(135deg, var(--verde-escuro), var(--verde));
  color: #fff;
}
.acomp-label {
  font-size: 0.65rem;
  letter-spacing: 2px;
  font-weight: 700;
  text-transform: uppercase;
  opacity: 0.7;
  margin-bottom: 4px;
}
.acomp-protocolo {
  font-family: 'Playfair Display', serif;
  font-size: 1.5rem;
  font-weight: 900;
  letter-spacing: 1px;
}
.acomp-data {
  font-size: 0.82rem;
  opacity: 0.7;
  margin-top: 4px;
}
.acomp-status {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 999px;
  font-size: 0.88rem;
  font-weight: 700;
  background: rgba(255,255,255,0.18);
  color: #fff;
  border: 1px solid rgba(255,255,255,0.3);
  backdrop-filter: blur(8px);
}

/* Timeline */
.acomp-timeline {
  display: flex;
  align-items: center;
  padding: 28px 36px;
  gap: 0;
  background: var(--cinza-fundo);
  border-bottom: 1px solid var(--cinza-borda);
}
.acomp-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}
.acomp-step-circle {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 0.95rem;
  border: 2.5px solid var(--cinza-borda);
  background: #fff;
  color: #9ca3af;
  transition: all 0.3s;
}
.acomp-step-done .acomp-step-circle {
  background: var(--verde);
  border-color: var(--verde);
  color: #fff;
}
.acomp-step-current .acomp-step-circle {
  background: var(--laranja);
  border-color: var(--laranja);
  color: #fff;
  box-shadow: 0 0 0 5px rgba(232,130,10,0.2);
}
.acomp-step-label {
  font-size: 0.75rem;
  font-weight: 700;
  color: #9ca3af;
  text-align: center;
  white-space: nowrap;
}
.acomp-step-done .acomp-step-label,
.acomp-step-current .acomp-step-label { color: var(--texto); }
.acomp-step-line {
  flex: 1;
  height: 2px;
  background: var(--cinza-borda);
  margin-bottom: 28px;
}
.acomp-step-line-done { background: var(--verde); }

.acomp-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  padding: 24px 28px;
  border-bottom: 1px solid var(--cinza-borda);
}
.acomp-field-label {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.2px;
  color: var(--texto-suave);
  margin-bottom: 4px;
}
.acomp-field-value {
  font-size: 0.93rem;
  font-weight: 600;
  color: var(--texto);
}
.acomp-feedback-box {
  margin: 0;
  padding: 22px 28px;
  background: #f0fdf4;
  border-bottom: 1px solid #bbf7d0;
}
.acomp-feedback-title {
  font-size: 0.82rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--verde);
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.acomp-feedback-text {
  font-size: 0.93rem;
  color: var(--texto);
  line-height: 1.7;
}

/* Foi útil */
.acomp-util-box {
  padding: 22px 28px;
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}
.acomp-util-title {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--texto-suave);
}
.acomp-util-form {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.btn-util {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border-radius: 999px;
  border: 1.5px solid var(--cinza-borda);
  background: #fff;
  color: var(--texto-suave);
  font-family: 'DM Sans', sans-serif;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-util:hover {
  border-color: var(--verde);
  color: var(--verde);
  background: #f0fdf4;
}
.btn-util-ativo {
  background: var(--verde);
  color: #fff;
  border-color: var(--verde);
}
.btn-util-neg:hover {
  border-color: #dc2626;
  color: #dc2626;
  background: #fef2f2;
}
.btn-util-ativo-neg {
  background: #dc2626;
  color: #fff;
  border-color: #dc2626;
}

@media (max-width: 600px) {
  .acomp-timeline { padding: 20px 18px; }
  .acomp-step-circle { width: 34px; height: 34px; font-size: 0.8rem; }
  .acomp-step-label { font-size: 0.65rem; }
  .acomp-grid { padding: 18px; }
  .acomp-header { padding: 20px 18px 16px; }
}
</style>

<script>
(function() {
  const input = document.getElementById('protocolo_input');
  const feedback = document.getElementById('protocolo_feedback');
  let debounceTimer;

  if (!input) return;

  // Força maiúsculas automaticamente
  input.addEventListener('input', function() {
    const pos = this.selectionStart;
    this.value = this.value.toUpperCase();
    this.setSelectionRange(pos, pos);
    clearTimeout(debounceTimer);
    const val = this.value.trim();

    if (!val) {
      feedback.innerHTML = '';
      resetEstilo();
      return;
    }

    // Validação de formato em tempo real (visual imediato)
    if (!formatoValido(val)) {
      if (val.length < 3) {
        feedback.innerHTML = '';
        resetEstilo();
      } else {
        mostrarErro('Formato inválido — use: GRE-AAAAMMDD-XXXXXX');
        input.classList.remove('campo-ok');
        input.classList.add('campo-erro');
        tremerCampo();
      }
      return;
    }

    feedback.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="color:#9ca3af"></i> <span style="color:#9ca3af">Verificando...</span>';

    debounceTimer = setTimeout(() => {
      fetch('acompanhar.php?ajax_check=1&protocolo=' + encodeURIComponent(val))
        .then(r => r.json())
        .then(data => {
          if (data.status === 'found') {
            feedback.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#16a34a"></i> <span style="color:#16a34a">Protocolo encontrado — status: <strong>' + data.manifest_status + '</strong></span>';
            input.classList.remove('campo-erro');
            input.classList.add('campo-ok');
          } else if (data.status === 'not_found') {
            mostrarErro('Protocolo não encontrado no sistema.');
            input.classList.remove('campo-ok');
            input.classList.add('campo-erro');
            tremerCampo();
          } else if (data.status === 'invalid') {
            mostrarErro('Formato inválido — use: GRE-AAAAMMDD-XXXXXX');
            input.classList.remove('campo-ok');
            input.classList.add('campo-erro');
          }
        })
        .catch(() => {
          feedback.innerHTML = '';
          resetEstilo();
        });
    }, 500);
  });

  function formatoValido(val) {
    return /^GRE-\d{8}-[A-Z0-9]{6}$/.test(val);
  }

  function mostrarErro(msg) {
    feedback.innerHTML = '<i class="fa-solid fa-circle-xmark" style="color:#e84040"></i> <span style="color:#e84040">' + msg + '</span>';
  }

  function resetEstilo() {
    input.classList.remove('campo-erro', 'campo-ok');
  }

  function tremerCampo() {
    input.style.animation = 'none';
    setTimeout(() => {
      input.style.animation = 'tremerCampo .2s linear 2';
    }, 10);
  }
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
