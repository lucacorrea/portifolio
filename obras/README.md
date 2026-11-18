# OBRAS360 — Prefeitura Municipal de Coari / AM

Sistema PHP + MySQL para gestão municipal de obras, planejamento, fiscalização,
ordens de serviço, patrimônio, documentos, materiais, protocolos e relatórios.

## Acesso e estrutura: do GitHub diretamente para /obras/

**Endereço esperado:** https://lucascorrea.pro/obras/public/

Não renomeie `public` nem use `obras360_privado` / `public_html` dentro do repositório.
Estrutura:

```text
obras/
  app/                     Controladores, serviços, autenticação e views
  config/
    app.php                Preferências sem segredos (versionado)
    database.php           Host/porta/nome/usuário sem segredo (versionado)
    database.local.php     SENHA PRIVADA: criar só no servidor; NÃO versionar
    database.local.example.php  Modelo sem senha
  database/
    mysql.sql              DDL de todas as tabelas
    02_primeiro_administrador.sql  SQL de INSERT de administrador Argon2id
  storage/                 Uploads e logs (não públicos)
  public/
    index.php              Entrada única, login na primeira visita
    assets/                CSS, JS, ícones
  .htaccess                Protege pastas internas
```

### 1. Banco MySQL no hPanel

Confirme que o banco **u784961086_obras** e o usuário **u784961086_obras**
foram realmente criados, com acesso. A captura enviada mostrava o *formulário de
criação*, não uma confirmação.

**ALTERE A SENHA DO MYSQL** que apareceu na captura antes de publicar o sistema.

No phpMyAdmin abra o banco e importe, nesta ordem:

1. `database/mysql.sql` — cria as tabelas sem apagar registros existentes.
2. `database/02_primeiro_administrador.sql` — `INSERT ... SELECT` do primeiro
   administrador (hash Argon2id, sem senha em texto puro no SQL).

Se o administrador `admin@lucascorrea.pro` **já existir** no banco e você
precisar recuperar o acesso, o arquivo `03_OPCIONAL_redefinir_senha_admin.sql`
pode ser executado manualmente no phpMyAdmin, somente com autorização.
Ele redefine a senha para a temporária entregue separadamente.

Não importe scripts de teste ou demonstração em dados oficiais.

### 2. Sincronização GitHub → Hostinger

O **destino de deploy** na Hostinger precisa ser `/obras`, ou o endereço acima não
corresponderá à aplicação. Se o hPanel especificar o caminho relativo à pasta
raiz do site, normalmente ele será `obras` (o diretório público físico costuma
ser `public_html/obras`, mas não faz parte da estrutura do repositório).
Verifique que `app`, `config` e `public` sejam pastas irmãs em `/obras/`.

**No GitHub:** envie os arquivos que estão *dentro* da pasta `obras/` do ZIP
para a **raiz do repositório**. Não crie `obras/obras/public/` por engano.

### 3. Configuração de conexão SEM .env e SEM publicar senhas no GitHub

Na Hostinger, pelo **Gerenciador de Arquivos**, na pasta `obras/config/`, crie
**`database.local.php`** com o conteúdo abaixo, substituindo a senha por sua
senha MySQL nova (após ter trocado no hPanel):

```php
<?php
return [
    'DB_PASSWORD' => 'SUA_NOVA_SENHA_DO_BANCO',
];
```

Os outros campos já constam em `config/database.php`:

- Host: `localhost` (mesma hospedagem)
- Porta: `3306`
- Nome: `u784961086_obras`
- Usuário: `u784961086_obras`

`database.local.php` está no `.gitignore` e protegido por `.htaccess`.
**Não crie esse arquivo dentro do GitHub.** Se o tipo de deploy utilizado
apagar arquivos não versionados, preserve a configuração fora da área apagada
ou use variáveis de ambiente do servidor; confira após cada deploy.

### 4. Login

