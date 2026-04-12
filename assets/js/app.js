document.addEventListener('DOMContentLoaded', () => {
  const hamburger = document.getElementById('hamburgerBtn');
  const drawer = document.getElementById('drawerSidebar');
  const drawerOverlay = document.getElementById('drawerOverlay');
  const drawerClose = document.getElementById('drawerClose');

  function openDrawer() {
    if (!drawer) return;
    hamburger?.classList.add('open');
    drawer.classList.add('open');
    drawerOverlay?.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeDrawer() {
    if (!drawer) return;
    hamburger?.classList.remove('open');
    drawer.classList.remove('open');
    drawerOverlay?.classList.remove('open');
    document.body.style.overflow = '';
  }

  hamburger?.addEventListener('click', () => {
    drawer?.classList.contains('open') ? closeDrawer() : openDrawer();
  });

  drawerOverlay?.addEventListener('click', closeDrawer);
  drawerClose?.addEventListener('click', closeDrawer);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeDrawer();
  });

  const rawPage = window.location.pathname.split('/').pop();
  const page = (!rawPage || rawPage === '') ? 'index.php' : rawPage;

  function marcarAtivo(selector) {
    document.querySelectorAll(selector).forEach(item => {
      item.classList.remove('active');
      const href = item.getAttribute('href') || '';
      const basePage = href.split('#')[0].split('/').pop();
      const normalBase = (!basePage || basePage === '') ? 'index.php' : basePage;
      if (normalBase === page) item.classList.add('active');
    });
  }

  marcarAtivo('.topbar-link');
  marcarAtivo('.drawer-item');

  const flash = document.querySelector('.flash-msg');
  if (flash) {
    setTimeout(() => flash.classList.add('flash-hide'), 4500);
  }

  const authTabs = document.querySelectorAll('.auth-tab');
  const authPanels = document.querySelectorAll('.auth-panel');

  function abrirPainelAuth(panelName) {
    if (!panelName) return;
    authTabs.forEach(tab => tab.classList.toggle('active', tab.dataset.panel === panelName));
    authPanels.forEach(panel => panel.classList.toggle('active', panel.id === panelName));
  }

  authTabs.forEach(tab => {
    tab.addEventListener('click', () => abrirPainelAuth(tab.dataset.panel));
  });

  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('cadastro') === 'ok') {
    abrirPainelAuth('login');
  } else if (window.location.hash === '#cadastro') {
    abrirPainelAuth('cadastro');
  } else if (authTabs.length > 0) {
    abrirPainelAuth('login');
  }

  function aplicarMascaraCPF(input) {
    input.addEventListener('input', () => {
      let v = input.value.replace(/\D/g, '').slice(0, 11);
      if (v.length > 9) v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
      else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
      else if (v.length > 3) v = v.replace(/(\d{3})(\d{1,3})/, '$1.$2');
      input.value = v;
    });
  }

  document.querySelectorAll('[data-mask="cpf"]').forEach(aplicarMascaraCPF);

  const senhaInput = document.getElementById('cadSenha');
  const barra = document.getElementById('barraSenha');
  const labelSenha = document.getElementById('labelSenha');

  function calcularForcaSenha(senha) {
    let pts = 0;
    if (senha.length >= 8) pts++;
    if (senha.length >= 12) pts++;
    if (/[A-Z]/.test(senha)) pts++;
    if (/[a-z]/.test(senha)) pts++;
    if (/[0-9]/.test(senha)) pts++;
    if (/[^A-Za-z0-9]/.test(senha)) pts++;
    return pts;
  }

  function obterInfoForca(pts) {
    if (pts <= 1) return { label: 'Muito fraca', cor: '#e84040', pct: 16 };
    if (pts === 2) return { label: 'Fraca', cor: '#e88040', pct: 33 };
    if (pts === 3) return { label: 'Razoável', cor: '#e8c040', pct: 50 };
    if (pts === 4) return { label: 'Boa', cor: '#80c840', pct: 67 };
    if (pts === 5) return { label: 'Forte', cor: '#40b840', pct: 83 };
    return { label: 'Muito forte', cor: '#1a8c3a', pct: 100 };
  }

  if (senhaInput && barra && labelSenha) {
    senhaInput.addEventListener('input', () => {
      if (!senhaInput.value) {
        barra.style.width = '0';
        labelSenha.textContent = '';
        return;
      }
      const info = obterInfoForca(calcularForcaSenha(senhaInput.value));
      barra.style.width = info.pct + '%';
      barra.style.background = info.cor;
      labelSenha.textContent = info.label;
      labelSenha.style.color = info.cor;
    });
  }

  function setErro(campo, msg) {
    if (!campo) return;
    campo.classList.remove('campo-ok');
    campo.classList.add('campo-erro');
    let err = campo.parentElement.querySelector('.msg-erro');
    if (!err) {
      err = document.createElement('span');
      err.className = 'msg-erro';
      campo.parentElement.appendChild(err);
    }
    err.textContent = msg;
  }

  function setCampoOk(campo) {
    if (!campo) return;
    campo.classList.remove('campo-erro');
    campo.classList.add('campo-ok');
    const err = campo.parentElement.querySelector('.msg-erro');
    if (err) err.remove();
  }

  function limparErro(campo) {
    if (!campo) return;
    campo.classList.remove('campo-erro', 'campo-ok');
    const err = campo.parentElement.querySelector('.msg-erro');
    if (err) err.remove();
  }

  function validarEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test((email || '').trim());
  }

  function validarCPF(cpf) {
    cpf = (cpf || '').replace(/\D/g, '');
    if (cpf.length !== 11) return false;
    if (/^(\d)\1{10}$/.test(cpf)) return false;

    let soma = 0;
    for (let i = 0; i < 9; i++) soma += parseInt(cpf[i], 10) * (10 - i);
    let dig1 = (soma * 10) % 11;
    if (dig1 === 10) dig1 = 0;
    if (dig1 !== parseInt(cpf[9], 10)) return false;

    soma = 0;
    for (let i = 0; i < 10; i++) soma += parseInt(cpf[i], 10) * (11 - i);
    let dig2 = (soma * 10) % 11;
    if (dig2 === 10) dig2 = 0;
    return dig2 === parseInt(cpf[10], 10);
  }

  function bindValidation(campo, validator) {
    if (!campo) return;
    const run = () => validator();
    campo.addEventListener('input', run);
    campo.addEventListener('change', run);
    campo.addEventListener('blur', run);
  }

  // LOGIN
  const formLogin = document.getElementById('formLogin');
  if (formLogin) {
    const loginEmail = document.getElementById('loginEmail');
    const loginSenha = document.getElementById('loginSenha');

    const validarLoginEmail = () => {
      const valor = loginEmail.value.trim();
      if (!valor) { setErro(loginEmail, 'Informe seu e-mail.'); return false; }
      if (!validarEmail(valor)) { setErro(loginEmail, 'Informe um e-mail válido.'); return false; }
      setCampoOk(loginEmail); return true;
    };

    const validarLoginSenha = () => {
      const valor = loginSenha.value;
      if (!valor) { setErro(loginSenha, 'Informe sua senha.'); return false; }
      setCampoOk(loginSenha); return true;
    };

    bindValidation(loginEmail, validarLoginEmail);
    bindValidation(loginSenha, validarLoginSenha);

    formLogin.addEventListener('submit', (e) => {
      const ok = [validarLoginEmail(), validarLoginSenha()].every(Boolean);
      if (!ok) {
        e.preventDefault();
        formLogin.querySelector('.campo-erro')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
  }

  // CADASTRO
  const formCadastro = document.getElementById('formCadastro');
  if (formCadastro) {
    const cadNome = document.getElementById('cadNome');
    const cadCPF = document.getElementById('cadCPF');
    const cadPerfil = document.getElementById('cadPerfil');
    const cadEmail = document.getElementById('cadEmail');
    const cadSenha = document.getElementById('cadSenha');
    const cadConfSenha = document.getElementById('cadConfSenha');

    const validarCadNome = () => {
      if (cadNome.value.trim().length < 3) { setErro(cadNome, 'Digite seu nome completo.'); return false; }
      setCampoOk(cadNome); return true;
    };
    const validarCadCPF = () => {
      if (!cadCPF.value.trim()) { setErro(cadCPF, 'Informe seu CPF.'); return false; }
      if (!validarCPF(cadCPF.value)) { setErro(cadCPF, 'CPF inválido.'); return false; }
      setCampoOk(cadCPF); return true;
    };
    const validarCadPerfil = () => {
      if (!cadPerfil.value.trim()) { setErro(cadPerfil, 'Selecione seu perfil.'); return false; }
      setCampoOk(cadPerfil); return true;
    };
    const validarCadEmail = () => {
      if (!cadEmail.value.trim()) { setErro(cadEmail, 'Informe seu e-mail.'); return false; }
      if (!validarEmail(cadEmail.value)) { setErro(cadEmail, 'Informe um e-mail válido.'); return false; }
      setCampoOk(cadEmail); return true;
    };
    const validarCadSenha = () => {
      if (cadSenha.value.length < 8) { setErro(cadSenha, 'A senha deve ter pelo menos 8 caracteres.'); return false; }
      setCampoOk(cadSenha); return true;
    };
    const validarCadConfSenha = () => {
      if (!cadConfSenha.value) { setErro(cadConfSenha, 'Confirme sua senha.'); return false; }
      if (cadConfSenha.value !== cadSenha.value) { setErro(cadConfSenha, 'As senhas não coincidem.'); return false; }
      setCampoOk(cadConfSenha); return true;
    };

    [ [cadNome, validarCadNome], [cadCPF, validarCadCPF], [cadPerfil, validarCadPerfil], [cadEmail, validarCadEmail], [cadSenha, validarCadSenha], [cadConfSenha, validarCadConfSenha] ]
      .forEach(([campo, fn]) => bindValidation(campo, fn));

    formCadastro.addEventListener('submit', (e) => {
      const ok = [validarCadNome(), validarCadCPF(), validarCadPerfil(), validarCadEmail(), validarCadSenha(), validarCadConfSenha()].every(Boolean);
      if (!ok) {
        e.preventDefault();
        formCadastro.querySelector('.campo-erro')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
  }

  // MINHA CONTA
  const formMinhaContaDados = document.getElementById('formMinhaContaDados');
  if (formMinhaContaDados) {
    const nome = document.getElementById('mcNome');
    const email = document.getElementById('mcEmail');

    const validarNome = () => {
      if (nome.value.trim().length < 3) { setErro(nome, 'Digite um nome válido.'); return false; }
      setCampoOk(nome); return true;
    };
    const validarEmailConta = () => {
      if (!email.value.trim()) { setErro(email, 'Informe seu e-mail.'); return false; }
      if (!validarEmail(email.value)) { setErro(email, 'Informe um e-mail válido.'); return false; }
      setCampoOk(email); return true;
    };

    bindValidation(nome, validarNome);
    bindValidation(email, validarEmailConta);

    formMinhaContaDados.addEventListener('submit', (e) => {
      const ok = [validarNome(), validarEmailConta()].every(Boolean);
      if (!ok) {
        e.preventDefault();
        formMinhaContaDados.querySelector('.campo-erro')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
  }

  const formMinhaContaSenha = document.getElementById('formMinhaContaSenha');
  if (formMinhaContaSenha) {
    const atual = document.getElementById('mcSenhaAtual');
    const nova = document.getElementById('mcNovaSenha');
    const conf = document.getElementById('mcConfirmarSenha');

    const validarAtual = () => {
      if (!atual.value) { setErro(atual, 'Informe sua senha atual.'); return false; }
      setCampoOk(atual); return true;
    };
    const validarNova = () => {
      if (nova.value.length < 8) { setErro(nova, 'A nova senha deve ter pelo menos 8 caracteres.'); return false; }
      setCampoOk(nova); return true;
    };
    const validarConf = () => {
      if (!conf.value) { setErro(conf, 'Confirme a nova senha.'); return false; }
      if (conf.value !== nova.value) { setErro(conf, 'As senhas não coincidem.'); return false; }
      setCampoOk(conf); return true;
    };

    [ [atual, validarAtual], [nova, validarNova], [conf, validarConf] ].forEach(([campo, fn]) => bindValidation(campo, fn));

    formMinhaContaSenha.addEventListener('submit', (e) => {
      const ok = [validarAtual(), validarNova(), validarConf()].every(Boolean);
      if (!ok) {
        e.preventDefault();
        formMinhaContaSenha.querySelector('.campo-erro')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
  }

  // RECUPERAR/RESETAR SENHA
  const formForgot = document.getElementById('formForgotPassword');
  if (formForgot) {
    const email = document.getElementById('email');
    const validar = () => {
      if (!email.value.trim()) { setErro(email, 'Informe seu e-mail.'); return false; }
      if (!validarEmail(email.value)) { setErro(email, 'Informe um e-mail válido.'); return false; }
      setCampoOk(email); return true;
    };
    bindValidation(email, validar);
    formForgot.addEventListener('submit', (e) => { if (!validar()) e.preventDefault(); });
  }

  const formReset = document.getElementById('formResetPassword');
  if (formReset) {
    const nova = document.getElementById('nova_senha');
    const conf = document.getElementById('confirmar_senha');
    const validarNova = () => {
      if (nova.value.length < 8) { setErro(nova, 'A senha deve ter pelo menos 8 caracteres.'); return false; }
      setCampoOk(nova); return true;
    };
    const validarConf = () => {
      if (!conf.value) { setErro(conf, 'Confirme a senha.'); return false; }
      if (conf.value !== nova.value) { setErro(conf, 'As senhas não coincidem.'); return false; }
      setCampoOk(conf); return true;
    };
    bindValidation(nova, validarNova);
    bindValidation(conf, validarConf);
    formReset.addEventListener('submit', (e) => {
      if (![validarNova(), validarConf()].every(Boolean)) e.preventDefault();
    });
  }

  // MANIFESTAÇÃO
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

  function classeCurso(valor) {
    if (valor.includes('Informática')) return 'curso-informatica';
    if (valor.includes('Saúde Bucal')) return 'curso-saude';
    if (valor.includes('Energias')) return 'curso-energias';
    if (valor.includes('Enfermagem')) return 'curso-enfermagem';
    return 'curso-neutro';
  }

  if (formManifestacao) {
    const tipo = document.getElementById('tipo');
    const assunto = document.getElementById('assunto');
    const descricao = document.getElementById('descricao');
    const email = document.getElementById('email');
    const perfil = document.getElementById('perfil');
    const setor = document.getElementById('setor_relacionado');
    const contadorDescricao = document.getElementById('contadorDescricao');
    const contadorWrap = document.getElementById('contadorWrapDescricao');

    function validarTipo() {
      if (!tipo.value.trim()) { setErro(tipo, 'Selecione o tipo da manifestação.'); return false; }
      setCampoOk(tipo); return true;
    }
    function validarAssunto() {
      const valor = assunto.value.trim();
      if (!valor) { setErro(assunto, 'Informe o assunto.'); return false; }
      if (valor.length < 5) { setErro(assunto, 'Assunto muito curto. Mínimo de 5 caracteres.'); return false; }
      setCampoOk(assunto); return true;
    }
    function validarDescricao() {
      const valor = descricao.value.trim();
      const tamanho = valor.length;
      if (contadorDescricao) contadorDescricao.textContent = tamanho;
      contadorWrap?.classList.remove('invalido', 'valido');

      if (!valor) {
        setErro(descricao, 'Descreva sua manifestação.');
        contadorWrap?.classList.add('invalido');
        return false;
      }
      if (tamanho < 15) {
        setErro(descricao, 'Descreva melhor sua manifestação. Mínimo de 15 caracteres.');
        contadorWrap?.classList.add('invalido');
        return false;
      }
      setCampoOk(descricao);
      contadorWrap?.classList.add('valido');
      return true;
    }
    function validarEmailManifestacao() {
      if (!email.value.trim()) {
        limparErro(email);
        return true;
      }
      if (!validarEmail(email.value)) { setErro(email, 'Informe um e-mail válido.'); return false; }
      setCampoOk(email); return true;
    }

    [[tipo, validarTipo],[assunto, validarAssunto],[descricao, validarDescricao],[email, validarEmailManifestacao]].forEach(([campo, fn]) => bindValidation(campo, fn));
    // Não valida na carga — só atualiza o contador sem marcar erro
    if (contadorDescricao && descricao) contadorDescricao.textContent = descricao.value.trim().length;

    function preencherConfirmacao() {
      document.getElementById('confTipo').textContent = tipo.options[tipo.selectedIndex]?.text || 'Não informado';
      document.getElementById('confNome').textContent = valorOuPadrao(document.getElementById('nome').value, 'Anônimo');
      document.getElementById('confPerfil').textContent = valorOuPadrao(perfil.options[perfil.selectedIndex]?.text, 'Não informado');
      document.getElementById('confEmail').textContent = valorOuPadrao(email.value);
      const turmaValor = valorOuPadrao(document.getElementById('turma_setor').value);
      const confTurma = document.getElementById('confTurmaSetor');
      confTurma.textContent = turmaValor;
      confTurma.className = 'badge-curso-preview ' + classeCurso(turmaValor);
      document.getElementById('confSetorRelacionado').textContent = valorOuPadrao(setor.options[setor.selectedIndex]?.text);
      document.getElementById('confAssunto').textContent = valorOuPadrao(assunto.value);
      document.getElementById('confDescricao').textContent = valorOuPadrao(descricao.value);
    }

    function abrirModalConfirmacao() {
      preencherConfirmacao();
      modalConfirmacao.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }

    function fecharModalConfirmacao() {
      if (!modalConfirmacao) return;
      modalConfirmacao.style.display = 'none';
      document.body.style.overflow = '';
    }

    btnAbrirConfirmacao?.addEventListener('click', function () {
      const ok = [validarTipo(), validarAssunto(), validarDescricao(), validarEmailManifestacao()].every(Boolean);
      if (!ok) {
        formManifestacao.querySelector('.campo-erro')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }
      abrirModalConfirmacao();
    });

    btnFecharModal?.addEventListener('click', fecharModalConfirmacao);
    btnVoltarEdicao?.addEventListener('click', fecharModalConfirmacao);
    modalConfirmacao?.addEventListener('click', function (e) {
      if (e.target === modalConfirmacao) fecharModalConfirmacao();
    });
    btnConfirmarEnvio?.addEventListener('click', function () {
      formManifestacao.submit();
    });
  }
});

// ─── MÁSCARA DE TELEFONE ──────────────────────────────────────────────────
(function () {
  function mascaraTelefone(el) {
    el.addEventListener('input', function () {
      let v = this.value.replace(/\D/g, '').substring(0, 11);
      if (v.length > 10) {
        v = v.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
      } else if (v.length > 6) {
        v = v.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
      } else if (v.length > 2) {
        v = v.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
      } else if (v.length > 0) {
        v = v.replace(/^(\d*)/, '($1');
      }
      this.value = v;
    });
    // Bloqueia teclas não numéricas
    el.addEventListener('keydown', function (e) {
      const allow = ['Backspace','Delete','ArrowLeft','ArrowRight','Tab','Home','End'];
      if (!allow.includes(e.key) && !/^\d$/.test(e.key)) e.preventDefault();
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[type="tel"], #mcTelefone').forEach(mascaraTelefone);
  });
})();

// ─── AUTO-DISMISS FLASH MESSAGES ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  const flash = document.querySelector('.flash-msg');
  if (flash) {
    setTimeout(() => {
      flash.style.transition = 'opacity .4s, transform .4s';
      flash.style.opacity = '0';
      flash.style.transform = 'translateY(-8px)';
      setTimeout(() => flash.parentElement && flash.parentElement.remove(), 400);
    }, 4500);
  }
});
