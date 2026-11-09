# PCCT — Ensino Médio

Sistema em **Laravel 9 + PHP + MySQL/MariaDB**, com biblioteca de objetos de aprendizagem e áreas de administrador, professor e estudante.

## Configuração única por ambiente: `.env`

O PCCT utiliza **somente o arquivo `.env` da instalação** para configurar banco de dados e aplicação. O arquivo `.env.example` é apenas um modelo neutro para ser copiado; **não é carregado pelo Laravel enquanto existe `.env`**.

### Para quem for instalar localmente

1. Entre na pasta `pcct/`.
2. Copie `.env.example` para `.env`: no Windows PowerShell, `Copy-Item .env.example .env`.
3. Edite apenas o seu próprio `.env` e informe a conexão com o MySQL local:
   ```ini
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://127.0.0.1:8000

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=pcct
   DB_USERNAME=root
   DB_PASSWORD=
   ```
4. Prepare um banco MySQL vazio com o nome usado em `DB_DATABASE`.
5. Se ainda não existir `vendor/`, será preciso instalar as dependências do Laravel (normalmente uma vez, com `composer install`). Se `vendor/` já estiver pronto, não precisa reinstalar a cada execução.
6. Execute `php artisan key:generate` **somente na primeira configuração**, quando `APP_KEY` estiver vazio.
7. Execute `php artisan migrate`, depois `php artisan db:seed`.
8. Crie o primeiro administrador com `php artisan pcct:admin`.
9. Inicie com `php artisan serve` e abra `http://127.0.0.1:8000`.

### Hospedagem