Abra **https://lucascorrea.pro/obras/public/**. A tela inicial é o LOGIN.
E-mail: `admin@lucascorrea.pro`; senha temporária exclusiva enviada em um
**arquivo de credenciais separado, fora do projeto e do GitHub**.
O primeiro acesso exige troca de senha (mínimo 15 caracteres).
A tela de login também aparece sem configuração de banco, com aviso claro.

### Segurança

- Password hashing Argon2id com salt individual aleatório; não é criptografia reversível.
- PDO prepared statements, CSRF, sessões com cookies HttpOnly e SameSite.
- Limites de login e bloqueio temporário, trilha de auditoria.
- Arquivos internos bloqueados por `.htaccess`, uploads fora de `public/`.
- HTTPS é necessário em produção. Teste controles de acesso, permissões e backups
  antes de usar dados oficiais.

### Problemas comuns

- **"configuracao .env ausente"**: significa que a versão antiga foi publicada;
  atualize o repositório e confira `app/bootstrap.php`.
- **"Configuração inicial pendente"**: crie `config/database.local.php` somente
  na hospedagem, não no GitHub.
- **Erro 1045 / acesso negado**: usuário, banco ou senha do hPanel divergentes.
- **Banco indisponível / SQLSTATE 42S02**: importe os dois arquivos SQL.
- **403 ou 404**: verifique se o deploy apontou a `/obras` e se o diretório
  `public/index.php` está lá; a rota não deve ser `/obras360/`.
- **Tela sem estilo**: confira `public/assets/css/app.css` e URLs iniciadas em
  `/obras/public/assets/`.
- **Credencial inválida após importar SQL**: verifique se o administrador já
  existia; `02_primeiro_administrador.sql` não altera contas existentes.
- **pdo_mysql não instalado**: habilite extensão MySQL/PDO no PHP da hospedagem.

### Teste local

PHP >= 8.2 com extensões `pdo_mysql`, `fileinfo`, `zlib` e Argon2id.
Na pasta raiz do projeto: `php -S 127.0.0.1:8080 -t .` e abra
`http://127.0.0.1:8080/public/`. Para testar com o mesmo prefixo /obras,
renomeie ou copie o projeto para uma pasta `obras` no servidor local.
**Aviso:** o servidor embutido PHP não aplica regras `.htaccess`; não use
`-t .` em ambiente público. Em produção use Apache/LiteSpeed com as proteções
configuradas, ou configure o document root para `public/`.

### Validação

Ver `docs/VERIFICACOES.md` para testes realizados e limitações. Não houve
conexão com a conta Hostinger do usuário: a ligação real é estabelecida
somente após criação do banco e configuração da senha na hospedagem.


## Versão 1.1 — ajustes visuais

- Tipografia compacta em todo o sistema (menus, tabelas, formulários e login).
- Notificações de sucesso, erro e aviso no estilo do protótipo: aparecem no canto inferior direito, têm botão para fechar e respeitam o fechamento automático (erros importantes só fecham ao clicar).
- Confirmações de operações destrutivas apresentadas em um modal estilizado, em vez da janela padrão do navegador.
- Botão de olho nas senhas do login, na alteração de senha do perfil e na senha temporária de novos usuários.
- Senhas do banco **não** são distribuídas no projeto. Configure `config/database.local.php` diretamente na Hostinger com a senha MySQL nova, sem enviar esse arquivo ao GitHub.
- **Segurança:** versões anteriores do projeto continham uma credencial MySQL em código. Redefina imediatamente essa senha no painel Hostinger, remova-a do histórico público do Git, e configure a nova senha apenas no servidor.

### Atualização pelo GitHub

Publique os arquivos da pasta `obras` na raiz do seu repositório. Continue acessando `https://lucascorrea.pro/obras/public/`. Depois da sincronização, limpe o cache do navegador com `Ctrl+F5`. Não é necessário reinstalar as tabelas MySQL para aplicar apenas estas alterações visuais.
