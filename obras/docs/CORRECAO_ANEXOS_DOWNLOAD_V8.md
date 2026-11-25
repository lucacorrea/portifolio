# Correção: anexo aparece no MySQL mas download falha

Na tela `download.php?id=1`, a versão anterior exibiu **Arquivo não disponível.**
A mensagem exata correspondia à captura genérica de uma exceção/erro interno no PHP (HTTP 500).
A hipótese de arquivo físico ausente precisa ser verificada no servidor: a versão anterior
exibia **Arquivo indisponível.** (HTTP 404) para esse caso.

## O que foi alterado

- Corrigida a expressão usada para higienizar o nome do arquivo (era inválida).
- Distingue: anexo ausente no MySQL, registro principal inativo, arquivo físico ausente,
  falta de permissão de leitura e falha interna.
- Retorna referência de diagnóstico no erro 500 e registra detalhes somente no log PHP.
- Continua protegendo downloads por login e verificando o caminho físico antes de acessar.
- Nenhuma tabela, anexo ou cadastro é apagado/alterado pelo patch.

## Diagnóstico do anexo 1

No phpMyAdmin, execute **`database/05_VERIFICAR_ANEXO.sql`**.

Se houver uma linha em `attachments`, mas o arquivo físico tiver sido perdido, copie
**o arquivo original do backup** para a pasta protegida correspondente, preservando
seu nome armazenado (`stored_name`). Não edite o `stored_name` arbitrariamente.

No terminal da hospedagem (se disponível):

```bash
cd public_html/obras
php tools/diagnosticar_anexo.php 1
```

O script mostra se o arquivo está em uma das pastas aceitas:

- `<domínio>/obras360-private/uploads/` — destino atual;
- `public_html/obras/storage/uploads/` — arquivos legados, que devem ser movidos com cuidado.

Os conteúdos de `obras360-private/uploads/` **não devem ser colocados no GitHub**.
Em deploy Git, o banco pode persistir enquanto os arquivos físicos são perdidos se a
hospedagem recriar/limpar a pasta durante a publicação.

**Atenção:** o patch não pode restaurar documentos inexistentes. Se você não tiver
backup, o servidor precisa receber novo upload do arquivo original após diagnóstico.
Faça backup do banco e dos anexos antes de qualquer alteração. Não compartilhe o
conteúdo do arquivo `conexao.php` com senha, nem caminhos de hospedagem sensíveis.
