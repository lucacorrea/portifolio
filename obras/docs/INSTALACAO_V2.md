# OBRAS360 — DEMONSTRAÇÃO FUNCIONAL • PHP + MySQL

Sistema com o mesmo layout, mapas e formulários do protótipo anterior. Todos os módulos
usam **`app/bootstrap.php` → `db()` → `app/conexao.php`** para abrir PDO.

Nesta versão a configuração tem **somente 3 campos**: `DB_NAME`, `DB_USER`,
`DB_PASS`. **Não precisa `APP_AUDIT_SALT`, `DB_HOST`, `DB_PORT` nem `.env`.**
O PHP tenta `localhost:3306` e, se houver erro de transporte, tenta automaticamente
o servidor MySQL indicado anteriormente pela Hostinger.

## INSTALAR COM GITHUB PÚBLICO (RECOMENDADO)

1. Faça backup do banco existente.
2. Copie **apenas `obras/`** deste ZIP para o seu repositório `portifolio/obras/` no VS Code
   e envie à branch `main` (que a Hostinger publica).
3. A pasta **`SOMENTE_HOSTINGER_PRIVADO/` não deve entrar no repositório!** Ela tem
   sua senha MySQL. No Gerenciador de Arquivos Hostinger, faça uma única cópia
   de `SOMENTE_HOSTINGER_PRIVADO/conexao.php` para o diretório do domínio,
   **fora de `public_html`**, em `obras360-private/conexao.php`:

   ```text
   lucascorrea.pro/
   ├── obras360-private/
   │   └── conexao.php      (privado, contém a senha que você forneceu)
   └── public_html/
       └── obras/
           ├── app/conexao.php
           └── public/
               ├── acesso.php
               ├── instalar.php
               └── conexao.php   (diagnóstico temporário)
   ```

   O arquivo privado **não entra no GitHub**, porque o repositório é público.
   Para projetos locais no VS Code, o conector também aceita `obras/config/conexao.local.php`,
   que está no `.gitignore` (mas não é a localização preferida em produção).

4. No phpMyAdmin, selecione `u784961086_obras`, importe `01_CREATE_TABLE.sql` e
   `02_INSERT_INICIAIS.sql` somente caso as tabelas/dados iniciais não existam.
   Ambos estão em `obras/database`. O SQL inicial não apaga tabelas nem redefine a senha
   de um usuário existente.
5. Visite `https://lucascorrea.pro/obras/public/conexao.php` para validar:
   PHP, `pdo_mysql`, MySQL, as nove tabelas e a conta `suporte`.
6. Se o `suporte` estiver inativo ou você não souber sua senha, acesse
   `https://lucascorrea.pro/obras/public/instalar.php`: informe a senha do banco
   e **crie uma nova senha de suporte**. A tela não pede host nem sal.
   Se a conta já estiver ativa e a conexão funcionar, o instalador normal é bloqueado.
   Para **redefinir a senha do suporte** em ambiente de testes, abra
   `instalar.php?reconfigurar=1`, com a senha atual do MySQL e uma nova senha
   de suporte. Remova o instalador depois, por segurança.
7. Entre em `https://lucascorrea.pro/obras/public/acesso.php` com
   `suporte` ou `suporte@obras360.local` e a senha do suporte (não a senha MySQL).
8. Depois de testar, **remova `public/conexao.php` e `public/instalar.php`**
   do site e do GitHub, para não expor diagnósticos nem a instalação.

## Esquema

- `database/01_CREATE_TABLE.sql`: criação das 9 tabelas.
- `database/02_INSERT_INICIAIS.sql`: sequências iniciais e conta suporte inativa, sem senha fixa.
- `database/03_INSERT_DEMONSTRACAO_OPCIONAL.sql`: dados fictícios para demonstração.
- `database/04_VERIFICAR_TABELAS.sql`: conferências.

## Segurança e limites da demonstração

A senha do MySQL foi fornecida durante a conversa e está incluída **somente**
no arquivo `SOMENTE_HOSTINGER_PRIVADO/conexao.php` deste ZIP. Nunca publique
esse arquivo no GitHub nem deixe-o em `public_html`. Se você publicar o ZIP inteiro
em um repositório, estará expondo a senha. Recomenda-se trocar a senha do banco
após os testes; se trocar, atualize também esse arquivo privado na Hostinger.

A senha dos usuários permanece protegida por **Argon2id**; o sistema ainda
usa consultas parametrizadas, sessões, controle de acesso e CSRF. Removemos
somente as opções de configuração pedidas. Não é uma instalação homologada
para produção nem foi possível testar a conexão ao banco remoto desta hospedagem.


**Atualização (senha):** a senha do suporte exige no mínimo 8 caracteres, com letra maiúscula, minúscula, número e símbolo.
