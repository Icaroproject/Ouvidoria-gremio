<?php
require_once __DIR__ . '/includes_functions.php';

$pdo = conectarPDO();
$tipoSelecionado = $_GET['tipo'] ?? ($_POST['tipo'] ?? '');
$protocoloGerado = null;

$dadosFormulario = [
    'tipo' => $tipoSelecionado,
    'nome' => usuarioLogado() ? ($_SESSION['usuario']['nome'] ?? '') : '',
    'perfil' => '',
    'email' => usuarioLogado() ? ($_SESSION['usuario']['email'] ?? '') : '',
    'turma_setor' => '',
    'assunto' => '',
    'setor_relacionado' => '',
    'descricao' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dadosFormulario = [
        'tipo' => trim($_POST['tipo'] ?? ''),
        'nome' => trim($_POST['nome'] ?? ''),
        'perfil' => trim($_POST['perfil'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'turma_setor' => trim($_POST['turma_setor'] ?? ''),
        'assunto' => trim($_POST['assunto'] ?? ''),
        'setor_relacionado' => trim($_POST['setor_relacionado'] ?? ''),
        'descricao' => trim($_POST['descricao'] ?? ''),
    ];

    $descricaoTipo = tipoSlugParaDescricao($dadosFormulario['tipo']);

    if ($descricaoTipo === '') {
        flash('erro', 'Selecione o tipo da manifestação.');
        header('Location: manifestacao.php');
        exit;
    }

    if ($dadosFormulario['assunto'] === '' || mb_strlen($dadosFormulario['assunto']) < 5) {
        flash('erro', 'Informe um assunto com pelo menos 5 caracteres.');
        header('Location: manifestacao.php?tipo=' . urlencode($dadosFormulario['tipo']));
        exit;
    }

    if ($dadosFormulario['descricao'] === '' || mb_strlen($dadosFormulario['descricao']) < 15) {
        flash('erro', 'Descreva a manifestação com pelo menos 15 caracteres.');
        header('Location: manifestacao.php?tipo=' . urlencode($dadosFormulario['tipo']));
        exit;
    }

    if ($dadosFormulario['email'] !== '' && !filter_var($dadosFormulario['email'], FILTER_VALIDATE_EMAIL)) {
        flash('erro', 'Informe um e-mail válido para retorno ou deixe o campo em branco.');
        header('Location: manifestacao.php?tipo=' . urlencode($dadosFormulario['tipo']));
        exit;
    }

    $stmtTipo = $pdo->prepare('SELECT IDtipo, descricao FROM tipos WHERE descricao = :descricao LIMIT 1');
    $stmtTipo->execute([':descricao' => $descricaoTipo]);
    $tipoBanco = $stmtTipo->fetch(PDO::FETCH_ASSOC);

    if (!$tipoBanco) {
        flash('erro', 'Não foi possível localizar o tipo de manifestação no banco.');
        header('Location: manifestacao.php');
        exit;
    }

    $anonimo = $dadosFormulario['nome'] === '';
    $nomeManifestante = $anonimo ? 'Anônimo' : $dadosFormulario['nome'];
    $perfilManifestante = $anonimo
        ? 'Anônimo'
        : ($dadosFormulario['perfil'] !== '' ? $dadosFormulario['perfil'] : 'Comunidade');

    $usuarioId = null;

    if (usuarioLogado() && !$anonimo && isset($_SESSION['usuario']['id'])) {
        $idSessao = (int) $_SESSION['usuario']['id'];

        $stmtUsuario = $pdo->prepare('SELECT IDusu FROM tbusuarios WHERE IDusu = :id LIMIT 1');
        $stmtUsuario->execute([':id' => $idSessao]);
        $usuarioExiste = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

        if ($usuarioExiste) {
            $usuarioId = $idSessao;
        }
    }

    $protocoloGerado = gerarProtocoloManifestacao();

    $stmt = $pdo->prepare('
        INSERT INTO tbmanifest (
            IDusu,
            IDadm,
            IDtipo,
            protocolo,
            assunto,
            manifest,
            status,
            feedback,
            contato,
            nome_manifestante,
            perfil_manifestante,
            turma_setor,
            setor_relacionado,
            criado_em
        ) VALUES (
            :idusu,
            NULL,
            :idtipo,
            :protocolo,
            :assunto,
            :manifest,
            :status,
            NULL,
            :contato,
            :nome_manifestante,
            :perfil_manifestante,
            :turma_setor,
            :setor_relacionado,
            NOW()
        )
    ');

    $stmt->bindValue(':idusu', $usuarioId, $usuarioId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':idtipo', (int) $tipoBanco['IDtipo'], PDO::PARAM_INT);
    $stmt->bindValue(':protocolo', $protocoloGerado, PDO::PARAM_STR);
    $stmt->bindValue(':assunto', $dadosFormulario['assunto'], PDO::PARAM_STR);
    $stmt->bindValue(':manifest', $dadosFormulario['descricao'], PDO::PARAM_STR);
    $stmt->bindValue(':status', 'Recebida', PDO::PARAM_STR);
    $stmt->bindValue(
        ':contato',
        $dadosFormulario['email'] !== '' ? $dadosFormulario['email'] : null,
        $dadosFormulario['email'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL
    );
    $stmt->bindValue(':nome_manifestante', $nomeManifestante, PDO::PARAM_STR);
    $stmt->bindValue(':perfil_manifestante', $perfilManifestante, PDO::PARAM_STR);
    $stmt->bindValue(
        ':turma_setor',
        $dadosFormulario['turma_setor'] !== '' ? $dadosFormulario['turma_setor'] : null,
        $dadosFormulario['turma_setor'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL
    );
    $stmt->bindValue(
        ':setor_relacionado',
        $dadosFormulario['setor_relacionado'] !== '' ? $dadosFormulario['setor_relacionado'] : null,
        $dadosFormulario['setor_relacionado'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL
    );

    $stmt->execute();

    flash('sucesso', 'Manifestação enviada com sucesso. Seu protocolo é ' . $protocoloGerado . '.');
    header('Location: manifestacao.php?sucesso=1&protocolo=' . urlencode($protocoloGerado));
    exit;
}

$protocoloGerado = $_GET['protocolo'] ?? null;
$tituloPagina = 'Fazer Manifestação — Ouvidoria do Grêmio Escolar';
require_once __DIR__ . '/header.php';
?>

<div class="page-header">
  <div class="page-header-inner">
    <span class="section-label">OUVIDORIA DO GRÊMIO ESCOLAR</span>
    <h1>Fazer <em>Manifestação</em></h1>
    <p>Registre sua sugestão, elogio, reclamação ou denúncia para o Grêmio Escolar da EEEP Dom Walfrido Teixeira Vieira.</p>
  </div>
</div>

<section class="form-section">
  <div class="form-card">
    <?php if (isset($_GET['sucesso']) && $protocoloGerado): ?>
      <div style="margin-bottom:24px;padding:18px 20px;border-radius:16px;background:#ecf9f0;border:1px solid #bfe5cb;color:#14532d;">
        <strong>Manifestação registrada com sucesso.</strong><br>
        Protocolo: <strong><?= e($protocoloGerado) ?></strong>
      </div>
    <?php endif; ?>

    <div style="margin-bottom:32px">
      <h3 style="font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:900;color:var(--verde-escuro);margin-bottom:8px">
        Canal do Grêmio Escolar
      </h3>
      <p style="color:var(--texto-suave);font-size:0.92rem">
        Antes do envio final, você poderá revisar todos os dados informados.
      </p>
    </div>

    <form method="post" novalidate id="formManifestacao">
      <div class="form-group">
        <label for="tipo">Tipo de manifestação <span style="color:#e84040">*</span></label>
        <select id="tipo" name="tipo" class="form-control" required>
          <option value="">Selecione</option>
          <option value="sugestao" <?= $dadosFormulario['tipo'] === 'sugestao' ? 'selected' : '' ?>>Sugestão</option>
          <option value="elogio" <?= $dadosFormulario['tipo'] === 'elogio' ? 'selected' : '' ?>>Elogio</option>
          <option value="reclamacao" <?= $dadosFormulario['tipo'] === 'reclamacao' ? 'selected' : '' ?>>Reclamação</option>
          <option value="denuncia" <?= $dadosFormulario['tipo'] === 'denuncia' ? 'selected' : '' ?>>Denúncia</option>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="nome">Nome completo</label>
          <input
            type="text"
            id="nome"
            name="nome"
            class="form-control"
            placeholder="Deixe em branco se quiser enviar como anônimo"
            value="<?= e($dadosFormulario['nome']) ?>"
          >
        </div>

        <div class="form-group">
          <label for="perfil">Perfil</label>
          <select id="perfil" name="perfil" class="form-control">
            <option value="">Selecione</option>
            <?php foreach (['Aluno(a)', 'Responsável', 'Professor(a)', 'Servidor(a)', 'Comunidade'] as $perfil): ?>
              <option value="<?= e($perfil) ?>" <?= $dadosFormulario['perfil'] === $perfil ? 'selected' : '' ?>>
                <?= e($perfil) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="email">E-mail para retorno</label>
          <input
            type="email"
            id="email"
            name="email"
            class="form-control"
            placeholder="opcional@email.com"
            value="<?= e($dadosFormulario['email']) ?>"
          >
        </div>

        <div class="form-group">
  <label for="turma_setor">Turma / Curso</label>
  <select id="turma_setor" name="turma_setor" class="form-control">
    <option value="">Selecione sua turma</option>

    <optgroup label="Informática">
      <?php foreach (['1°Informática', '2°Informática', '3°Informática'] as $opcao): ?>
        <option value="<?= e($opcao) ?>" <?= $dadosFormulario['turma_setor'] === $opcao ? 'selected' : '' ?>>
          <?= e($opcao) ?>
        </option>
      <?php endforeach; ?>
    </optgroup>

    <optgroup label="Saúde Bucal">
      <?php foreach (['1°Saúde Bucal', '2°Saúde Bucal', '3°Saúde Bucal'] as $opcao): ?>
        <option value="<?= e($opcao) ?>" <?= $dadosFormulario['turma_setor'] === $opcao ? 'selected' : '' ?>>
          <?= e($opcao) ?>
        </option>
      <?php endforeach; ?>
    </optgroup>

    <optgroup label="Energias Renováveis">
      <?php foreach (['1°Energias renováveis', '2°Energias renováveis', '3°Energias renováveis'] as $opcao): ?>
        <option value="<?= e($opcao) ?>" <?= $dadosFormulario['turma_setor'] === $opcao ? 'selected' : '' ?>>
          <?= e($opcao) ?>
        </option>
      <?php endforeach; ?>
    </optgroup>

    <optgroup label="Enfermagem">
      <?php foreach (['1°Enfermagem', '2°Enfermagem', '3°Enfermagem'] as $opcao): ?>
        <option value="<?= e($opcao) ?>" <?= $dadosFormulario['turma_setor'] === $opcao ? 'selected' : '' ?>>
          <?= e($opcao) ?>
        </option>
      <?php endforeach; ?>
    </optgroup>
  </select>
</div>

      <div class="form-group">
        <label for="assunto">Assunto <span style="color:#e84040">*</span></label>
        <input
          type="text"
          id="assunto"
          name="assunto"
          class="form-control"
          placeholder="Descreva o assunto em poucas palavras"
          value="<?= e($dadosFormulario['assunto']) ?>"
          required
        >
      </div>

      <div class="form-group">
        <label for="setor_relacionado">Setor relacionado</label>
        <select id="setor_relacionado" name="setor_relacionado" class="form-control">
          <option value="">Selecione</option>
          <?php foreach (['Grêmio Escolar', 'Coordenação', 'Professores', 'Secretaria', 'Infraestrutura', 'Laboratório', 'Outro'] as $setor): ?>
            <option value="<?= e($setor) ?>" <?= $dadosFormulario['setor_relacionado'] === $setor ? 'selected' : '' ?>>
              <?= e($setor) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      </div>

      <div class="form-group full-width">
        <label for="descricao">Descrição <span style="color:#e84040">*</span></label>
        <textarea
          id="descricao"
          name="descricao"
          class="form-control"
          rows="7"
          placeholder="Explique sua manifestação com detalhes"
        ><?= e($dadosFormulario['descricao']) ?></textarea>
        <div class="contador-caracteres" id="contadorWrapDescricao">
          <span id="contadorDescricao">0</span>/15 caracteres mínimos
        </div>
      </div>

      <div class="form-group full-width">
        <button type="button" class="btn-revisar" id="btnAbrirConfirmacao">
          <i class="fa-solid fa-paper-plane"></i> Revisar antes de enviar
        </button>
      </div>
    </form>
  </div>
</section>

<div id="modalConfirmacao" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="width:100%;max-width:720px;background:#fff;border-radius:22px;padding:28px;box-shadow:0 25px 70px rgba(0,0,0,0.22);max-height:90vh;overflow:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:18px;">
      <div>
        <h3 style="margin:0;font-size:1.5rem;color:var(--verde-escuro);font-family:'Playfair Display',serif;">Confirmar manifestação</h3>
        <p style="margin:6px 0 0 0;color:var(--texto-suave);font-size:0.92rem;">Confira os dados antes do envio final.</p>
      </div>
      <button type="button" id="fecharModalConfirmacao" style="border:none;background:#f1f1f1;width:40px;height:40px;border-radius:50%;cursor:pointer;font-size:18px;">×</button>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
      <div style="background:#f8f8f8;padding:14px;border-radius:14px;"><strong>Tipo:</strong><br><span id="confTipo"></span></div>
      <div style="background:#f8f8f8;padding:14px;border-radius:14px;"><strong>Nome:</strong><br><span id="confNome"></span></div>
      <div style="background:#f8f8f8;padding:14px;border-radius:14px;"><strong>Perfil:</strong><br><span id="confPerfil"></span></div>
      <div style="background:#f8f8f8;padding:14px;border-radius:14px;"><strong>E-mail:</strong><br><span id="confEmail"></span></div>
      <div style="background:#f8f8f8;padding:14px;border-radius:14px;">
      <strong>Turma / Curso:</strong><br>
      <span id="confTurmaSetor" class="badge-curso-preview"></span>
      </div>
      <div style="background:#f8f8f8;padding:14px;border-radius:14px;"><strong>Setor relacionado:</strong><br><span id="confSetorRelacionado"></span></div>
    </div>

    <div style="background:#f8f8f8;padding:14px;border-radius:14px;margin-bottom:14px;">
      <strong>Assunto:</strong><br>
      <span id="confAssunto"></span>
    </div>

    <div style="background:#f8f8f8;padding:14px;border-radius:14px;margin-bottom:20px;">
      <strong>Descrição:</strong><br>
      <span id="confDescricao" style="white-space:pre-wrap;"></span>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:12px;flex-wrap:wrap;">
      <button type="button" id="btnVoltarEdicao" style="padding:12px 18px;border-radius:999px;border:1px solid #ccc;background:#fff;cursor:pointer;font-weight:600;">
        Voltar e editar
      </button>
      <button type="button" id="btnConfirmarEnvio" style="padding:12px 20px;border-radius:999px;border:none;background:var(--verde);color:#fff;cursor:pointer;font-weight:700;">
        Confirmar envio
      </button>
    </div>
  </div>
</div>

<script>
const formManifestacao = document.getElementById('formManifestacao');
const modalConfirmacao = document.getElementById('modalConfirmacao');
const btnAbrirConfirmacao = document.getElementById('btnAbrirConfirmacao');
const btnFecharModal = document.getElementById('fecharModalConfirmacao');
const btnVoltarEdicao = document.getElementById('btnVoltarEdicao');
const btnConfirmarEnvio = document.getElementById('btnConfirmarEnvio');

function valorOuPadrao(valor, padrao = 'Não informado') {
  const texto = (valor || '').trim();
  return texto !== '' ? texto : padrao;
}

function preencherConfirmacao() {
  const tipoSelect = document.getElementById('tipo');
  const perfilSelect = document.getElementById('perfil');
  const setorSelect = document.getElementById('setor_relacionado');

  document.getElementById('confTipo').textContent =
    tipoSelect.options[tipoSelect.selectedIndex]?.text || 'Não informado';

  document.getElementById('confNome').textContent =
    valorOuPadrao(document.getElementById('nome').value, 'Anônimo');

  document.getElementById('confPerfil').textContent =
    valorOuPadrao(perfilSelect.options[perfilSelect.selectedIndex]?.text, 'Não informado');

  document.getElementById('confEmail').textContent =
    valorOuPadrao(document.getElementById('email').value);

  const turmaValor = valorOuPadrao(document.getElementById('turma_setor').value);
  const confTurma = document.getElementById('confTurmaSetor');
  confTurma.textContent = turmaValor;
  confTurma.className = 'badge-curso-preview ' + classeCurso(turmaValor);

  document.getElementById('confSetorRelacionado').textContent =
    valorOuPadrao(setorSelect.options[setorSelect.selectedIndex]?.text);

  document.getElementById('confAssunto').textContent =
    valorOuPadrao(document.getElementById('assunto').value);

  document.getElementById('confDescricao').textContent =
    valorOuPadrao(document.getElementById('descricao').value);
}

function abrirModalConfirmacao() {
  preencherConfirmacao();
  modalConfirmacao.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function fecharModalConfirmacao() {
  modalConfirmacao.style.display = 'none';
  document.body.style.overflow = '';
}

btnAbrirConfirmacao.addEventListener('click', function () {
  const tipo = document.getElementById('tipo').value.trim();
  const assunto = document.getElementById('assunto').value.trim();
  const descricao = document.getElementById('descricao').value.trim();
  const email = document.getElementById('email').value.trim();

  if (tipo === '') {
    alert('Selecione o tipo da manifestação.');
    return;
  }

  if (assunto.length < 5) {
    alert('Informe um assunto com pelo menos 5 caracteres.');
    return;
  }

  if (descricao.length < 15) {
    alert('Descreva a manifestação com pelo menos 15 caracteres.');
    return;
  }

  if (email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    alert('Informe um e-mail válido ou deixe o campo em branco.');
    return;
  }

  abrirModalConfirmacao();
});

btnFecharModal.addEventListener('click', fecharModalConfirmacao);
btnVoltarEdicao.addEventListener('click', fecharModalConfirmacao);

modalConfirmacao.addEventListener('click', function(e) {
  if (e.target === modalConfirmacao) {
    fecharModalConfirmacao();
  }
});

btnConfirmarEnvio.addEventListener('click', function () {
  formManifestacao.submit();
});
function valorOuPadrao(valor, padrao = 'Não informado') {
  const texto = (valor || '').trim();
  return texto !== '' ? texto : padrao;
}

function classeCurso(valor) {
  if (valor.includes('Informática')) return 'curso-informatica';
  if (valor.includes('Saúde Bucal')) return 'curso-saude';
  if (valor.includes('Energias')) return 'curso-energias';
  if (valor.includes('Enfermagem')) return 'curso-enfermagem';
  return 'curso-neutro';
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>