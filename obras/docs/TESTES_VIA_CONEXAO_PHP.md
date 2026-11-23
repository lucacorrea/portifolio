# FASE DE TESTES — todos os módulos via conexao.php (SEM .env)

## Como funciona

- **`app/conexao.php`** é o conector único que cria a conexão PDO.
- **`app/bootstrap.php`** expõe `db()` para todos os módulos, inclusive login,
  usuários, obras, mapas, relatórios, estoque, anexos e API; `db()` chama
  `obras_conectar()` de `app/conexao.php`.
- **`public/conexao.php`** é a tela de diagnóstico do navegador: testa manualmente
  a senha informada e também a conexão real usada por `db()`.
- **`config/conexao.exemplo.php`** é apenas o modelo de credenciais. Não contém senha.
- Não se usa `.env` nesta fase; ele será retomado numa migração futura.

## IMPORTANTE: um passo manual inevitável

O GitHub é público. Ele **não pode guardar a senha MySQL**. Então, uma única
vez na Hostinger:

1. No gerenciador de arquivos, localize `public_html/obras`.
2. **Fora de `public_html`**, ao lado dessa pasta, crie `obras360-private`.
3. Copie o conteúdo de `obras/config/conexao.exemplo.php` para o arquivo
   `obras360-private/conexao.php` (um arquivo PHP, não `.env`).
4. Preencha **somente no servidor** o campo `DB_PASS` com a senha MySQL atual.
   Preencha `APP_AUDIT_SALT` com 64 caracteres hexadecimais aleatórios
   (por exemplo com `openssl rand -hex 32` ou PowerShell seguro).
5. Salve; o usuário PHP do site deve ter permissão de leitura do arquivo.
6. Publique os arquivos versionados pelo GitHub como de costume.
7. Acesse `https://lucascorrea.pro/obras/public/conexao.php`, digite a senha
   MySQL somente no teste temporário e confirme que **duas conexões** funcionam:
   a manual e a do sistema via `app/conexao.php`.
8. Entre em `https://lucascorrea.pro/obras/public/acesso.php` com o suporte.
9. **Apague `public/conexao.php` do GitHub e da hospedagem após os testes**.

Exemplo de estrutura no servidor:

```text
lucascorrea.pro/
├── obras360-private/
│   └── conexao.php          <- senha somente aqui (NÃO no GitHub)
└── public_html/
    └── obras/
        ├── app/conexao.php <- conector central (sem credenciais)
        ├── config/conexao.exemplo.php
        └── public/
            ├── conexao.php  <- teste temporário, remova após uso
            └── acesso.php
```

## Para executar localmente no VS Code

Opcionalmente, copie `config/conexao.exemplo.php` para
`config/conexao.local.php` (arquivo ignorado pelo Git) e configure sua senha
somente nesse arquivo. Isso permite testes locais sem .env.

## Não confundir

`DB-CONFIGURACAO` indica que o `conexao.php` privado não foi encontrado ou está
incompleto. `DB-CONEXAO` indica falha de rede/host e `DB-CREDENCIAIS` indica
usuário/senha MySQL rejeitados. Se o teste manual passar mas o teste integrado
falhar, a senha/caminho do arquivo privado está diferente do teste manual.

Não basta apenas enviar o ZIP para o GitHub: o arquivo privado é propositalmente
excluído do repositório público. No futuro, vamos alterar SOMENTE a origem dos
segredos em `app/conexao.php` para `.env`, sem mexer em cada página.

**Segurança:** faça backup antes de mudanças; habilite HTTPS, use apenas o
intervalo de testes, não divulgue a senha em prints; gire a senha MySQL já
compartilhada nesta conversa. O arquivo de diagnóstico temporário não deve
continuar exposto após homologação.
