> **Documento histórico da fase `.env`. Para o pacote atual, siga `TESTES_VIA_CONEXAO_PHP.md` (conexão PHP sem .env).**

# OBRAS360 — correção de deploy GitHub → Hostinger

Este ajuste vale para o repositório **lucacorrea/portifolio**, pasta `obras/`, publicado em
`https://lucascorrea.pro/obras/public/acesso.php`.

## Por que aparece `DB-CONFIGURACAO`?

O `app/bootstrap.php` precisa de `DB_NAME` e `DB_USER`. O arquivo `.env` não está no
GitHub porque foi corretamente ignorado pelo Git. Portanto, o deploy Git **não cria
as credenciais** no servidor. Não resolva isso adicionando a senha ao repositório.

## Configuração única na Hostinger (fora do deploy Git)

1. No Gerenciador de Arquivos da Hostinger, localize a pasta do domínio que contém
   `public_html/obras/`. Crie **fora de `public_html`** a pasta:
   `obras360-private/config/`.
2. Crie nela um arquivo chamado `.env`, **não** `.env.txt`. Use `obras/config/.env.example`
   como modelo, preenchendo `DB_PASS` com a senha atual do MySQL. Não copie esse
   arquivo preenchido ao VS Code/GitHub. Configure `APP_AUDIT_SALT` com um valor
   aleatório de 64 caracteres hexadecimais (`openssl rand -hex 32`).
3. O arquivo ficará em um caminho semelhante a:
   `domains/lucascorrea.pro/obras360-private/config/.env`.
   A posição é relativa ao diretório **pai** de `public_html`, não ao diretório `obras`.
4. Confirme `DB_HOST=srv1885.hstgr.io` e `DB_PORT=3306`, conforme o hPanel mostrado,
   além do nome e usuário `u784961086_obras`. Salve o arquivo.
5. No GitHub, depois de revisar e mesclar a correção, faça o deploy da branch `main`
   normalmente. O PHP encontra o `.env` privado, mesmo que o Git publique apenas
   `public_html/obras`.
6. Teste o login. Se surgir `DB-CONEXAO` ou `DB-CREDENCIAIS`, o `.env` já foi
   localizado; verifique a senha/permissões e a disponibilidade do MySQL.
   Se surgir `DB-TABELA`, execute o diagnóstico SQL presente em `database/`.

A aplicação também suporta a variável de ambiente `OBRAS360_ENV_FILE` apontando
para um caminho **absoluto**, ou as variáveis `DB_*` efetivamente disponibilizadas
ao processo PHP pelo servidor. Como último recurso de compatibilidade, ainda lê
`obras/config/.env`, mas é preferível manter o arquivo fora do `public_html`.

## Proteção do código privado

`obras/.htaccess` bloqueia URLs de tudo que não esteja em `obras/public/`. Cada
pasta interna recebe também um `.htaccess` de bloqueio. Isso depende de Apache
compatível com `.htaccess`; o ideal é configurar o document root diretamente
para `obras/public` quando a hospedagem permitir. Confirme que uma URL como
`https://lucascorrea.pro/obras/config/.env.example` retorna **403/404**, não o
conteúdo do arquivo. Não exponha arquivos de teste, backups, SQL ou credenciais.

## Diagnóstico e segurança

Na linha de comando da Hostinger, dentro de `public_html/obras`, execute:

```bash
php tools/diagnosticar_login.php
```

A execução é permitida somente via CLI e não revela senhas. O script testa
extensões PHP, configuração, conexão, tabelas e usuário suporte.

O **layout não foi alterado**. Esse ajuste corrige o carregamento de ambiente e
o isolamento dos arquivos. A conexão real com o MySQL precisa ser testada na
hospedagem; um PR não prova o sucesso do deploy.

**Troque a senha do banco e a senha inicial de suporte**, pois foram compartilhadas
na conversa. Jamais as coloque no GitHub, nem em prints, issues ou logs.
