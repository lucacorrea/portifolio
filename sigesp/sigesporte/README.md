# SIGESP

Sistema municipal para gestão esportiva, em PHP 8.2, PDO/MySQL e MVC modular.

## Instalação

1. Instale PHP 8.2 com a extensão `pdo_mysql` e Composer.
2. Copie `.env.example` para `.env` e informe o banco.
3. Execute `composer install`, `php database/migrate.php` e `php database/seed.php`.
4. Inicie com `php -S localhost:8080 -t public` ou configure Apache/Nginx para apontar a `public/`.

No ambiente local, o seeder cria `admin@sigesporte.local` com a senha temporária `TroqueEstaSenha!2026`; defina `ADMIN_INITIAL_PASSWORD` em ambientes não locais e altere a senha no primeiro acesso. Nunca versione `.env` ou arquivos de `storage/app/private`.

Rotas ativas: `/login`, `/dashboard`, `/atletas`, `/atletas/novo`, `/atletas/{id}`. Os módulos restantes têm navegação e estados vazios, sem links quebrados. Os relatórios Excel/PDF dependem de `PhpSpreadsheet` e `Dompdf`, instalados pelo Composer.
