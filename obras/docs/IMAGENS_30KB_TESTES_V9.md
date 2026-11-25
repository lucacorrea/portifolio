# OBRAS360 V9 — Anexos de imagem até 30 KB

## Comportamento

- Ao **cadastrar ou editar** um registro com anexo JPG, JPEG, PNG ou WebP, o PHP valida a imagem e tenta mantê-la **em até 30 KiB (30 × 1024 bytes)**.
- Se o arquivo original já couber em 30 KiB, ele é mantido **sem alterar nenhum byte**.
- Se exceder, o servidor tenta convertê-lo em WebP com qualidade gradualmente reduzida e, quando necessário, com dimensões menores. A extensão do arquivo baixado passa a `.webp`.
- **É impossível garantir 30 KiB sem perda de qualidade em qualquer foto.** Se o arquivo não couber nos limites definidos, a operação é cancelada com erro claro; nenhum novo anexo é persistido, e o registro anterior fica intacto.
- PDF, DOC e DOCX **não são convertidos nem limitados a 30 KB**, porque isso poderia destruir conteúdo e não pode ser feito de maneira universal. Mantêm o limite anterior de 12 MB.
- **A configuração do servidor precisa de PHP GD com suporte a WebP** para imagens maiores que 30 KiB; imagens já pequenas são aceitas sem essa extensão.
- Imagens antigas não são modificadas automaticamente, para não perder documentos nem adulterar arquivos já registrados.
- Os anexos permanecem em `obras360-private/uploads` e podem ser baixados pela rota autenticada `public/download.php`.
- A API mantém a proteção de CSRF, autenticação, permissões e transações; não houve alteração nas tabelas.

## Testes na Hostinger

1. Faça backup do banco e da pasta privada de anexos.
2. Verifique PHP 8.2+ e extensão GD/WebP no painel de configurações PHP.
3. Cadastre uma obra com foto de 2 MB; confirme que a ficha exibe o anexo convertido para `.webp` e que `size_bytes <= 30720` no banco.
4. Baixe o arquivo e confirme formato, visual e tamanho, sem supor que seja idêntico ao original.
5. Edite a obra e anexe outra foto; confirme o mesmo comportamento.
6. Teste imagem pequena (`<= 30 KiB`): deve manter a extensão original.
7. Teste arquivo PDF acima de 30 KiB: o sistema deve manter o PDF original.
8. Teste foto complexa que não caiba: a API deve explicar o motivo e preservar o registro anterior.

SQL opcional para conferir os novos anexos:

```sql
SELECT id, original_name, stored_name, mime, size_bytes, uploaded_at
FROM attachments WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 20;
```
