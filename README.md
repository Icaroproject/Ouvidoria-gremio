# Ouvidoria do Grêmio Escolar
### EEEP Dom Walfrido Teixeira Vieira · Sobral, Ceará

> Sistema web de ouvidoria escolar desenvolvido para o Grêmio Estudantil da EEEP Dom Walfrido Teixeira Vieira. Permite que alunos, responsáveis e comunidade registrem manifestações de forma anônima ou identificada, com acompanhamento em tempo real por protocolo.

**Versão:** `1.5.0`  
**Stack:** PHP 8.2 · MySQL · Bootstrap 5 · Chart.js · PHPMailer  
**Ambiente:** Apache (XAMPP/WAMP) · `localhost`

---

## Funcionalidades

### Para o público
- Envio de manifestações em 4 categorias: **Sugestão, Elogio, Reclamação, Denúncia**
- Modo **anônimo** ou **identificado**, com escolha explícita antes do envio
- Revisão dos dados antes do envio final (modal de confirmação)
- Anexo de arquivos (imagens, PDFs, documentos — máx. 10 MB cada)
- Acompanhamento por **protocolo** (`GRE-AAAAMMDD-XXXXXX`) com timeline de status
- Validação de protocolo em tempo real (AJAX)
- **Conversa direta** com o Grêmio pela página de acompanhamento
- **Avaliação de satisfação** com estrelas (1–5) e comentário opcional
- E-mail de confirmação automático com o protocolo ao enviar
- E-mail automático ao aluno quando o status é atualizado

### Para o usuário cadastrado
- Cadastro e login com CPF e senha
- Painel `Minha Conta` com histórico de manifestações
- Foto de perfil com upload direto
- Notificações em tempo real (sino) para respostas e atualizações
- Recuperação de senha por e-mail com token seguro

### Para o administrador (Grêmio)
- Painel administrativo com visão geral de todas as manifestações
- Filtros por tipo, status, período (data início/fim) e busca por texto
- **Paginação** (10 por página) para grandes volumes
- **Exportar CSV** com todas as manifestações (compatível com Excel)
- **Arquivar e excluir** manifestações individualmente
- Resposta direta ao aluno pelo painel (chat interno)
- Atualização de status com feedback e histórico completo de movimentações
- Notificação interna ao aluno ao mover status
- Dashboard com gráficos em tempo real:
  - Evolução de manifestações nos últimos 30 dias (gráfico de linha)
  - Distribuição por status (donut)
  - Distribuição por tipo (barras)
  - Distribuição por curso (barras horizontais)
  - Anônimas vs. identificadas (donut)
  - Filtro de período aplicável a todos os gráficos

---

## Instalação

### Pré-requisitos
- PHP 8.1 ou superior
- MySQL 5.7 ou superior
- Apache com `mod_rewrite`
- XAMPP, WAMP ou equivalente

### Passo a passo

**1. Copie a pasta para o servidor**
```
htdocs/projeto_final/
```

**2. Importe o banco de dados**

Abra o phpMyAdmin e execute o arquivo:
```
database/schema.sql
```

**3. Configure o sistema**

Edite `config/config.php`:
```php
// Banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'dbouvidoria');
define('DB_USER', 'root');
define('DB_PASS', '');

// URL — altere se mudar o nome da pasta
define('APP_URL',  'http://localhost/projeto_final');
define('BASE_URL', '/projeto_final/');

// SMTP (Gmail, por exemplo)
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'seu@email.com');
define('MAIL_PASSWORD', 'sua_senha_de_app');
```

**4. Acesse no navegador**
```
http://localhost/projeto_final
```

### Credenciais padrão (do schema.sql)

| Perfil | E-mail | Senha |
|--------|--------|-------|
| Administrador | `admin@gremio.com` | `123456` |
| Aluno teste | `aluno@gremio.com` | `123456` |

> ⚠️ Troque as senhas padrão antes de colocar em produção.

---

## Estrutura do projeto

