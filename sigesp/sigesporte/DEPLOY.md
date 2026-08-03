# Publicação da demonstração SIGESP em `/sigesp`

## Requisitos

- PHP 8.2 ou superior com `mbstring`.
- Apache 2.4 com `mod_rewrite` e `AllowOverride` habilitados.
- Pastas com permissão `755` e arquivos com `644`.

A demonstração não requer MySQL, PDO, migrations, seeders, Composer, Dompdf ou PhpSpreadsheet.

## Estrutura recomendada

Mantenha o código PHP fora da área pública e publique somente o front controller e os assets:

```text
/home/USUARIO/
├── apps/
│   └── sigesporte/
│       ├── app/
│       ├── bootstrap/
│       ├── config/
│       ├── routes/
│       ├── public/
│       └── .env
└── public_html/
    └── sigesp/
        ├── index.php
        ├── .htaccess
        └── assets/
```

Copie `public/index.php`, `public/.htaccess` e `public/assets/` diretamente para `public_html/sigesp/`. Copie o restante da aplicação para `/home/USUARIO/apps/sigesporte/`. O front controller já procura essa pasta privada.

No layout atual do repositório também existe um front controller em `sigesp/index.php` e uma regra em `sigesp/.htaccess`; essa topologia funciona no monorepositório, mas a separação acima reduz a superfície pública na hospedagem.

## Configuração

Crie `.env` somente na pasta privada:

```env
APP_NAME=SIGESP
APP_ENV=demo
APP_DEBUG=false
APP_URL=https://lucascorrea.pro/sigesp
APP_BASE_PATH=/sigesp
DEMO_MODE=true
APP_TIMEZONE=America/Manaus
APP_TRUST_PROXY_HEADERS=false
```

Não publique `.env`, credenciais, dumps SQL, logs ou diretórios privados. A demonstração funciona mesmo sem `.env`, mas o arquivo deixa URL e subdiretório explícitos.

## `.htaccess` público

O arquivo precisa manter listagem desabilitada, `DirectoryIndex index.php` e encaminhamento das rotas ao front controller:

```apache
Options -Indexes
DirectoryIndex index.php
RewriteEngine On
RewriteBase /sigesp/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

Preserve também as regras de bloqueio de arquivos ocultos, `.env`, logs, SQL e eventuais pastas privadas que já existem no arquivo do projeto. Nunca use `chmod 777`.

## Verificação após publicar

Confirme HTTP `200` e carregamento visual em:

```text
/sigesp/login
/sigesp/dashboard
/sigesp/atletas
/sigesp/atletas/novo
/sigesp/atletas/1
/sigesp/atletas/1/editar
/sigesp/atletas/1/documentos
/sigesp/atletas/1/carteira
/sigesp/documentos
/sigesp/equipes
/sigesp/eventos
/sigesp/relatorios
/sigesp/configuracoes
/sigesp/assets/css/app.css
/sigesp/assets/js/app.js
```

`/sigesp/` pode responder `302` apenas para `/sigesp/dashboard`; nunca deve retornar `403`.

Valide ainda:

- nenhum acesso interno redireciona para login;
- qualquer conteúdo no login abre o dashboard;
- o botão de demonstração funciona;
- não existe cabeçalho `Set-Cookie` nas páginas demo;
- formulários e uploads não fazem requisições de gravação;
- `.env`, `config`, `app`, `routes`, logs e SQL retornam `403` ou `404`;
- nenhum link perde `/sigesp` nem gera `/sigesp/sigesp`;
- o console do navegador permanece sem erros.

Se a raiz retornar `403`, confira se `index.php`, `.htaccess` e `assets/` estão diretamente em `public_html/sigesp`, além de `DirectoryIndex`, proprietário, permissões, `mod_rewrite`, `AllowOverride` e o log do Apache.
