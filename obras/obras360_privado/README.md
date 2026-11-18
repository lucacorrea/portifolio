# OBRAS360 · Código-fonte completo para Hostinger

O sistema vem do arquivo `obras(1).zip` enviado pelo usuário e contém as páginas PHP, regras de negócio, schema MySQL, scripts de instalação e recursos estáticos.

**Para instalar:** consulte o `INSTALAR_NA_HOSTINGER.md` na raiz do ZIP. A pasta `obras360_privado` deve ficar fora da pasta pública. O diretório público está em `public_html/obras360`. Não coloque código interno nem credenciais dentro de `public_html`.

## Componentes

- `app/Controllers`: rotas de autenticação, módulos, relatórios, mapas, usuários e instalador
- `app/Core`: autenticação, consultas PDO, validação, CSRF, auditoria e configurações
- `app/Services`: geração de CSV/XLSX/GeoJSON, movimentações, uploads e migrações
- `app/Views`: páginas HTML/PHP por módulo; layouts separados
- `config/modules.php`: esquema declarativo dos formulários e das tabelas
- `database/mysql.sql`: 14 tabelas com índices e relacionamentos
- `storage/`: anexos fora da pasta pública
- `bin/`: comando CLI de instalação, reset de administrador e diagnósticos
- `../public_html/obras360/assets`: CSS, JavaScript, ícones e mapas

## Requisitos

PHP 8.2+, `pdo_mysql`, `fileinfo`, `openssl`, `zlib`, suporte a Argon2id, MySQL 8+ ou MariaDB 10.6+, HTTPS. O mapa usa Leaflet e OpenStreetMap, portanto requer internet.

A instalação web `?r=instalar` exige chave aleatória longa e é bloqueada após o primeiro administrador. Ao concluir, desative o instalador no `.env` privado. O instalador CLI é `php bin/setup.php --email=...`.

Para desenvolver localmente com o mesmo arranjo, use `php -S 127.0.0.1:8000 -t public_html_html` a partir da raiz da entrega. Configure o `.env` privado com MySQL ou SQLite (se o PHP local tiver `pdo_sqlite`). A URL será `http://127.0.0.1:8000/obras360/`.

## Proteções

- Hash Argon2id com salt próprio para cada senha; confirmação via `password_verify`
- Expiração de sessão, cookies seguros sob HTTPS e invalidação após redefinição de senha
- Separação do diretório público, formulários CSRF e consultas preparadas
- Perfis `admin`, `gestor`, `tecnico`, `consulta`, logs de auditoria e arquivamento lógico
- Validação de arquivos enviados e download autenticado

**Validação:** consulte `docs/VALIDACAO_REALIZADA.md`. A conexão com a Hostinger precisa ser realizada no servidor de destino; esta entrega não instala nem altera sua conta remotamente.
