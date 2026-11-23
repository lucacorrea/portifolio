# OBRAS360 — PHP + MySQL · Reestruturação de instalação e autenticação

Este projeto **mantém o layout original** (HTML, CSS e JavaScript) e os módulos
preexistentes. A conexão, o instalador, o fluxo de login e os diagnósticos foram
reorganizados para a fase de testes em hospedagem Hostinger.

**Requisitos:** PHP 8.2+, PDO / `pdo_mysql`, suporte a Argon2id, `fileinfo`,
`openssl`, HTTPS, MySQL/MariaDB com `CHECK(JSON_VALID(...))` e InnoDB.

## Instalação rápida (VS Code → GitHub → Hostinger)

1. Antes de qualquer atualização, faça **backup completo do banco e dos arquivos**.
2. Extraia a pasta `obras/` deste ZIP sobre a pasta `obras/` do repositório.
   Não envie arquivos de configuração com senhas para um GitHub público.
3. Faça commit/push no VS Code e confirme que a Hostinger publicou `obras/`.
   O login continua em `https://lucascorrea.pro/obras/public/acesso.php`.
4. No phpMyAdmin, abra o banco `u784961086_obras` e execute os SQLs **nesta ordem**:
   - `database/01_CREATE_TABLE.sql` — cria 9 tabelas com `CREATE TABLE IF NOT EXISTS`.
   - `database/02_INSERT_INICIAIS.sql` — insere sequências e conta suporte INATIVA.
   - Opcional: `database/03_INSERT_DEMONSTRACAO_OPCIONAL.sql` — dados fictícios.
   - `database/04_VERIFICAR_TABELAS.sql` — somente consultas para conferência.
5. Abra **`https://lucascorrea.pro/obras/public/instalar.php`** pelo HTTPS.
   Informe a senha **atual** do MySQL, escolha uma **senha nova e forte para o
   suporte** e clique para configurar. O sistema gera o salt automaticamente,
   valida as 9 tabelas, testa o MySQL e grava a conexão privada no servidor.
6. Acesse **`https://lucascorrea.pro/obras/public/conexao.php`** e confirme que
   a conexão real, as tabelas e o suporte estão OK.
7. Entre no login com **`suporte`** ou **`suporte@obras360.local`** e a senha que
   você definiu **no instalador**, não com a senha antiga compartilhada.

**Não é necessário montar `.env` nesta fase.** Toda operação que usa MySQL
chama `db()` em `app/bootstrap.php`, que usa `app/conexao.php`. O instalador
salva as credenciais no PHP privado `obras360-private/conexao.php` fora de
`public_html`. Quando a hospedagem não permite gravação no diretório pai,
a alternativa é `obras/config/conexao.local.php`, que é ignorado pelo Git e
protegido por `.htaccess`. A página de instalação se desabilita
automaticamente depois de configurar uma conexão funcional.

Se já existe um arquivo privado incorreto, o instalador pode repará-lo **somente
depois de validar novamente a senha MySQL fornecida**. Ele também redefine o
usuário `suporte` com a senha nova informada. Não apaga as demais contas.

## Pastas

```text
obras/
├── app/
│   ├── bootstrap.php                 (sessão, autenticação básica, utilitários)
│   ├── conexao.php                   (conector único PDO)
│   ├── auth.php                      (Argon2id, login, troca de senha)
│   └── data.php                      (registros, validações, anexos)
├── config/
│   ├── modules.json                  (metadados dos formulários)
│   └── conexao.exemplo.php           (modelo SEM senhas)
├── database/
│   ├── 01_CREATE_TABLE.sql
│   ├── 02_INSERT_INICIAIS.sql
│   ├── 03_INSERT_DEMONSTRACAO_OPCIONAL.sql
│   └── 04_VERIFICAR_TABELAS.sql
├── public/
│   ├── instalar.php                  (assistente seguro de primeira instalação)
│   ├── conexao.php                  (diagnóstico SOMENTE-LEITURA)
│   ├── acesso.php, actions-login.php, index.php, api.php
│   ├── ... todas as demais páginas e formulários
│   └── assets/                       (layout original: CSS, JS)
├── storage/uploads/                  (compatibilidade de anexos antigos)
├── tools/diagnosticar_login.php      (diagnóstico somente via terminal)
└── docs/INSTALACAO_V2.md
```

No servidor, fora da pasta publicada, o instalador prefere criar:

```text
<diretório do domínio>/
├── obras360-private/
│   ├── conexao.php                   (contém senha, FORA do Git)
│   └── uploads/                      (anexos novos, se possível)
└── public_html/
    └── obras/
        ├── app/, config/, database/, docs/, storage/, tools/
        └── public/
            ├── acesso.php
            ├── instalar.php
            └── conexao.php
```

## O que é preservado e o que foi corrigido

- Visual original, mapas Leaflet, telas e formulários.
- CRUD para obras, ordens de serviço, fiscalização, documentos/projetos,
  medições, materiais/estoque, prédios públicos e protocolos.
- Login por usuário/e-mail, Argon2id e troca de senha, sessões, CSRF,
  permissões, auditoria e limitação de tentativas.
- A conexão do banco **não depende do salt de auditoria**, não usa `.env` e
  apresenta diagnóstico quando o arquivo PHP privado não foi configurado.
- Consultas não exibem registros com exclusão lógica (`deleted_at`).
- Anexos novos preferem área privada fora de `public_html`, sem perder o acesso
  aos anexos existentes na pasta antiga.
- Alterações de permissões protegem o último usuário de suporte ativo.

## Erros técnicos

- `DB-CONFIGURACAO`: falta arquivo de configuração privado ou dados incompletos.
  Use o instalador (e não recadastre tabelas desnecessariamente).
- `DB-DRIVER`: extensão `pdo_mysql` desabilitada no PHP da Hostinger.
- `DB-CONEXAO`: endereço/porta do servidor MySQL indisponível.
- `DB-CREDENCIAIS`: senha ou usuário do banco rejeitado pelo MySQL.
- `DB-TABELA`: as tabelas não foram instaladas corretamente.
- `DB-SQL`: erro no banco que deve ser conferido nos logs da hospedagem.

**Atenção:** código e SQL foram analisados localmente, mas a conexão real com
MySQL na Hostinger e o deploy precisam ser testados na hospedagem. Use o
instalador e o diagnóstico antes de operar dados reais.

Depois de homologar, **retire o arquivo público `public/conexao.php`** do
servidor e do repositório para reduzir a exposição de informações técnicas.
A página `instalar.php` já se desabilita após uma instalação funcional;
também pode ser retirada depois da homologação.

## Segurança do GitHub

- Nunca versionar `conexao.local.php`, `obras360-private/conexao.php`, senhas
  MySQL, `APP_AUDIT_SALT` real nem arquivos de backup.
- O repositório é público. As credenciais anteriormente divulgadas devem ser
  trocadas após configurar a nova conexão.
- O bloqueio `.htaccess` depende de o servidor respeitar regras Apache;
  confira que `https://lucascorrea.pro/obras/config/modules.json` devolve 403/404.
- Faça cópias de segurança e teste permissões antes de cadastrar dados oficiais.
