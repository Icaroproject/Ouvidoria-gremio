<?php
require_once __DIR__ . '/../../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'login';

    if ($acao === 'login') {
        $email = trim($_POST['email'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        $tipoAcesso = $_POST['tipo_acesso'] ?? 'adm';
        $lembrar = isset($_POST['lembrar_me']);

        if ($email === '' || $senha === '') {
            flash('erro', 'Preencha e-mail e senha para continuar.');
            header('Location: /projeto_final/app/auth/login.php');
            exit;
        }

        try {
            $pdo = conectarPDO();

            if ($tipoAcesso === 'adm') {
                $stmt = $pdo->prepare('SELECT IDadm, nome, email, senha FROM tbadm WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                $admin = $stmt->fetch();

                if (!$admin || !senhaConfere($senha, (string) $admin['senha'])) {
                    flash('erro', 'Login incorreto. E-mail ou senha do administrador estão errados.');
                    header('Location: /projeto_final/app/auth/login.php');
                    exit;
                }

                $_SESSION['admin'] = [
                    'id' => $admin['IDadm'],
                    'nome' => $admin['nome'],
                    'email' => $admin['email'],
                ];
                unset($_SESSION['usuario']);

                if ($lembrar) {
                    setRememberMeCookies($email, $tipoAcesso);
                } else {
                    clearRememberMeCookies();
                }

                flash('sucesso', 'Login administrativo realizado com sucesso.');
                header('Location: /projeto_final/app/painel/dashboard.php');
                exit;
            }

            $stmt = $pdo->prepare('SELECT IDusu, nome, email, senha FROM tbusuarios WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $usuario = $stmt->fetch();

            if (!$usuario || !senhaConfere($senha, (string) $usuario['senha'])) {
                flash('erro', 'Login incorreto. Usuário não encontrado ou senha inválida.');
                header('Location: /projeto_final/app/auth/login.php');
                exit;
            }

            $_SESSION['usuario'] = [
                'id' => $usuario['IDusu'],
                'nome' => $usuario['nome'],
                'email' => $usuario['email'],
            ];
            unset($_SESSION['admin']);

            if ($lembrar) {
                setRememberMeCookies($email, $tipoAcesso);
            } else {
                clearRememberMeCookies();
            }

            flash('sucesso', 'Login realizado com sucesso.');
            header('Location: /projeto_final/app/painel/minha_conta.php');
            exit;
        } catch (PDOException $e) {
            flash('erro', 'Erro ao consultar o banco de dados com PDO. Verifique a conexão.');
            header('Location: /projeto_final/app/auth/login.php');
            exit;
        }
    }

    if ($acao === 'cadastro') {
        $nome = trim($_POST['nome'] ?? '');
        $cpf = trim($_POST['cpf'] ?? '');
        $perfil = trim($_POST['perfil'] ?? '');
        $email = trim($_POST['email_cadastro'] ?? '');
        $senha = trim($_POST['senha_cadastro'] ?? '');
        $confirmar = trim($_POST['confirmar_senha'] ?? '');

        if ($nome === '' || $cpf === '' || $email === '' || $senha === '' || $confirmar === '') {
            flash('erro', 'Preencha os campos obrigatórios do cadastro.');
            header('Location: /projeto_final/app/auth/login.php#cadastro');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('erro', 'Informe um e-mail válido.');
            header('Location: /projeto_final/app/auth/login.php#cadastro');
            exit;
        }

        if (!validarCPF($cpf)) {
            flash('erro', 'CPF inválido.');
            header('Location: /projeto_final/app/auth/login.php#cadastro');
            exit;
        }

        if ($senha !== $confirmar) {
            flash('erro', 'As senhas do cadastro não coincidem.');
            header('Location: /projeto_final/app/auth/login.php#cadastro');
            exit;
        }

        if (mb_strlen($senha) < 8) {
            flash('erro', 'A senha deve ter pelo menos 8 caracteres.');
            header('Location: /projeto_final/app/auth/login.php#cadastro');
            exit;
        }

        try {
            $pdo = conectarPDO();
            $cpfLimpo = preg_replace('/\D/', '', $cpf);

            if (cpfJaCadastrado($pdo, $cpfLimpo)) {
                flash('erro', 'Este CPF já está cadastrado no sistema.');
                header('Location: /projeto_final/app/auth/login.php#cadastro');
                exit;
            }

            $verifica = $pdo->prepare('SELECT IDusu FROM tbusuarios WHERE email = :email LIMIT 1');
            $verifica->execute([':email' => $email]);

            if ($verifica->fetch()) {
                flash('erro', 'Já existe um cadastro com esse e-mail.');
                header('Location: /projeto_final/app/auth/login.php#cadastro');
                exit;
            }

            $stmt = $pdo->prepare('INSERT INTO tbusuarios (nome, cpf, perfil, email, senha) VALUES (:nome, :cpf, :perfil, :email, :senha)');
            $stmt->execute([
                ':nome' => $nome,
                ':cpf' => $cpfLimpo,
                ':perfil' => $perfil,
                ':email' => $email,
                ':senha' => password_hash($senha, PASSWORD_DEFAULT),
            ]);

            flash('sucesso', 'Cadastro realizado com sucesso. Agora você já pode entrar.');
            header('Location: /projeto_final/app/auth/login.php?cadastro=ok');
            exit;
        } catch (PDOException $e) {
            flash('erro', 'Não foi possível concluir o cadastro no banco de dados.');
            header('Location: /projeto_final/app/auth/login.php#cadastro');
            exit;
        }
    }
}

$emailLembrado = $_COOKIE['remember_email'] ?? '';
$tipoLembrado = $_COOKIE['remember_tipo'] ?? 'adm';

$tituloPagina = 'Login e Cadastro — Ouvidoria do Grêmio Escolar';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
  <div class="page-header-inner">
    <span class="section-label">ACESSO À PLATAFORMA</span>
    <h1>Login & <em>Cadastro</em></h1>
    <p>Entre no sistema, acompanhe sua conta e participe da melhoria da vida escolar.</p>
  </div>
</div>

<div class="auth-section">
  <div class="auth-card">
    <div class="auth-tabs">
      <button class="auth-tab active" type="button" data-panel="login"><i class="fa-solid fa-right-to-bracket" style="margin-right:8px;color:var(--laranja)"></i>Login</button>
      <button class="auth-tab" type="button" data-panel="cadastro"><i class="fa-solid fa-user-plus" style="margin-right:8px;color:var(--verde)"></i>Cadastrar-se</button>
    </div>

    <div class="auth-body">
      <div class="auth-panel active" id="login">
        <h2 class="auth-title">Entrar na ouvidoria</h2>
        <p class="auth-sub">Use sua conta para registrar manifestações e acompanhar seus dados.</p>

        <form method="post" autocomplete="off" id="formLogin">
          <input type="hidden" name="acao" value="login">

          <div class="form-group">
            <label for="tipo_acesso">Tipo de acesso</label>
            <select name="tipo_acesso" id="tipo_acesso" class="form-control">
              <option value="adm" <?= $tipoLembrado === 'adm' ? 'selected' : '' ?>>Administrador / Grêmio</option>
              <option value="usuario" <?= $tipoLembrado === 'usuario' ? 'selected' : '' ?>>Usuário</option>
            </select>
          </div>

          <div class="form-group">
            <label for="loginEmail"><i class="fa-solid fa-envelope" style="color:var(--laranja);margin-right:6px"></i>E-mail</label>
            <input type="email" name="email" id="loginEmail" class="form-control" placeholder="seunome@exemplo.com" value="<?= e($emailLembrado) ?>">
          </div>

          <div class="form-group">
            <label for="loginSenha"><i class="fa-solid fa-lock" style="color:var(--laranja);margin-right:6px"></i>Senha</label>
            <input type="password" name="senha" id="loginSenha" class="form-control" placeholder="••••••••">
          </div>

          <label class="remember-check">
            <input type="checkbox" name="lembrar_me" <?= $emailLembrado !== '' ? 'checked' : '' ?>>
            <span>Lembrar de mim</span>
          </label>

          <div style="text-align:right;margin-bottom:24px">
            <a href="<?= $_base ?>app/auth/forgot_password.php" style="font-size:0.82rem;color:var(--laranja);text-decoration:none;font-weight:600">Esqueci minha senha</a>
          </div>

          <button type="submit" class="btn-submit"><i class="fa-solid fa-right-to-bracket"></i> Entrar na plataforma</button>
        </form>
      </div>

      <div class="auth-panel" id="cadastro">
        <h2 class="auth-title">Criar conta de usuário</h2>
        <p class="auth-sub">Cadastre-se para registrar manifestações e gerenciar seus dados pessoais.</p>

        <form method="post" autocomplete="off" id="formCadastro">
          <input type="hidden" name="acao" value="cadastro">

          <div class="form-group">
            <label for="cadNome">Nome completo</label>
            <input type="text" name="nome" id="cadNome" class="form-control" placeholder="Seu nome completo">
          </div>

          <div class="form-group">
            <label for="cadCPF">CPF</label>
            <input type="text" name="cpf" id="cadCPF" class="form-control" data-mask="cpf" placeholder="000.000.000-00">
          </div>

          <div class="form-group">
            <label for="cadPerfil">Perfil</label>
            <select name="perfil" id="cadPerfil" class="form-control">
              <option value="">Selecione</option>
              <option value="Aluno(a)">Aluno(a)</option>
              <option value="Responsável">Responsável</option>
              <option value="Professor(a)">Professor(a)</option>
              <option value="Servidor(a)">Servidor(a)</option>
              <option value="Comunidade">Comunidade</option>
            </select>
          </div>

          <div class="form-group">
            <label for="cadEmail">E-mail</label>
            <input type="email" name="email_cadastro" id="cadEmail" class="form-control" placeholder="seunome@exemplo.com">
          </div>

          <div class="form-group">
            <label for="cadSenha">Senha</label>
            <input type="password" name="senha_cadastro" id="cadSenha" class="form-control" placeholder="Crie uma senha segura">
            <div class="forca-wrap">
              <div class="forca-trilho"><div id="barraSenha"></div></div>
              <span id="labelSenha"></span>
            </div>
          </div>

          <div class="form-group">
            <label for="cadConfSenha">Confirmar senha</label>
            <input type="password" name="confirmar_senha" id="cadConfSenha" class="form-control" placeholder="Repita a senha">
          </div>

          <button type="submit" class="btn-green"><i class="fa-solid fa-user-plus"></i> Concluir cadastro</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
