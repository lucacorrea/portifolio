# Relatório Operacional: ERP Elétrica (Sistema Multi-Unidades)

Este documento detalha o estado atual do sistema, segmentado por módulos e capacidades técnicas. O sistema foi transformado em uma arquitetura **B2B Multi-Tenant**, permitindo a gestão isolada ou centralizada de múltiplas empresas/filiais.

---

## 1. Núcleo e Arquitetura (Core)
O sistema utiliza uma estrutura robusta em PHP 8.x seguindo o padrão **MVC** (Model-View-Controller) com camadas adicionais de serviços.

*   **Isolamento B2B Automático**: O `BaseModel` injeta filtros de segurança em todas as consultas. Se um usuário loga em uma filial, ele **fisicamente não consegue ver** dados de outra filial.
*   **Sistema de Migrações**: Automação de alterações de banco de dados (`MigrationService`), garantindo que todas as unidades rodem a mesma versão do esquema.
*   **Log de Auditoria**: Registro de todas as ações críticas para rastreabilidade fiscal e operacional.

---

## 2. Autenticação e Controle de Acesso (IAM)
*   **Seleção de Unidade**: Login com escolha prévia da filial/matriz.
*   **Gestão de Níveis**: Hierarquia entre Administrador, Gerente, Técnico e Vendedor.
*   **Sessões Seguras**: Controle de contexto de filial em tempo real, impedindo acessos cruzados.

---

## 3. Gestão de Unidades e Filiais
*   **Cadastro Corporativo**: Registro completo de novas unidades com endereço fiscal e CNPJ.
*   **Central Fiscal**: Configuração individualizada de **Ambiente SEFAZ** (Homologação/Produção), Tokens CSC e upload de **Certificados Digitais (A1 .pfx)**.
*   **Teste de Conectividade**: Validação em tempo real da comunicação com os servidores da SEFAZ.

---

## 4. Frente de Caixa (PDV) e Vendas
*   **Checkout Rápido**: Interface otimizada para vendas rápidas no balcão.
*   **Múltiplas Formas de Pagamento**: Suporte a PIX, Cartões e Dinheiro.
*   **Pré-Vendas**: Possibilidade de orçamentação antes da efetivação da venda.
*   **Histórico de Vendas**: Visão detalhada de transações passadas, filtradas por filial.

---

## 5. Módulo Fiscal (NFE / NFCE)
*   **Emissão Instantânea**: Geração automática de NFC-e após a venda.
*   **Gestão de XML/PDF**: Repositório central para download de arquivos fiscais e impressão de DANFE.
*   **Conformidade**: Automatização de NCM, CFOP e alíquotas de ICMS baseadas no cadastro do produto.

---

## 6. Estoque e Catálogo de Produtos
*   **Controle Dimensional**: Gestão de entradas, saídas e estoque mínimo.
*   **Alertas Críticos**: Identificação visual no dashboard de itens abaixo do limite de segurança.
*   **Curva ABC**: Análise inteligente de produtos mais vendidos vs. mais rentáveis.

---

## 7. Ordens de Serviço (OS)
*   **Fluxo de Trabalho**: Gestão do ciclo de vida técnico (Aberto, Em Execução, Aguardando Peças, Finalizado).
*   **Detalhamento Técnico**: Associação de produtos e mão de obra a cada ordem de serviço específica.
*   **Impressão Profissional**: Relatórios de OS formatados para entrega ao cliente.

---

## 8. Financeiro e BI (Business Intelligence)
*   **DRE (Demonstrativo de Resultados)**: Visão financeira de lucro e prejuízo por período.
*   **Contas a Pagar/Receber**: Controle de prazos e fluxo de caixa futuro.
*   **Dashboard Executivo**: Gráficos dinâmicos (ApexCharts) com comparativos mensais, ticket médio e margens de lucro.

---

## 9. Gestão de Colaboradores
*   **Diretório de Equipe**: Cadastro de funcionários com vinculação direta a uma unidade de lotação.
*   **Controle de Atividade**: Monitoramento de último acesso e status do operador.

---

### Situação Atual: **OPERACIONAL E PRONTO PARA EXPANSÃO**
O sistema encontra-se em um estado avançado de maturidade, com todos os módulos essenciais para uma rede de lojas elétrica totalmente funcionais e isolados por unidade.
