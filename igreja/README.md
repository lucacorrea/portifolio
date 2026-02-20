# Sistema de Membros - Igreja de Deus Nascer de Novo

Sistema web completo para gerenciamento de membros da Igreja de Deus Nascer de Novo, desenvolvido em **PHP + JavaScript + Bootstrap**.

## 🎯 Funcionalidades

### Dashboard
- **Estatísticas gerais** com total de membros
- **Gráficos interativos** mostrando:
  - Distribuição por tipo de integração (Batismo, Mudança, Aclamação)
  - Distribuição por sexo
  - Distribuição por estado civil
  - Faixa etária dos membros

### Gerenciamento de Membros
- ✅ **Cadastro completo** com todos os campos do formulário original
- ✅ **Listagem paginada** de membros
- ✅ **Busca avançada** por nome, CPF ou telefone
- ✅ **Edição** de dados de membros
- ✅ **Exclusão** de membros
- ✅ **Upload de foto** (3x4) com validação
- ✅ **Visualização detalhada** de cada membro

### Relatórios
- 📄 **Relatório em PDF** de membro individual
- 📄 **Relatório em PDF** com lista de todos os membros
- 📄 **Relatório em PDF** com estatísticas gerais

### Design
- 🎨 **Interface moderna e profissional**
- 🎨 **Paleta de cores institucional** (Azul-marinho + Dourado)
- 🎨 **Responsivo** para desktop, tablet e mobile
- 🎨 **Navegação intuitiva** com sidebar fixa

## 📋 Estrutura do Projeto

```
sistema-membros-igreja/
├── config/
│   └── database.php          # Configuração do banco de dados SQLite
├── api/
│   ├── membros.php           # API CRUD de membros
│   └── relatorio.php         # Geração de relatórios PDF
├── includes/
│   └── functions.php         # Funções auxiliares
├── public/
│   ├── index.php             # Página principal
│   ├── css/
│   │   └── style.css         # Estilos customizados
│   ├── js/
│   │   └── app.js            # Lógica da aplicação
│   ├── uploads/              # Fotos dos membros
│   └── .htaccess             # Rewrite de URLs
├── data/
│   └── membros.db            # Banco de dados SQLite
└── README.md
```

## 🚀 Como Usar

### Requisitos
- PHP 7.4+
- SQLite3 (geralmente já vem com PHP)
- Servidor web (Apache com mod_rewrite ou Nginx)
- Navegador moderno

### Instalação

1. **Copiar arquivos para o servidor**
```bash
cp -r sistema-membros-igreja /var/www/html/
```

2. **Definir permissões**
```bash
chmod -R 755 /var/www/html/sistema-membros-igreja
chmod -R 777 /var/www/html/sistema-membros-igreja/data
chmod -R 777 /var/www/html/sistema-membros-igreja/public/uploads
```

3. **Acessar no navegador**
```
http://localhost/sistema-membros-igreja/public/
```

### Primeiro Acesso
- O banco de dados SQLite será criado automaticamente
- A tabela de membros será criada na primeira execução
- Você pode começar a cadastrar membros imediatamente

## 📝 Campos de Cadastro

### Dados Pessoais
- Nome Completo (obrigatório)
- Data de Nascimento
- Sexo (M/F)
- CPF (com validação)
- RG
- Tipo Sanguíneo
- Nacionalidade
- Naturalidade
- Estado (UF)
- Escolaridade
- Profissão

### Documentos
- Título de Eleitor
- CTP
- CDI

### Filiação
- Pai
- Mãe

### Estado Civil
- Estado Civil
- Cônjuge
- Filhos

### Endereço Residencial
- Rua
- Número
- Bairro
- CEP
- Cidade
- Estado (UF)
- Telefone

### Dados Eclesiásticos
- Tipo de Integração (Batismo, Mudança, Aclamação)
- Data de Integração
- Batismo em Águas
- Batismo no Espírito Santo
- Procedência
- Congregação
- Área
- Núcleo

### Foto
- Upload de foto 3x4 (máximo 5MB)

## 🔧 Configuração

### Banco de Dados
O banco de dados é SQLite, armazenado em `data/membros.db`. Não requer configuração adicional.

### Uploads de Fotos
As fotos são armazenadas em `public/uploads/` com nomes únicos para evitar conflitos.

## 📊 Gráficos e Estatísticas

O dashboard utiliza **Chart.js** para criar gráficos interativos:
- Gráfico de rosca para tipo de integração
- Gráfico de barras para distribuição por sexo
- Gráfico de pizza para estado civil
- Gráfico de linha para faixa etária

## 🔐 Segurança

- ✅ Validação de CPF
- ✅ Sanitização de entrada (XSS prevention)
- ✅ Prepared statements (SQL injection prevention)
- ✅ Validação de tipos de arquivo para upload
- ✅ Limite de tamanho de arquivo (5MB)

## 🎨 Paleta de Cores

| Cor | Código | Uso |
|-----|--------|-----|
| Azul-marinho | #1a2e4a | Primária, headers |
| Dourado | #c9a84c | Secundária, destaques |
| Branco | #ffffff | Fundo, cards |
| Cinza quente | #f5f3f0 | Fundo geral |
| Verde-água | #2d7d6f | Sucesso |
| Vermelho | #d32f2f | Erro |

## 📱 Responsividade

O sistema é totalmente responsivo:
- **Desktop**: Layout completo com sidebar
- **Tablet**: Sidebar colapsável
- **Mobile**: Sidebar em overlay, tabelas scrolláveis

## 🐛 Troubleshooting

### Erro ao fazer upload de foto
- Verifique permissões da pasta `public/uploads/`
- Verifique tamanho do arquivo (máximo 5MB)
- Verifique formato (JPG, PNG, GIF)

### Banco de dados não criado
- Verifique permissões da pasta `data/`
- Verifique se PHP tem permissão de escrita

### Gráficos não aparecem
- Verifique conexão com CDN do Chart.js
- Verifique console do navegador para erros

## 📞 Suporte

Para dúvidas ou problemas, verifique:
1. Permissões de arquivo/pasta
2. Versão do PHP (7.4+)
3. Extensões PHP necessárias (PDO, SQLite3)
4. Mod_rewrite ativado (Apache)

## 📄 Licença

Sistema desenvolvido para Igreja de Deus Nascer de Novo.

---

**Desenvolvido com ❤️ para a Igreja de Deus Nascer de Novo**
