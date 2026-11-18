# Uso em Windows / XAMPP para desenvolvimento

1. Copie `obras360_privado` para fora de `htdocs` (idealmente uma pasta irmã de `public_html`), preservando a estrutura da entrega.
2. Instale PHP 8.2+ com `pdo_mysql` (ou `pdo_sqlite`) e suporte a Argon2id.
3. Copie `.env.example` para `.env`, altere `APP_ENV=local`, `INSTALL_ENABLED=false`, e configure seu banco local.
4. Abra um terminal na raiz do ZIP extraído (onde estão `obras360_privado` e `public_html`).
5. Execute `php obras360_privado/bin/setup.php --email=admin@exemplo.local` para gerar a senha inicial no terminal.
6. Execute `php -S 127.0.0.1:8000 -t public_html_html` e abra `http://127.0.0.1:8000/obras360/`.

Não coloque o código privado em uma pasta servida pelo Apache. Se usar XAMPP com Apache, configure um VirtualHost cuja raiz pública aponte para `public_html` e mantenha a pasta privada como irmã.