No servidor, use **esse mesmo arquivo `.env`**, com os valores próprios daquele ambiente: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` público, host, usuário, banco e senha do MySQL da hospedagem. **Não substitua o `APP_KEY` de uma instalação já existente.**

O Laravel já lê as variáveis via `config/database.php`, sem valores fixos de banco no código. Se estiver usando cache de configurações, execute `php artisan config:clear` após atualizar o `.env`. O deploy automático precisa preservar `.env` e `storage/` entre publicações e ter as dependências em `vendor/`.

**Segurança:** nunca publique o `.env`, senhas ou credenciais no GitHub. Se uma senha aparecer no histórico do repositório, troque-a imediatamente no provedor e atualize o `.env` da instalação afetada.

**Banco já importado:** confira as colunas complementares previstas na migration `2026_10_30_000000_pcct_ensino_medio.php` antes de usar o cadastro de materiais. Faça backup antes de aplicar alterações estruturais em produção e não rode `migrate:fresh` no banco da hospedagem.

## Funcionalidades

- Biblioteca com filtros de texto, disciplina, série, formato e habilidades BNCC.
- Cadastro de estudantes e autenticação com perfis.
- Administração de instituições, usuários, disciplinas e habilidades.
- Professor cadastra, edita, publica e exclui materiais.
- Anexos e rascunhos protegidos por verificações de permissão.

## Testes

Com `pdo_sqlite` habilitado, rode `php artisan test --filter=PcctWorkflowTest`. Os testes utilizam SQLite em memória, sem modificar o banco hospedado.

## Publicação em subpasta /pcct/

A pasta `pcct/.htaccess` redireciona internamente as URLs de `/pcct/` para `pcct/public/` e bloqueia o acesso HTTP às pastas privadas do Laravel. A `public/.htaccess` continua responsável pelas rotas da aplicação. **O arquivo oculto `.htaccess` também precisa ser enviado na implantação automática.**

Se a hospedagem permitir alterar a raiz web, a opção mais segura e recomendada é configurar o DocumentRoot diretamente para `pcct/public/`, dispensando a regra de adaptação da raiz. Se o domínio já aponta para `pcct/public/`, use somente as regras de `public/.htaccess` e não redirecione duas vezes.

Se aparecer **403 Forbidden** no endereço do PCCT, confira: a existência de `pcct/.htaccess` e de `pcct/public/index.php` na hospedagem, o módulo Apache mod_rewrite, a publicação de arquivos ocultos e as permissões de leitura. Se mudar para erro **500**, examine os logs do Laravel/servidor e confirme as dependências `vendor/` e o `.env` com `APP_KEY`. Nunca ative `APP_DEBUG=true` em ambiente público.

## Dependências Laravel com publicação pela main

O envio automático da branch `main` para a Hostinger **não instala a pasta `vendor/`** (ela é ignorada pelo Git). Para evitar o erro `vendor/autoload.php not found`, foi criado o workflow [`.github/workflows/pcct-vendor.yml`](../../.github/workflows/pcct-vendor.yml).

A cada atualização da pasta `pcct/` na `main`, o GitHub Actions instala as dependências do `composer.lock` com PHP 8.2, monta o `vendor/` e, **somente após configurar SSH**, transfere essa pasta para a instalação em `/home/u784961086/domains/lucascorrea.pro/public_html/pcct`. O Git da Hostinger continua enviando o restante do código. O workflow **não sobrescreve o `.env`** e **não modifica o banco**.

### Configuração inicial (uma vez)

1. No hPanel da Hostinger, ative o acesso SSH e autorize a **chave pública** de uma conta de implantação.
2. Em **GitHub → repositório portifolio → Settings → Secrets and variables → Actions**, adicione os segredos abaixo:

   | Secret | Conteúdo |
   |---|---|
   | `PCCT_SSH_HOST` | Endereço/IP do SSH mostrado no hPanel |
   | `PCCT_SSH_USER` | Usuário SSH mostrado no hPanel |
   | `PCCT_SSH_PORT` | Porta SSH do hPanel |
   | `PCCT_SSH_PRIVATE_KEY` | Chave privada correspondente à chave pública cadastrada no hPanel |
   | `PCCT_SSH_KNOWN_HOSTS` | Linha do `known_hosts` verificada com a impressão digital SSH do hPanel |

3. Abra **Actions → PCCT - Dependências Laravel na Hostinger → Run workflow** na branch `main`. Acompanhe o resultado. A configuração só precisa ser refeita quando as chaves ou o destino mudarem.

> Nunca coloque a chave privada, senha SSH ou senha do banco em arquivos versionados. Se os segredos SSH não estiverem configurados, a etapa de instalação gera o `vendor/` no GitHub Actions, mas **não consegue entregá-lo ao servidor**. O acesso SSH não é substituído pela senha do MySQL.

O processo pressupõe que o código PCCT já esteja implantado no caminho acima e que a Hostinger permita SSH, SCP e TAR; não foi validado diretamente na hospedagem.

## Conexão PDO independente (opcional)

O arquivo `conexao.php` permite acessar o MySQL em scripts PHP independentes do Laravel, usando as mesmas variáveis `DB_*` do `.env`, sem duplicar usuário ou senha:

```php
<?php
$pdo = require __DIR__ . '/conexao.php';
$disciplinas = $pdo->query('SELECT id, nome FROM disciplinas ORDER BY nome')->fetchAll();
```

Esse recurso não substitui `config/database.php` e **não resolve a ausência de `vendor/autoload.php`**. As páginas Laravel continuam dependendo da pasta `vendor/` instalada na Hostinger. Não crie um endpoint público que exponha erros ou credenciais do PDO.

### Quando aparecer erro de vendor/autoload.php ausente

O Laravel **não funciona apenas com a conexão PDO**, porque todas as rotas precisam do autoload das dependências. No [workflow PCCT](../../.github/workflows/pcct-vendor.yml), o GitHub instala e empacota o `vendor`. O pacote está disponível como artefato `pcct-vendor` na execução do GitHub Actions por 7 dias, útil como alternativa manual se o SSH ainda não estiver configurado.

- **Publicação automática real:** configure os cinco segredos SSH no GitHub Actions. Sem eles o workflow agora **falha explicitamente**, mesmo que tenha conseguido gerar o pacote, para não indicar que o site foi atualizado.
- **Alternativa manual para desbloquear:** baixe o artefato `pcct-vendor` na execução do Actions, extraia `pcct-vendor.tar.gz` mantendo a estrutura `vendor/` e coloque essa pasta ao lado de `artisan` e `composer.json` na Hostinger. Não coloque dentro de `public/`.
- No servidor, `pcct/vendor/autoload.php` deve existir. Se faltar, a entrada web retorna HTTP 503 sem mostrar o caminho absoluto da hospedagem.

**Observação:** o download manual pode resolver o erro de dependências da primeira publicação, mas, sem SSH configurado, não transforma o envio da pasta `vendor/` em automático.
