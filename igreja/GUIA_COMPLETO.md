# 📘 Guia Completo do Sistema de Membros

## 🎯 Visão Geral

O **Sistema de Membros da Igreja de Deus Nascer de Novo** é uma aplicação web completa desenvolvida em **PHP + JavaScript + Bootstrap** para gerenciar membros da Igreja com funcionalidades de cadastro, listagem, busca, impressão de fichas e geração de relatórios.

---

## 🚀 Como Começar

### 1. Acessar o Sistema

Abra seu navegador e acesse:
```
http://localhost:8080/
```

Você verá a interface principal com:
- **Sidebar** com menu de navegação
- **Dashboard** com estatísticas
- **Listagem de membros**
- **Botões de ação**

### 2. Estrutura da Interface

```
┌─────────────────────────────────────────┐
│  SIDEBAR                │  CONTEÚDO PRINCIPAL
│                         │
│ • Dashboard             │  [Header com Novo Membro]
│ • Membros               │  [Conteúdo da página]
│ • Novo Membro           │
│ • Relatórios            │
└─────────────────────────────────────────┘
```

---

## 📋 Funcionalidades Principais

### 1. Dashboard
**Acesso:** Menu lateral → Dashboard ou clique no logo

**O que você vê:**
- Total de membros cadastrados
- Gráficos interativos:
  - Tipo de integração (Batismo, Mudança, Aclamação)
  - Distribuição por sexo
  - Distribuição por estado civil
  - Faixa etária
- Tabela resumida de dados
- Botões para exportar relatórios

**Recursos:**
- ✅ Gráficos em tempo real
- ✅ Atualização automática
- ✅ Exportação de relatórios em PDF

---

### 2. Listagem de Membros
**Acesso:** Menu lateral → Membros

**O que você vê:**
- Lista paginada de membros (10 por página)
- Informações resumidas de cada membro
- Botões de ação para cada membro

**Ações disponíveis:**
- 👁️ **Visualizar** - Ver detalhes completos
- ✏️ **Editar** - Modificar dados
- 🖨️ **Imprimir** - Abrir ficha para impressão
- 📄 **Relatório** - Gerar PDF individual
- 🗑️ **Deletar** - Remover membro

---

### 3. Cadastro de Novo Membro
**Acesso:** Botão "Novo Membro" no topo ou menu lateral

**Seções do Formulário:**

#### Dados Pessoais
- Nome Completo (obrigatório)
- Data de Nascimento
- Sexo
- CPF
- RG
- Tipo Sanguíneo
- Nacionalidade
- Naturalidade
- Estado (UF)
- Escolaridade
- Profissão

#### Documentos
- Título de Eleitor
- CTP
- CDI

#### Filiação
- Pai
- Mãe

#### Estado Civil
- Estado Civil
- Cônjuge
- Filhos

#### Endereço Residencial
- Rua
- Número
- Bairro
- CEP
- Cidade
- Estado (UF)
- Telefone

#### Dados Eclesiásticos
- Tipo de Integração (Batismo, Mudança, Aclamação)
- Data de Integração
- Batismo em Águas
- Batismo no Espírito Santo
- Procedência
- Congregação
- Área
- Núcleo

#### Foto
- Upload de foto 3x4 (máximo 5MB)
- Formatos aceitos: JPG, PNG, GIF

**Como Cadastrar:**
1. Clique em "Novo Membro"
2. Preencha os campos desejados
3. Faça upload da foto (opcional)
4. Clique em "Salvar Membro"
5. Pronto! O membro será adicionado

---

### 4. Busca de Membros
**Acesso:** Caixa de busca na listagem de membros

**Como Buscar:**
- Digite o nome, CPF ou telefone
- Mínimo 2 caracteres
- Resultados aparecem em tempo real

**Exemplo:**
- Buscar por nome: "João"
- Buscar por CPF: "123"
- Buscar por telefone: "92"

---

### 5. Ficha de Impressão
**Acesso:** Clique no ícone de impressora (🖨️) na listagem

**O que contém:**
- Logo e informações da Igreja
- Foto do membro (3x4)
- Todos os dados do membro
- Espaço para assinaturas
- Caixa de recebimento (Secretaria Geral)
- Declaração de adesão

**Como Imprimir:**
1. Clique no ícone de impressora
2. A ficha abrirá em uma nova aba
3. Use Ctrl+P ou clique em "Imprimir"
4. Configure a impressora
5. Clique em "Imprimir"

**Como Salvar como PDF:**
1. Abra a ficha
2. Use Ctrl+P
3. Selecione "Salvar como PDF"
4. Escolha a pasta e nome
5. Clique em "Salvar"

---

### 6. Relatórios
**Acesso:** Menu lateral → Relatórios ou Dashboard

**Tipos de Relatórios:**

#### Relatório Individual
- Dados completos de um membro
- Formato: HTML/PDF
- Acesso: Clique no ícone de PDF na listagem

#### Lista de Todos os Membros
- Tabela com todos os membros
- Colunas: Nome, CPF, Telefone, Tipo de Integração, Data
- Formato: HTML/PDF
- Acesso: Dashboard → "Lista de Membros"

#### Relatório de Estatísticas
- Gráficos e tabelas de dados
- Distribuição por tipo, sexo, estado civil
- Percentuais e totais
- Formato: HTML/PDF
- Acesso: Dashboard → "Relatório de Estatísticas"

---

## 🔍 Recursos Avançados

### Visualização Detalhada de Membro
**Como Acessar:**
1. Na listagem, clique no ícone de olho (👁️)
2. Um modal abrirá com todos os dados

**Dados Exibidos:**
- Informações pessoais completas
- Documentos
- Filiação
- Estado civil
- Endereço
- Dados eclesiásticos
- Foto (se disponível)

