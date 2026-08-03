# Publicação do SIGESP em `/sigesp`

## Requisitos

- PHP 8.2 ou superior.
- Extensões `pdo_mysql`, `mbstring`, `fileinfo`, `openssl` e `json`.
- Apache 2.4 com `mod_rewrite` e `AllowOverride` habilitado para a pasta pública.
- Composer.
- MySQL ou MariaDB.

## Estrutura recomendada

Mantenha o código privado fora de `public_html` e publique somente o conteúdo de `public/`:

```text
/home/USUARIO/
├── apps/
│   └── sigesporte/
│       ├── app/
│       ├── bootstrap/
│       ├── config/
│       ├── database/
│       ├── routes/
│       ├── storage/
│       ├── vendor/
│       ├── .env
│       └── composer.json
└── public_html/
    └── sigesp/
        ├── index.php
        ├── .htaccess
        └── assets/
```

Copie `public/index.php`, `public/.htaccess` e `public/assets/` para `public_html/sigesp/`. O `index.php` procura primeiro a estrutura do repositório, depois `/home/USUARIO/apps/sigesporte` e, como alternativa, `app-private` dentro da pasta pública.

Se a hospedagem obrigar a manter o projeto em `public_html/sigesp/app-private`, adicione `app-private/.htaccess` com `Require all denied` e confirme por HTTP que `.env`, `app`, `bootstrap`, `config`, `database`, `routes`, `storage` e `vendor` retornam 403 ou 404. O include PHP interno continua funcionando. Esta alternativa é inferior à estrutura privada acima.

O 403 observado em `https://lucascorrea.pro/sigesp/`, combinado com 404 nas rotas e nos assets, indica que o front controller publicado não está sendo alcançado. O motivo exato — arquivo ausente, pasta aninhada, `DirectoryIndex`, permissões, proprietário ou `DocumentRoot` — deve ser confirmado no painel e no log do Apache. A implantação da estrutura acima elimina a divergência local conhecida.

## Configuração do `.env`

Crie o `.env` somente na pasta privada, nunca em `public_html/sigesp`:

```env
APP_NAME=SIGESP
APP_ENV=production
APP_DEBUG=false
APP_URL=https://lucascorrea.pro/sigesp
APP_BASE_PATH=/sigesp
APP_TIMEZONE=America/Manaus
APP_TRUST_PROXY_HEADERS=false

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sigesporte
DB_USERNAME=USUARIO_DO_BANCO
DB_PASSWORD=SENHA_FORTE_DO_BANCO

SESSION_LIFETIME=120
UPLOAD_MAX_SIZE=10485760
```

Mantenha `APP_TRUST_PROXY_HEADERS=false`. Altere para `true` somente quando o tráfego chegar exclusivamente por um proxy reverso confiável que sobrescreva `X-Forwarded-Proto`; isso permite marcar o cookie de sessão como seguro atrás do proxy.

No desenvolvimento local, use `APP_URL=http://localhost:8080`, `APP_BASE_PATH=` e execute:

```bash
php -S localhost:8080 -t public
```

Não inicie o servidor embutido do PHP com o `DocumentRoot` na raiz do projeto: ele ignora arquivos `.htaccess` e exporia o código privado. O alvo deve ser sempre `public/`.

## Composer

Na pasta privada da aplicação, execute:

```bash
composer install --no-dev --optimize-autoloader
```

Se o servidor não tiver Composer, execute o comando localmente com PHP 8.2 compatível e envie a pasta `vendor/` gerada para a área privada.

## Banco de dados

Em uma instalação nova, faça backup e valide as variáveis do banco antes de executar:

```bash
php database/migrate.php
ADMIN_INITIAL_PASSWORD='uma-senha-temporaria-forte' php database/seed.php
```

Não execute `migrate.php` sobre uma base existente sem revisar `database/schema.sql`: o script aplica o schema completo. O seed é apenas para a instalação inicial; troque imediatamente a senha temporária. Em PowerShell, defina `ADMIN_INITIAL_PASSWORD` no ambiente antes do segundo comando.

## Permissões

- Pastas: `755`.
- Arquivos: `644`.
- `storage/logs`, `storage/cache`, `storage/temp` e `storage/app/private`: `775` somente se o usuário/grupo do PHP precisar escrever.
- Nunca use `777` e nunca habilite listagem de diretórios.

## Verificação após publicar

Confira no painel se `public_html/sigesp/index.php`, `.htaccess` e `assets/` estão diretamente na pasta `/sigesp`, e não em `/sigesp/public`. Teste:

```text
/sigesp/
/sigesp/login
/sigesp/dashboard
/sigesp/atletas
/sigesp/atletas/
/sigesp/atletas/10
/sigesp/atletas?page=2
/sigesp/atletas/novo
/sigesp/modalidades
/sigesp/equipes
/sigesp/relatorios
/sigesp/assets/css/app.css
/sigesp/assets/js/app.js
```

Valide login inválido, login válido, CSRF, navegação, logout e expiração da sessão. As rotas autenticadas devem levar o visitante sem sessão a `/sigesp/login`.

Confirme também que os recursos abaixo retornam 403 ou 404 e nunca exibem conteúdo:

```text
/sigesp/.env
/sigesp/config/app.php
/sigesp/database/schema.sql
/sigesp/storage/logs/app.log
/sigesp/vendor/
/sigesp/diagnostico.php.example
```

Se a raiz continuar em 403, consulte o log do Apache e confirme `DocumentRoot`, proprietário/permissões, `DirectoryIndex`, `mod_rewrite` e se `AllowOverride` permite o `.htaccess`. Diretivas incompatíveis no `.htaccess` costumam produzir 500, que também deve ser investigado no log do servidor.

## Diagnóstico temporário

`public/diagnostico.php.example` é bloqueado pelo `.htaccess`. Para diagnóstico controlado, copie-o temporariamente como `diagnostico.php`, habilite a variável de ambiente do servidor `SIGESP_DIAGNOSTICS=true`, acesse o arquivo, remova-o imediatamente e desabilite a variável. O arquivo não mostra credenciais nem o conteúdo do `.env`.
