# OBRAS360 — revisão funcional e de segurança (V7)

**Data:** 24/11/2026  
**Fonte auditada:** `OBRAS360_COMPLETO_EXCEL_V6_BORDAS_CENTRALIZADAS.zip`, a última versão fornecida nesta conversa.  
**Objetivo:** reduzir riscos antes de liberar o piloto para a equipe da Secretaria de Obras e Planejamento.  
**Classificação:** **BETA / PILOTO CONTROLADO. Não homologado para uso oficial.**

## 1. Resultado executivo

O sistema possui implementação PHP/MySQL e interfaces de todos os módulos operacionais. Nesta revisão foram feitas correções específicas em autenticação/instalação, consistência concorrente, armazenamento de anexos e relatórios, sem substituir o layout do painel nem recriar as tabelas.

**Não foi executado teste real no MySQL da Hostinger.** Este ambiente tem PHP CLI, mas não possui o driver `pdo_mysql`. Por isso, o funcionamento completo de cadastro, edição, login e anexos no servidor real continua **pendente de homologação manual**. Teste local de renderização não é prova de funcionamento integral.

## 2. Correções aplicadas no código

| Prioridade | Componente | Problema observado no V6 | Correção na V7 |
|---|---|---|---|
| Alta | Instalação / recuperação | `instalar.php?reconfigurar=1` aceitava tentativa de redefinição pública da conta de suporte com senha MySQL, sem exigir sessão de suporte. | Se houver configuração instalada, somente uma sessão de suporte autenticada pode reconfigurar. Se a configuração desaparecer mas já houver suporte ativo no banco, o instalador bloqueia a redefinição pública e exige recuperação administrativa. |
| Alta | Credenciais | O instalador podia gravar `config/conexao.local.php` dentro da árvore publicada em um fallback. | Novas gravações via web são limitadas ao diretório privado `obras360-private/`. A leitura legada foi preservada para não derrubar instalações antigas. |
| Média | Materiais | Edição de material consultava o saldo antes da transação; uma movimentação concorrente podia ser sobrescrita. | Leitura bloqueante `SELECT ... FOR UPDATE` e gravação dentro da mesma transação; edição com saldo desatualizado recebe HTTP 409. |
| Média | Usuários | Duas alterações simultâneas podiam colocar em risco a regra de manter o último suporte ativo. | Bloqueio transacional ordenado das contas de suporte antes de desativar ou alterar perfil. |
| Média | Anexos | Novos uploads podiam usar pasta de fallback potencialmente exposta na raiz pública. | Novos uploads exigem diretório privado; downloads legados continuam localizáveis. Arquivo é removido se a transação do registro falhar. |
| Média | Relatórios | Filtragem por período incluía registros sem nenhuma data de referência. | Registros sem data ficam fora da seleção quando houver filtro de data. |
| Baixa | Planejamento | Salvar marco sem mudanças retornava erro de registro inexistente. | Confere a existência e aceita gravação sem mudanças. |
| Baixa | Apresentação | Página de apresentação afirmava, incorretamente, que não havia banco ou permissões. | Texto atualizado para refletir a versão PHP/MySQL e avisar sobre dados fictícios. |
| Baixa | Diagnóstico | Página pública de conexão exibia detalhes operacionais após instalação. | Quando há configuração instalada, diagnóstico exige sessão autenticada de suporte. |

Nenhuma tabela foi apagada, nenhuma credencial privada foi incluída e **não há SQL adicional obrigatório**.

## 3. Matriz de módulos

