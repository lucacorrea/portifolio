# PCCT — Ensino Médio

Repositório de objetos de aprendizagem desenvolvido em Laravel 9 / PHP 8.x.
Perfis: administrador, professor e estudante.

## Executar localmente

1. Instale PHP 8.x, Composer e MariaDB/MySQL (Laragon ou XAMPP).
2. Entre na pasta `portifolio/pcct`.
3. Execute `composer install`.
4. Copie `.env.example` para `.env` (PowerShell: `Copy-Item .env.example .env`).
5. Crie um banco vazio `pcct`, configure `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` no `.env`.
6. Execute `php artisan key:generate`.
7. Execute `php artisan migrate`. Alternativamente, se já importou `database/PCCT_estrutura_banco.sql`, **execute também** `php artisan migrate` para aplicar os campos complementares.
8. Execute `php artisan db:seed` para cadastrar disciplinas básicas.
9. Execute `php artisan pcct:admin` para criar um administrador: o comando pedirá e-mail, nome e senha (mínimo 12 caracteres). Nenhuma senha padrão está no repositório.
10. Inicie com `php artisan serve` e acesse `http://127.0.0.1:8000`.

O estilo é servido localmente de `public/css/pcct.css`, sem necessidade de instalar Node/npm.
Anexos são guardados no armazenamento privado do Laravel e servidos por rotas verificadas; não é necessário rodar `storage:link`.

## Funcionalidades disponíveis

- Catálogo público com filtros de texto, disciplina, série do Ensino Médio, formato e BNCC.
- Cadastro público de estudantes, com senha criptografada.
- Login e logout com controle de perfis.
- Admin cadastra instituição, professor, estudante, disciplina e habilidade BNCC.
- Professor cria, edita, exclui e publica materiais, incluindo rascunhos.
- Associação com habilidades BNCC por disciplina.
- Arquivos locais (até 10 MB), capas (até 2 MB), links HTTP/HTTPS.
- Rascunhos só podem ser visualizados pelo autor ou administrador.

## Testes automatizados

Os testes estão configurados para usar **SQLite em memória**; isso evita modificar o MySQL da sua máquina. Habilite a extensão `pdo_sqlite` do PHP e execute:

```bash
php artisan test --filter=PcctWorkflowTest
```

Os testes cobrem o cadastro do estudante, as restrições de professor, a publicação e a privacidade de rascunhos.

## Cuidados

- Cadastre somente habilidades oficiais da BNCC; os códigos e descrições precisam ser conferidos antes de entrar no acervo.
- Em produção, desative `APP_DEBUG`, use HTTPS e revise a política de acesso a contas de estudantes.
- O comando `pcct:admin` deve ser usado apenas por quem administra o servidor local.
- A migration complementar não remove tabelas na reversão para proteger bancos importados manualmente.
