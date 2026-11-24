# OBRAS360 — roteiro de homologação para a equipe de Obras e Planejamento

## Preparação

- [ ] Foi feito backup do banco de dados e dos anexos privados.
- [ ] Foi configurado um ambiente ou conjunto de registros fictícios de teste.
- [ ] O suporte verificou login e troca de senha, e criou contas `gestor`, `operador` e `consulta`.
- [ ] Usuários e senhas não estão embutidos no repositório GitHub.

## Execução assistida — registrar resultado, usuário e data

- [ ] **Login**: conta ativa entra, conta inativa não entra, senha errada é recusada, logout funciona.
- [ ] **Usuários**: suporte cria usuário; administrador cria operador; administrador não cria suporte.
- [ ] **Obras**: criar, editar, consultar, filtrar, anexar arquivo, remover logicamente e conferir no mapa.
- [ ] **Ordens**: criar com prioridade, mudar situação, consultar ficha e imprimir.
- [ ] **Planejamento**: cadastrar, editar e excluir marco; salvar sem alteração não gera erro.
- [ ] **Fiscalização**: cadastrar vistoria, anexar JPG/PDF e fazer download autenticado.
- [ ] **Projetos**: cadastrar documento e validar tipos proibidos de anexo.
- [ ] **Medições**: cadastrar valor, editar e conferir total no relatório.
- [ ] **Estoque**: entrada de 10, saída de 4, saldo 6; saída maior que saldo rejeitada.
- [ ] **Estoque concorrente**: dois operadores tentam editar e movimentar simultaneamente; saldo não pode ser sobrescrito silenciosamente.
- [ ] **Prédios públicos**: cadastro e localização no mapa.
- [ ] **Protocolos**: cadastrar, encaminhar, consultar histórico do setor e despacho.
- [ ] **Relatórios**: filtrar por status e período; comparar números da tela com Excel e impressão.
- [ ] **Excel**: abrir as abas Registros, Indicadores e Dados completos no Excel da secretaria; conferir valores, células e bordas.
- [ ] **Permissão consulta**: não consegue cadastrar, editar, excluir ou movimentar via tela nem API.
- [ ] **Auditoria**: localizar eventos de criação, edição, movimentação, exclusão e login.
- [ ] **Segurança**: testar URLs privadas retornando 403/404 e páginas `instalar.php` e `conexao.php` restritas após instalação.

## Registro de ocorrência

Use, para cada problema, o modelo: **Módulo | Ação | Perfil | Data/hora | Resultado esperado | Resultado obtido | Evidência (captura) | Gravidade | Responsável**.

## Critério mínimo para liberar piloto

A equipe de suporte deve confirmar autenticação, autorização por perfil, gravação e recuperação no MySQL, integridade de estoque, upload/download privado e exportações. Se algum teste essencial falhar, mantenha o ambiente apenas para desenvolvimento até corrigir.
