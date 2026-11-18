# OBRAS360 — atualização de interface v1.1

Esta revisão atualiza a interface sem alterar as tabelas SQL e sem remover o funcionamento dos módulos.

## O que mudou

- Fonte e espaçamentos mais compactos em menu, tabelas, formulários, indicadores e login.
- Mensagens de confirmação, sucesso e erro inspiradas no protótipo original.
- Alertas (toast) no canto inferior direito, com botão de fechar e suporte a leitores de tela. As mensagens de erro ficam visíveis até o usuário fechá-las.
- Confirmações de arquivamento e outras ações sensíveis substituídas por diálogo personalizado com opções Confirmar / Cancelar e tecla Escape.
- Olho para mostrar/ocultar senha no login, nas três senhas da área Meu perfil e na credencial temporária de novos usuários.
- Ajuste de quebra de linhas na notificação de conexão MySQL para telas pequenas.

## Publicação no GitHub / Hostinger

1. Substitua os arquivos da versão anterior pelos desta revisão no repositório.
2. Preserve a estrutura `obras/public/index.php` e o endereço `/obras/public/`.
3. Não envie senhas ao GitHub. `config/database.local.php` deve existir **somente** na Hostinger; é ignorado pelo `.gitignore`.
4. Certifique-se de ter mudado a senha MySQL exposta em versões anteriores do projeto e configure a nova senha no arquivo privado da Hostinger.
5. Após a sincronização, atualize a página com `Ctrl+F5` ou limpe o cache do navegador para obter CSS e JavaScript novos.

Não é necessário importar SQL novamente para estas alterações, desde que o banco já esteja instalado.

## Testes locais

- PHP: verificação de sintaxe dos arquivos `*.php`.
- JavaScript: verificação de sintaxe de `public/assets/js/app.js` e `maps.js`.
- Interface: verificação do botão de mostrar/ocultar senha, da apresentação e fechamento de mensagens, da confirmação personalizada e da largura móvel em Chromium via documento offline.
- Testes rápidos do projeto: hashes Argon2id e estrutura de exportação XLSX.

**Limite**: a conexão com o MySQL real e os fluxos autenticados de produção não foram validados neste ambiente. Os fluxos de interface foram testados sem acessar o servidor Hostinger.