### Edição de Membro
**Como Acessar:**
1. Na listagem, clique no ícone de lápis (✏️)
2. O formulário abrirá com os dados preenchidos
3. Modifique os campos desejados
4. Clique em "Atualizar Membro"

### Exclusão de Membro
**Como Acessar:**
1. Na listagem, clique no ícone de lixo (🗑️)
2. Confirme a exclusão
3. O membro será removido do banco de dados

---

## 📊 Compreendendo os Gráficos

### Gráfico de Tipo de Integração
- **Tipo:** Rosca (Doughnut)
- **Mostra:** Distribuição de membros por tipo (Batismo, Mudança, Aclamação)
- **Uso:** Entender qual tipo de integração é mais comum

### Gráfico de Sexo
- **Tipo:** Barras horizontais
- **Mostra:** Comparação entre membros masculinos e femininos
- **Uso:** Análise demográfica

### Gráfico de Estado Civil
- **Tipo:** Pizza
- **Mostra:** Distribuição por estado civil (Solteiro, Casado, Divorciado, etc.)
- **Uso:** Entender composição familiar

### Gráfico de Faixa Etária
- **Tipo:** Linha
- **Mostra:** Distribuição de membros por faixa etária
- **Uso:** Análise de idade da congregação

---

## 🎨 Design e Cores

### Paleta de Cores
- **Azul-marinho (#1a2e4a):** Cor primária, headers, botões
- **Dourado (#c9a84c):** Cor secundária, destaques
- **Branco (#ffffff):** Fundo de cards
- **Cinza quente (#f5f3f0):** Fundo geral

### Responsividade
- ✅ Desktop: Layout completo com sidebar
- ✅ Tablet: Sidebar colapsável
- ✅ Mobile: Sidebar em overlay, tabelas scrolláveis

---

## 💾 Dados e Banco de Dados

### Localização do Banco
```
/home/ubuntu/sistema-membros-igreja/data/membros.db
```

### Backup de Dados
**Fazer Backup:**
```bash
cp /home/ubuntu/sistema-membros-igreja/data/membros.db /backup/membros_backup.db
```

**Restaurar Backup:**
```bash
cp /backup/membros_backup.db /home/ubuntu/sistema-membros-igreja/data/membros.db
```

### Dados de Exemplo
O sistema vem com 5 membros de exemplo para teste. Para adicionar mais dados:
```bash
php /home/ubuntu/sistema-membros-igreja/public/seed-database.php
```

---

## ⚙️ Configurações

### Alterar Permissões
```bash
chmod -R 755 /home/ubuntu/sistema-membros-igreja
chmod -R 777 /home/ubuntu/sistema-membros-igreja/data
chmod -R 777 /home/ubuntu/sistema-membros-igreja/public/uploads
```

### Verificar Integridade do Banco
```bash
sqlite3 /home/ubuntu/sistema-membros-igreja/data/membros.db "PRAGMA integrity_check;"
```

### Otimizar Banco de Dados
```bash
sqlite3 /home/ubuntu/sistema-membros-igreja/data/membros.db "VACUUM;"
```

---

## 🔐 Segurança

### Proteção de Dados
- ✅ Validação de entrada
- ✅ Sanitização de dados
- ✅ Prepared statements (previne SQL injection)
- ✅ CPF único (sem duplicação)
- ✅ Validação de tipos de arquivo

### Boas Práticas
1. Faça backup regularmente
2. Restrinja acesso ao arquivo `membros.db`
3. Mantenha PHP atualizado
4. Use HTTPS em produção
5. Altere permissões de arquivo após instalação

---

## 🆘 Troubleshooting

### Problema: Página em branco
**Solução:**
1. Verifique se o servidor PHP está rodando
2. Verifique permissões de arquivo
3. Verifique logs do PHP: `/var/log/php-fpm.log`

### Problema: Erro ao fazer upload de foto
**Solução:**
1. Verifique permissões da pasta `public/uploads/`
2. Verifique tamanho do arquivo (máximo 5MB)
3. Verifique formato (JPG, PNG, GIF)

### Problema: Banco de dados não criado
**Solução:**
1. Verifique permissões da pasta `data/`
2. Verifique se PHP tem permissão de escrita
3. Execute: `chmod 777 /home/ubuntu/sistema-membros-igreja/data`

### Problema: Gráficos não aparecem
**Solução:**
1. Verifique conexão com internet (Chart.js é carregado via CDN)
2. Verifique console do navegador (F12)
3. Limpe cache: Ctrl+Shift+Delete

---

## 📞 Suporte

Para dúvidas ou problemas:
1. Verifique este guia
2. Consulte o arquivo README.md
3. Verifique o arquivo BANCO_DE_DADOS.md para queries SQL
4. Verifique permissões de arquivo/pasta

---

## 📚 Arquivos Importantes

| Arquivo | Descrição |
|---------|-----------|
| `index.php` | Página principal do sistema |
| `dashboard.php` | Dashboard com gráficos |
| `ficha-impressao.php` | Ficha de impressão de membro |
| `membros.php` | API CRUD de membros |
| `relatorio.php` | Geração de relatórios |
| `database.php` | Configuração do banco de dados |
| `functions.php` | Funções auxiliares |
| `app.js` | Lógica JavaScript |
| `style.css` | Estilos CSS |

---

## 🎓 Próximos Passos

1. ✅ Cadastre seus primeiros membros
2. ✅ Explore o dashboard
3. ✅ Imprima fichas de membros
4. ✅ Gere relatórios
5. ✅ Faça backup dos dados

---

**Desenvolvido para Igreja de Deus Nascer de Novo**

*Última atualização: Fevereiro 2026*
