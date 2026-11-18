# Instalação no Windows com XAMPP

Este sistema não é um conjunto de HTML estático. Os arquivos do servidor, os dados e os anexos permanecem fora de `public/`.

## Teste local rápido

1. Instale PHP 8.2+ com suporte a Argon2id. Verifique extensões `pdo_sqlite` e `fileinfo` no `php.ini` que corresponde ao PHP utilizado no terminal.
2. Extraia a pasta do projeto, abra o terminal dentro dela e execute:

```bat
copy .env.example .env
C:\xampp\php\php.exe bin\setup.php --demo --email=administrador@exemplo.gov.br
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public
```

3. Entre em `http://127.0.0.1:8000` com o e-mail do comando e a senha temporária exibida. Troque-a no primeiro acesso.

**Não compartilhe prints ou o histórico do terminal contendo essa senha.** O instalador não imprime credenciais novamente. Se precisar recuperar a conta, execute `bin/reset-admin.php` conforme as instruções do código.

## Implantação com MySQL

Em um servidor PHP com `pdo_mysql`, crie o banco `obras360` e um usuário próprio de acesso restrito. Edite o `.env`:

```ini
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=obras360
DB_USER=obras360_app
DB_PASSWORD="COLOQUE_UMA_SENHA_FORTE_AQUI"
APP_ENV=production
```

Execute:

```bat
C:\xampp\php\php.exe bin\setup.php --email=seu.email.institucional@exemplo.gov.br
```

Para o Apache em produção, **configure DocumentRoot para a pasta `public/`** e não use a pasta inteira do projeto como raiz do site. Os arquivos `.env`, `database/`, `bin/`, `app/` e `storage/` não devem ser acessíveis por HTTP. Ative HTTPS e proteja backups; realize as verificações em `docs/SEGURANCA.md`.

## Erros comuns

- `could not find driver`: faltou habilitar `pdo_sqlite` ou `pdo_mysql` na instalação **em uso** do PHP.
- `Argon2id`: o PHP foi compilado sem esse algoritmo; troque por uma distribuição com suporte, não desative a segurança da aplicação.
- Sem mapa: verifique internet/CSP e acesso ao provedor de tiles. Os dados continuam visíveis nas fichas.
- Não abre ao clicar em `index.php`: PHP precisa de servidor web; aponte `public/` como raiz.
