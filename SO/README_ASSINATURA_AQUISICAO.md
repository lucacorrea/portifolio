# Assinatura na visualização da aquisição

1. Execute `sql/assinatura_aquisicoes.sql` no banco.
2. A tela de assinatura fica em `assinatura_aquisicao_v2.php?id=ID_DA_AQUISICAO`.
3. O fluxo é exclusivo do perfil SUPORTE.
4. A assinatura usada é a ativa em `assinaturas_sistema` com finalidade `AUTORIZACAO_FORNECEDOR`.
5. A assinatura fica registrada por aquisição, com usuário e data/hora.

> Integração do botão na `aquisicoes_visualizar.php` deve apontar para `assinatura_aquisicao_v2.php?id=...`.