```
projeto_final/
│
├── index.php                      ← Página inicial
│
├── app/
│   ├── manifestacao.php           ← Envio de manifestação
│   ├── acompanhar.php             ← Consulta por protocolo
│   ├── notificacoes.php           ← Central de notificações
│   ├── sobre.php                  ← Sobre a escola
│   │
│   ├── auth/
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── forgot_password.php
│   │   ├── reset_password.php
│   │   └── email_enviado.php
│   │
│   └── painel/
│       ├── adm.php                ← Painel do Grêmio (admin)
│       ├── dashboard.php          ← Dashboard com gráficos
│       └── minha_conta.php        ← Painel do aluno
│
├── assets/
│   ├── css/style.css
│   ├── js/app.js
│   └── images/
│
├── config/
│   ├── bootstrap.php              ← Include único para todas as páginas
│   ├── config.php                 ← ⚙️ Configurações (banco, e-mail, URL)
│   ├── paths.php                  ← Constantes de caminho
│   └── functions.php              ← Funções globais
│
├── includes/
│   ├── header.php                 ← Topbar, drawer mobile, Bootstrap CSS
│   └── footer.php                 ← Bootstrap JS, scripts globais
│
├── lib/                           ← PHPMailer
├── storage/
│   ├── fotos/                     ← Fotos de perfil
│   └── manifestacoes/             ← Anexos das manifestações
│
└── database/
    └── schema.sql
```

---

## Boas práticas do código

Cada página começa com um único `require_once` do bootstrap:

```php
// Da raiz (index.php)
require_once __DIR__ . '/config/bootstrap.php';

// De app/
require_once __DIR__ . '/../config/bootstrap.php';

// De app/auth/ ou app/painel/
require_once __DIR__ . '/../../config/bootstrap.php';
```

Use sempre as constantes de caminho — nunca strings hardcoded:

```php
// ✅ Correto
$dir = STORAGE_MANIFESTACOES;
require_once LIB_PATH . '/src/PHPMailer.php';

// ❌ Evitar
$dir = __DIR__ . '/../../storage/manifestacoes/';
```

Para links e redirects, use sempre `BASE_URL` ou `$_base`:

```php
// Redirect PHP
header('Location: ' . BASE_URL . 'app/acompanhar.php?protocolo=' . $protocolo);

// Link HTML (via header.php — $_base já está disponível)
<a href="<?= $_base ?>app/manifestacao.php">Fazer Manifestação</a>
```

---

## Segurança

- Senhas armazenadas com `password_hash()` (bcrypt, custo 12)
- Todas as queries usam **PDO com prepared statements**
- Saída de dados sempre sanitizada via `e()` (wrapper de `htmlspecialchars`)
- Uploads validados por MIME type e extensão
- Pasta `storage/` protegida por `.htaccess` (bloqueia execução de PHP)
- Tokens de reset de senha com expiração de 1 hora
- Sessões PHP com regeneração de ID no login

---

## Changelog

### v1.5.0 — atual
- Arquivar e excluir manifestações no painel do admin
- Exportação de manifestações em CSV (compatível com Excel/LibreOffice)
- Filtro por período (data início e fim) no painel e no dashboard
- Paginação no painel administrativo (10 por página)
- Filtro de período aplicável aos gráficos do dashboard
- Avaliação de satisfação por estrelas (1–5) substituindo o sim/não
- Campo de comentário junto à avaliação
- Correção: campo de descrição aparecia vermelho ao carregar a página
- Correção: foto de perfil não carregava após o upload
- Correção: notificações de nova manifestação levavam para página 404
- Correção: CSS/JS não carregava em nenhuma página (BASE_URL incorreta)
- Melhorias de responsividade em mobile para todas as telas

### v1.0.0
- Lançamento inicial
- Formulário de manifestação (anônima/identificada)
- Acompanhamento por protocolo com timeline
- Painel administrativo com gestão de manifestações
- Sistema de chat entre aluno e Grêmio
- Notificações internas e por e-mail
- Dashboard com 5 gráficos Chart.js
- Autenticação com recuperação de senha
- Upload de arquivos e foto de perfil

---

## Licença

Projeto desenvolvido para uso interno da **EEEP Dom Walfrido Teixeira Vieira**.  
Distribuição e uso comercial não autorizados.

---

*Desenvolvido por Icaro Martins Azevedo · 2026*
