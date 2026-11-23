# Instalação e migração do OBRAS360

## Ordem: banco → instalador → teste → login

1. Backup do MySQL e da pasta `obras/`.
2. Importar `database/01_CREATE_TABLE.sql` no phpMyAdmin do banco `u784961086_obras`.
3. Importar `database/02_INSERT_INICIAIS.sql` para preparar sequências e suporte.
4. Para apresentação, opcionalmente importar `database/03_INSERT_DEMONSTRACAO_OPCIONAL.sql`.
5. Publicar toda a pasta `obras/` pelo fluxo VS Code → GitHub → Hostinger.
6. Abrir HTTPS `/obras/public/instalar.php` e informar a senha MySQL **somente nesse formulário**.
   Escolher uma senha forte nova para `suporte` (mínimo 16 caracteres, maiúscula,
   minúscula, número, símbolo). O PHP testa a conexão, verifica as 9 tabelas,
   redefine ou cria o suporte e salva a conexão sem versionar segredos.
7. Abrir `/obras/public/conexao.php` para validar os resultados.
8. Login como `suporte` ou `suporte@obras360.local` com a senha definida no passo 6.

### Se aparecer DB-CONFIGURACAO

Acesse `instalar.php`. Se há um PHP privado com conteúdo antigo/errado, o
instalador autoriza reparo **apenas mediante conexão autenticada ao MySQL**.
Se o instalador não conseguir salvar o arquivo por permissões de pasta, consulte
o log da hospedagem e crie manualmente `obras360-private/conexao.php` usando
`config/conexao.exemplo.php`, sempre FORA de `public_html`.

### Suporte que já existia no banco

O script SQL `02_INSERT_INICIAIS.sql` não troca nem desativa usuários existentes.
O instalador redefine a senha do suporte somente após o MySQL validar as
credenciais informadas. Não é necessário apagar tabelas nem usuários.

### Desenvolvimento local

Para fazer conexão local sem instalador, crie manualmente `obras/config/conexao.local.php`
com o conteúdo do modelo `config/conexao.exemplo.php`. Esse arquivo está em
`.gitignore` e deve permanecer sem commit.

### Depois de concluir

Retire o diagnóstico público `conexao.php` e, se preferir, `instalar.php`.
Nunca envie credenciais nem prints do PHP privado para o GitHub público.