| Módulo | Inspeção de código | Testes locais | Pendente no MySQL da Hostinger |
|---|---|---|---|
| Login, logout e senha | Autenticação com Argon2id, CSRF, sessões e limites de tentativa | Código e regras analisados | Login correto/incorreto, recuperação e expiração real |
| Usuários e permissões | Criação/edição via PHP, perfis e desativação | Formulário renderizado; regras de autorização verificadas | Criar gestor/operador/consulta, ativar/desativar, impedimento de último suporte |
| Obras | CRUD, validação, coordenadas | Payload obrigatório/invalidado e tela | Criar/editar/excluir, anexar e visualizar no mapa |
| Ordens de serviço | CRUD com prioridades e situações | Payload e tela | Criar, atualizar, filtrar e gerar relatório |
| Planejamento | Marcos e cronograma | Tela + correção do salvar sem alterações | Criar/editar/excluir marco e conferir listagem |
| Fiscalização | CRUD, arquivo e mapa | Payload e tela | Vistoria completa com anexo e localização |
| Projetos/documentos | CRUD e anexos | Payload e tela | Upload/download e tipos de arquivo autorizados |
| Orçamentos/medições | CRUD, valores e relatório | Payload e tela | Valores numéricos, atualização, impressão |
| Materiais/estoque | Entradas/saídas e saldo | Payload + revisão da transação | Entradas/saídas simultâneas e rejeição de saldo negativo |
| Prédios públicos | CRUD + localização | Payload e tela | Cadastro e ponto no mapa |
| Protocolos | CRUD + encaminhamentos | Payload e tela | Tramitar e comprovar histórico por usuário |
| Mapas | Camadas Leaflet, marcadores e fallback | Renderização JS sem Leaflet real | Abrir mapa online e confirmar coordenadas reais |
| Relatórios/Excel | Impressão HTML e XLSX 3 abas | XHTML/XLSX gerados e XML inspecionado | Abrir no Microsoft Excel, conferir valores reais, imprimir em A4 |
| Auditoria | Criação de eventos no banco e controle de acesso | Inspeção estática | Rastrear criação, edição e exclusão por usuários diferentes |

## 4. Testes executados nesta revisão

- **47 arquivos PHP:** análise de sintaxe `php -l` — sem erros.
- **JavaScript:** `node --check` e `node tools/testar_interface.js` — **20/20 telas renderizadas** em ambiente simulado.
- **Regras PHP:** `php tools/testar_regras.php` — **28/28 verificações** (campos obrigatórios, oito módulos, senha mínima, permissões, datas, coordenadas e saída gerada).
- **Proteção da API:** requisição de listagem de usuários sem sessão — retorna erro de autenticação (`Faça login para continuar.`).
- **Planilha de teste:** ZIP/Office XML íntegro, com abas **Registros**, **Indicadores** e **Dados completos**, contendo registros fictícios de teste.
- **Impressão:** HTML gerado em memória contém os dados de teste e identidade OBRAS360.

**Limites:** os testes PHP não fizeram consultas reais ao MySQL nem uploads HTTP reais; o mapa não foi testado contra o servidor OpenStreetMap; a conferência do Excel não substitui a abertura no Microsoft Excel em uma estação da secretaria.

## 5. Pendências antes de liberar usuários reais

1. Fazer **backup completo do banco e das pastas privadas de anexos**.
2. Conferir que `obras360-private/conexao.php` e `obras360-private/uploads` estão realmente **fora de `public_html`**, com permissões adequadas. Copiar arquivos antigos de upload da pasta pública para a privada quando for seguro.
3. Como as credenciais MySQL já foram compartilhadas no chat, **trocar a senha do banco** na Hostinger e atualizar somente o arquivo PHP privado; nunca sincronizar senhas pelo GitHub.
4. Se possível, usar um banco separado para testes ou fazer backup com plano de restauração verificado. Dados fictícios não devem ser apresentados como oficiais.
5. Testar a matriz do item 3 com 3–5 usuários de perfis diferentes, coletando capturas e resultados.
6. Restringir ou retirar `instalar.php` e `conexao.php` do acesso público após terminar o piloto (não afeta login e módulos).
7. Verificar se a hospedagem respeita `.htaccess`, impede navegação a `app/`, `config/`, `database/`, `storage/`, `tools/` e usa HTTPS válido.
8. Confirmar cópias de segurança automáticas, procedimentos de restauração e política de guarda de dados antes de usar documentos oficiais.

## 6. Limitações de arquitetura ainda presentes

- Os níveis `gestor` e `operador` têm praticamente as mesmas permissões; **não há autorização por secretaria, equipe ou registro**.
- O carregamento do painel consulta todos os registros em todos os módulos em cada acesso; esse padrão pode ficar lento com volume alto.
- Campos de vínculos entre obras, vistorias, medições e protocolos são principalmente texto livre, não chaves estrangeiras de domínio.
- Não há segunda etapa de autenticação (MFA), fluxo de aprovação/homologação documental nem assinatura digital oficial.
- Mapas com coordenadas do banco exigem validação humana: dados demonstrativos foram originalmente inventados.
- As senhas permanecem com mínimo de 8 caracteres por escolha do projeto demonstrativo. Recomenda-se uma política mais forte para produção.

**Decisão recomendada:** autorizar **somente um piloto assistido com dados fictícios**, depois de concluir os testes da seção 5. Não declarar o OBRAS360 “100% funcional” ou “homologado” antes das operações verificadas na Hostinger.
