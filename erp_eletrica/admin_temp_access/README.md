# Login Admin + Passkey + Usuário Temporário

Este pacote entrega o fluxo completo em PHP para:

1. **login do admin/master com e-mail + senha**
2. **convite para salvar o aparelho após o primeiro login válido**
3. **próximo login com passkey/WebAuthn**
4. **geração de usuário temporário com validade de 30 minutos**
5. **tela para validar o código temporário**

## Estrutura

```text
admin_temp_access/
├── acesso_temporario.php
├── admin_login.php
├── admin_logout.php
├── gerar_usuario_temporario.php
├── passkey_register.php
├── temp_logout.php
├── app/
│   └── bootstrap.php
├── api/
│   ├── login_password.php
│   ├── passkey_auth_begin.php
│   ├── passkey_auth_finish.php
│   ├── passkey_register_begin.php
│   ├── passkey_register_finish.php
│   ├── temp_user_generate.php
│   ├── temp_user_revoke.php
│   └── temp_user_validate.php
├── assets/
│   └── js/
│       └── passkey.js
└── sql/
    └── schema.sql
```

## Onde copiar no projeto

Copie os arquivos para a pasta do seu ERP, por exemplo:

```text
public_html/erp_eletrica/
```

A estrutura final fica assim:

```text
erp_eletrica/
├── conexao.php
├── admin_login.php
├── passkey_register.php
├── gerar_usuario_temporario.php
├── acesso_temporario.php
├── admin_logout.php
├── temp_logout.php
├── app/
├── api/
├── assets/
└── vendor/
```

## Banco

1. Use a sua tabela `usuarios` já existente.
2. Rode o arquivo `sql/schema.sql` para criar as tabelas extras.

## Dependência obrigatória para biometria/passkey

Na pasta do projeto rode:

```bash
composer require lbuchs/webauthn
```

## Conexão

O arquivo `app/bootstrap.php` espera que exista um `conexao.php` na raiz do módulo carregando um `PDO` em `$pdo`.

Exemplo:

```php
<?php
$pdo = new PDO(
    'mysql:host=localhost;dbname=SEU_BANCO;charset=utf8mb4',
    'USUARIO',
    'SENHA',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);
```

## Fluxo

### 1) Admin entra pela senha

Arquivo:

- `admin_login.php`

Regra:

- só deixa entrar `nivel = admin` ou `nivel = master`
- se a senha estiver em texto puro e bater, o sistema já converte para `password_hash()` automaticamente

### 2) Sistema oferece salvar o aparelho

Depois do login correto:

- se o admin ainda não tem passkey salva, o sistema oferece cadastrar o dispositivo
- o cadastro acontece em `passkey_register.php`

### 3) Próximo login com biometria/passkey

Na tela `admin_login.php` existe o botão:

- **Entrar com biometria**

Esse botão faz o fluxo WebAuthn e, quando válido, abre direto a página de geração.

### 4) Geração do usuário temporário

Arquivo:

- `gerar_usuario_temporario.php`

O admin escolhe:

- nome temporário
- nível temporário
- observação

O sistema gera:

- código como `TMP-AB12CD`
- validade de **30 minutos**

### 5) Uso do código temporário

Arquivo:

- `acesso_temporario.php`

O usuário temporário digita o código e o sistema cria `$_SESSION['temp_auth']` com:

- nome temporário
- nível temporário
- validade

## Regra de níveis

- `master` pode gerar até `master`
- `admin` pode gerar até `admin`

## Observação importante

Para a biometria funcionar direito:

- o site precisa estar em **HTTPS**
- o navegador/aparelho precisa suportar passkey/WebAuthn
- a autenticação real é decidida pelo aparelho: digital, rosto, PIN ou outro método local

## Arquivos principais

### Login admin

- `admin_login.php`

### Cadastro do aparelho

- `passkey_register.php`

### Geração do temporário

- `gerar_usuario_temporario.php`

### Entrada temporária

- `acesso_temporario.php`

