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
