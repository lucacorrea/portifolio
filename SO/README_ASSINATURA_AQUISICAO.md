# Assinatura nas aquisições — Sistema SO

1. Execute **uma única vez** `SO/sql/assinatura_aquisicoes.sql` no banco de dados da hospedagem, antes de utilizar a assinatura. A tabela não constava no backup de 13/10/2026. O arquivo utiliza tipo compatível com `aquisicoes.id`.
2. A assinatura é feita diretamente na `aquisicoes_visualizar.php?id=...`: basta clicar no botão **Assinar aquisição**. O formulário envia um POST protegido por CSRF, registra a assinatura e retorna à mesma aquisição, sem tela intermediária ou pedido de confirmação. O menu da lista abre essa visualização.
3. **Somente os logins exatos `suporte` e `amandha`** podem confirmar assinatura. A verificação usa o login de `usuarios` correspondente ao `user_id` da sessão, inclusive no POST; o perfil ADMIN, sozinho, não dá acesso.
4. A imagem aplicada é a assinatura de autorização ativa cadastrada em `assinaturas_sistema`, com finalidade `AUTORIZACAO_FORNECEDOR`. **A imagem é compartilhada**; o nome e a data de quem efetivou cada operação são gravados separadamente em `assinaturas_aquisicoes`.
5. A assinatura é registrada uma vez por aquisição (chave única). O usuário responsável e o horário aparecem na visualização; a imagem assinada sai nas duas vias e na impressão em lote.
6. Apenas o suporte continua podendo cadastrar, trocar e ativar arquivos de assinatura em `configuracoes_assinatura.php`.
7. `assinatura_aquisicao.php` direciona para o mesmo código seguro da `assinatura_aquisicao_v2.php`, impedindo contorno por rota antiga.
8. A imagem da assinatura registrada pode ser consultada por qualquer usuário autenticado com acesso à visualização da aquisição em `assinatura_suporte.php?aquisicao=ID`, mas a imagem ativa sem aquisição só é exibida aos dois logins autorizados.
9. A impressão em lote mantém seleção de assinatura não registrada para os dois logins; para registro auditável, utilize o botão **Assinar aquisição**.

**Importante:** este fluxo reutiliza a imagem central de autorização, não captura uma assinatura pessoal independente de cada usuário. Confirme que seu uso é autorizado pela pessoa titular da assinatura cadastrada.
