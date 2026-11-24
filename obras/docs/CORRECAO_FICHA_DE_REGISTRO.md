# Correção — ficha de registro reabre após atualizar a página

## Motivo

Links como `obras.php?registro=OBR-2026-009` carregavam a ficha automaticamente no JavaScript. Ao fechar o painel lateral, o código removia o HTML mas deixava `registro` no endereço. Assim, ao pressionar F5, a ficha era aberta novamente.

## Comportamento corrigido

- Ao **abrir** uma ficha diretamente na listagem, a URL passa a refletir o registro aberto, permitindo copiar o endereço da ficha.
- Ao **fechar** pelo X, pela área escurecida ou por **Esc**, o parâmetro `registro` é removido com `history.replaceState`, sem recarregar a página.
- Os demais parâmetros de URL (`aba`, por exemplo) e a âncora (`#...`) são preservados.
- Ao **atualizar** a página depois do fechamento, a ficha permanece fechada.
- Ao abrir um link direto com `?registro=...`, a ficha continua abrindo normalmente, até ser fechada.
- Links inválidos removem o parâmetro `registro`, evitando reaparecer após F5.
- O comportamento do login, banco de dados, formulários e menus não foi alterado.

## Publicar

Substitua a pasta `obras` no VS Code, envie para a sua branch de deploy do GitHub, aguarde a publicação na Hostinger e recarregue a página. Os arquivos JavaScript já usam versão de cache pelo horário de modificação (`filemtime`).

**Não é necessário alterar as tabelas SQL.**
