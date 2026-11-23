# Checklist de homologação (Hostinger)

- [ ] PHP 8.2+, pdo_mysql, Argon2id, fileinfo, OpenSSL.
- [ ] `conexao.php`: conexão PDO integrada, 9 tabelas, suporte ativo.
- [ ] Login com `suporte` e a nova senha definida no instalador.
- [ ] Troca de senha e logout; usuário errado rejeitado.
- [ ] Cadastro, edição, busca e exclusão lógica de uma obra.
- [ ] Exclusão lógica não aparece na lista nem no mapa após recarregar.
- [ ] Formulários: OS, fiscalização, projeto, medição, material, prédio, protocolo.
- [ ] Entrada/saída de estoque (saldo negativo deve ser rejeitado).
- [ ] Encaminhamento de protocolo e histórico.
- [ ] Marcos do planejamento e listagem.
- [ ] Upload PDF/JPG e download autenticado.
- [ ] Usuário consulta não consegue executar gravações na API.
- [ ] Auditoria registra criação/edição/exclusão.
- [ ] URL `/obras/config/modules.json` não pode ser acessada pela web.
- [ ] Apagar a página temporária `public/conexao.php` após os testes.
