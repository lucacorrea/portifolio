# Corrigir "Não foi possível entrar. Confira a instalação do banco"

Essa mensagem é exibida no `actions-login.php` quando ocorre uma exceção; não significa, por si só, senha errada. O hash da senha de suporte do pacote original foi conferido localmente contra a senha inicial do arquivo `INSTALACAO_CREDENCIAIS.txt` e corresponde.

## Se já está instalado: aplique só a correção

**Faça cópia de segurança.** Substitua apenas os arquivos `app/auth.php` e `public/actions-login.php` do servidor pelos presentes no pacote de correção. Não substitua o `config/.env` (contém sua senha atual) e não remova as tabelas nem dados existentes.

Ao tentar entrar, a tela exibirá um código, sem expor senhas ou SQL. Veja a explicação dos códigos abaixo. O log PHP registra o detalhe e a referência para você localizar a ocorrência.

| Código | Possível origem | O que verificar |
|---|---|---|
| DB-DRIVER | PHP sem driver MySQL | Ative a extensão `pdo_mysql` no servidor |
| DB-CONEXAO | MySQL indisponível/host incorreto | `DB_HOST`, serviço MySQL, rede, porta |
| DB-CREDENCIAIS | Usuário/senha de banco sem acesso | `DB_USER`, `DB_PASS`, permissões no painel |
| DB-NOME | Banco não existe no servidor | `DB_NAME` |
| DB-TABELA | Tabela não instalada | Importe `database/01_estrutura_e_suporte.sql` no banco selecionado |
| DB-COLUNA | Esquema diferente da versão do PHP | Compare o SQL atual com as tabelas |
| DB-CONFIGURACAO | Configuração incompleta | `config/.env` |
| DB-TRANSACAO | Contenção/timeout do banco | Logs do servidor e saúde do MySQL |
| DB-SQL ou LOGIN-SERVIDOR | Outro erro de SQL/PHP | Verifique o log pela referência gerada |

## Primeiro verifique a estrutura do banco (phpMyAdmin)

1. Acesse `hPanel > Bancos de dados > Gerenciamento > phpMyAdmin` e escolha `u784961086_obras`.
2. Execute `database/03_verificar_instalacao.sql` na aba SQL. Ele **não altera dados** e indica quais tabelas estão faltando.
3. Se faltarem tabelas, importe `database/01_estrutura_e_suporte.sql`, preferencialmente após fazer backup; ele utiliza `CREATE TABLE IF NOT EXISTS` e evita duplicar o suporte existente.
4. Confirme que a tabela `users` contém o usuário `suporte`, está ativa e tem perfil `suporte`, usando o SELECT comentado na parte final do SQL de diagnóstico.
5. Confirme `config/.env` no diretório **pai de `public_html`**. Na Hostinger, o host normal para um banco no mesmo servidor é `localhost`. Confirme o nome e usuário exatos no painel. Não envie a senha do banco nem o arquivo `.env` em mensagens ou capturas de tela.

## Diagnóstico detalhado (terminal da hospedagem)

Na pasta raiz privada do projeto (onde estão `app/`, `config/`, `database/` e `tools/`), execute:

```bash
php tools/diagnosticar_login.php
```

Ele verifica versão do PHP, extensão `pdo_mysql`, Argon2id, conexão, nove tabelas, usuário suporte e privilégios de gravação essenciais. O teste de gravação usa uma transação que é revertida: não adiciona registros definitivos. Não mostra senha, hash nem tokens.

**O script foi feito exclusivamente para execução no terminal, e recusa acesso por navegador.** Nunca mova `tools/` para `public_html`.

## Como interpretar os resultados

- **Sem conexão**: verifique host, nome, usuário, senha do banco e extensões. O host da Hostinger costuma ser `localhost`, mas confirme no seu painel.
- **Tabelas ausentes**: importe o SQL 01 no banco correto (não é necessário recriar o banco).
- **Conta suporte ausente**: o SQL 01 contém `INSERT` idempotente para o usuário de suporte; execute-o novamente após confirmar o banco.
- **Conta bloqueada**: aguarde o prazo do bloqueio e tente novamente. Evite repetidas tentativas.
- **Tudo OK e login continua com erro**: consulte `error_log` ou `.logs` na hospedagem, buscando a referência indicada no login, e compartilhe **apenas o código do erro e a linha do log com credenciais ocultas**.

Não altere hashes ou senhas diretamente no banco sem gerar um novo hash seguro. Se o usuário suporte já existia, o SQL não sobrescreve sua senha atual, por segurança.
