# Relatório Completo do Sistema: ERP Elétrica

Este relatório apresenta uma visão técnica e funcional detalhada do estado atual do ERP Elétrica, um sistema web robusto desenvolvido para a gestão de múltiplas unidades (Multi-Tenant) no setor de materiais elétricos e serviços.

---

## 🏗️ 1. Arquitetura e Core Técnico

O sistema é construído sobre **PHP 8.x** puro (Vanilla) com uma arquitetura **MVC personalizada**, priorizando performance e facilidade de manutenção sem dependências externas pesadas.

*   **Multi-Tenancy (Isolamento B2B)**: 
    *   Implementação via `BaseModel` que injeta automaticamente filtros de `filial_id` em todas as queries.
    *   Garantes que usuários de uma unidade não acessem dados de outras, mantendo a integridade e privacidade.
    *   Suporte a **Matriz vs Filial**: A Matriz possui visão consolidada (opcional) enquanto as filiais operam de forma isolada.
*   **Sistema de Migrações**: Automação total da evolução do banco de dados através do `MigrationService`, garantindo que todas as instâncias estejam sincronizadas.
*   **Logs e Auditoria**: Registro detalhado de ações críticas (Login, Logout, Alterações em Vendas/OS) via `AuditLogService`.

---

## 🔐 2. Segurança e Gestão de Acesso (RBAC)

O controle de acesso é granulado e baseado em níveis de usuário, protegendo funções administrativas e sensíveis.

*   **Níveis de Acesso**:
    *   **Master/Admin**: Acesso total a configurações globais, financeiro e gestão de usuários.
    *   **Gerente**: Controle operacional pleno da unidade, com foco em Vendas e Estoque.
    *   **Vendedor**: Acesso restrito a orçamentos (Pré-vendas), PDV e consulta de estoque (Apenas Visualização).
*   **Controle Dinâmico**: Além das regras fixas, o sistema possui uma estrutura de `permissao_nivel` para ajustes finos de permissões por módulo/ação.

---

## 📦 3. Módulos Operacionais

### 🛒 Frente de Caixa e Vendas
*   **PDV (Ponto de Venda)**: Interface otimizada para agilidade no balcão, com suporte a múltiplas formas de pagamento (PIX, Crédito, Débito, Dinheiro).
*   **Pré-Vendas/Orçamentos**: Fluxo para geração de orçamentos que podem ser convertidos em vendas finais, ideal para balcão técnico.
*   **Histórico Consolidado**: Rastreabilidade total de vendas por período e operador.

### 🛠️ Ordens de Serviço (OS)
*   **Gestão de Workflow**: Ciclo de vida completo desde o orçamento até a entrega do serviço.
*   **Associação de Insumos**: Integração direta com estoque para baixar materiais utilizados na manutenção/instalação.
*   **Checklist Técnico**: Registro de conformidade técnica em formato JSON para cada OS.

### 📊 Estoque e Logística
*   **Multi-Depósitos**: Gestão de estoque distribuído em diferentes locais físicos dentro da mesma unidade.
*   **Movimentação Detalhada**: Histórico de entradas, saídas, ajustes e transferências entre filiais.
*   **Alertas de Nível Crítico**: Notificações visuais no Dashboard para reposição de produtos.

---

## 📉 4. Financeiro e BI (Business Intelligence)

*   **Fluxo de Caixa**: Gestão de Contas a Pagar e Receber integrada às vendas e compras.
*   **DRE Simplificado**: Visão de lucro bruto e líquido baseada em centros de custo.
*   **Dashboards Dinâmicos**: Utilização de **ApexCharts** para visualização de performance de vendas, ticket médio e produtos mais vendidos.

---

## 🧾 5. Infraestrutura Fiscal (NFC-e / NF-e)

*   **Integração SEFAZ**: Módulo preparado para emissão de notas fiscais (Ambiente de Homologação/Produção).
*   **Gestão de Certificados**: Upload e armazenamento seguro de certificados A1 (.pfx).
*   **Parâmetros Fiscais**: Automatização de NCM, CFOP e cálculo de impostos básicos.

---

## 🚀 Situação Atual e Próximos Passos (Momentum)

O sistema encontra-se em estado **Maduro e Estável (Produção Ready)**. As últimas atualizações consolidaram as restrições de nível para vendedores e o isolamento total de filiais.

**Próximos passos sugeridos:**
1.  Expansão do módulo de Relatórios Avançados (Exportação em PDF/Excel).
2.  Implementação de App Mobile para Vendedores Externos (Consultas via API).
3.  Integração direta com gateways de pagamento para baixa automática de PIX e Cartão.

---
*Relatório gerado em 03/03/2026 às 12:12 pelo Assistente de Engenharia.*
