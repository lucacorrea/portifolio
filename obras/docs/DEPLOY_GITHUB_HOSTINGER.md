# OBRAS360 — deploy GitHub → Hostinger

Este guia corresponde à pasta `obras/` do repositório `lucacorrea/portifolio` e ao endereço `https://lucascorrea.pro/obras/public/acesso.php`.

## Erro DB-CONFIGURACAO

O arquivo `obras/config/.env` foi ignorado corretamente pelo Git. Portanto, o deploy automático não entrega as credenciais de banco ao PHP. Não publique senhas no GitHub.

## Configuração única, fora de public_html

1. No Gerenciador de Arquivos da Hostinger, encontre a pasta do domínio onde está `public_html/obras/`.
2. **Fora de `public_html`**, crie `obras360-private/config/`.
3. Dentro dela, crie o arquivo `.env` usando `obras/config/.env.example` como modelo. Preencha `DB_PASS` com a senha atual do MySQL **somente no servidor**. Não envie o arquivo preenchido ao GitHub, VS Code, screenshots ou issues.
4. Confirme `DB_HOST=srv1885.hstgr.io`, `DB_PORT=3306`, `DB_NAME=u784961086_obras`, `DB_USER=u784961086_obras`, conforme seu hPanel.
5. Preencha `APP_AUDIT_SALT` com 64 caracteres hexadecimais aleatórios (gerados, por exemplo, com `openssl rand -hex 32`).
6. Faça o deploy normal a partir da `main`, depois de revisar e mesclar a alteração. O sistema buscará o arquivo neste caminho semelhante a `domains/lucascorrea.pro/obras360-private/config/.env` sem precisar versioná-lo.

O PHP também aceita um arquivo indicado por `OBRAS360_ENV_FILE` (caminho absoluto) ou variáveis `DB_*` efetivamente disponibilizadas ao processo PHP. Para compatibilidade, ainda lê `obras/config/.env`, mas é preferível manter o segredo fora da área pública.

## Segurança

O `obras/.htaccess` bloqueia solicitações web para tudo fora de `obras/public/`, e as pastas internas também são bloqueadas individualmente. Confirme que `https://lucascorrea.pro/obras/config/.env.example` responde com **403** ou **404**. A proteção depende do servidor respeitar os arquivos `.htaccess`; sempre que possível configure o document root em `obras/public`.

## Diagnóstico

Caso haja terminal na Hostinger, execute de dentro de `public_html/obras`:

```bash
php tools/diagnosticar_login.php
```

Se após configurar o arquivo surgir `DB-CONEXAO` ou `DB-CREDENCIAIS`, a configuração foi localizada; verifique a conexão e a senha. Se surgir `DB-TABELA`, verifique os SQLs de `database/`. O layout não foi modificado. A conexão real com o MySQL só pode ser testada na hospedagem.

**Importante:** substitua a senha do banco e a senha inicial do suporte, pois ambas já foram compartilhadas na conversa. Não comite credenciais.
