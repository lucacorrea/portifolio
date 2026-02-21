# 🗄️ Sistema de Membros - Versão MySQL

## Igreja de Deus Nascer de Novo

---

## 📌 O QUE MUDOU?

Esta é a versão **MySQL** do sistema. Principais diferenças:

| Aspecto | SQLite | MySQL |
|---------|--------|-------|
| **Banco** | Arquivo único | Servidor MySQL |
| **Performance** | Boa | Excelente |
| **Escalabilidade** | Limitada | Ilimitada |
| **Múltiplos usuários** | Limitado | Sim |
| **Backup** | Copiar arquivo | Dump SQL |
| **Replicação** | Não | Sim |

---

## 🚀 INSTALAÇÃO RÁPIDA

### 1. Criar Banco de Dados

Execute o arquivo `setup-mysql.sql` em phpMyAdmin:

```
https://seu_dominio.com/phpmyadmin
```

### 2. Configurar Credenciais

Edite `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'igreja_membros');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. Fazer Upload

Faça upload de todos os arquivos via FTP

### 4. Acessar

```
https://seu_dominio.com/sistema-membros-mysql/
```

---

## 📁 ARQUIVOS IMPORTANTES

### Novo/Modificado

- ✅ `config/database.php` - Configuração MySQL
- ✅ `setup-mysql.sql` - Script de instalação
- ✅ `INSTALACAO_MYSQL.md` - Guia detalhado

### Mantidos

- ✅ Todos os outros arquivos funcionam igual
- ✅ Interface é a mesma
- ✅ Funcionalidades são as mesmas

---

## 🔧 CONFIGURAÇÃO

### Arquivo: `config/database.php`

```php
<?php
// Edite estes valores com suas credenciais MySQL

define('DB_HOST', 'localhost');      // Host MySQL
define('DB_NAME', 'igreja_membros');  // Nome do banco
define('DB_USER', 'root');            // Usuário
define('DB_PASS', '');                // Senha
define('DB_PORT', 3306);              // Porta
define('DB_CHARSET', 'utf8mb4');      // Charset
?>
```

### Exemplo com Usuário Específico

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'igreja_membros');
define('DB_USER', 'igreja_user');
define('DB_PASS', 'sua_senha_aqui');
define('DB_PORT', 3306);
define('DB_CHARSET', 'utf8mb4');
```

---

## 📊 CRIAR BANCO DE DADOS

### Opção 1: phpMyAdmin (Fácil)

1. Acesse phpMyAdmin
2. Clique em "SQL"
3. Cole conteúdo de `setup-mysql.sql`
4. Clique em "Executar"

### Opção 2: SSH (Rápido)

```bash
mysql -u root -p < setup-mysql.sql
```

### Opção 3: Manualmente

1. Criar banco: `CREATE DATABASE igreja_membros;`
2. Executar script SQL

---

## ✅ VERIFICAR INSTALAÇÃO

### Teste 1: Banco Criado

```sql
SHOW DATABASES LIKE 'igreja_membros';
```

### Teste 2: Tabelas Criadas

```sql
USE igreja_membros;
SHOW TABLES;
```

### Teste 3: Dados de Exemplo

```sql
SELECT COUNT(*) FROM membros;
```

Deve retornar: **5** (membros de exemplo)

---

## 🔐 SEGURANÇA

### Criar Usuário Específico

```sql
CREATE USER 'igreja_user'@'localhost' IDENTIFIED BY 'senha_forte';
GRANT ALL PRIVILEGES ON igreja_membros.* TO 'igreja_user'@'localhost';
FLUSH PRIVILEGES;
```

### Proteger Banco

- ✅ Use usuário específico (não root)
- ✅ Senha forte (mínimo 12 caracteres)
- ✅ Restrinja host (localhost)
- ✅ Faça backups regulares

---

## 💾 BACKUP E RESTAURAÇÃO

### Fazer Backup

```bash
# Via SSH
mysqldump -u root -p igreja_membros > backup_$(date +%Y%m%d).sql

# Via phpMyAdmin
# Banco → Exportar → Selecionar tudo → Executar
```

### Restaurar Backup

```bash
# Via SSH
mysql -u root -p igreja_membros < backup_20260219.sql

# Via phpMyAdmin
# Banco → Importar → Selecionar arquivo → Executar
```

---

## 📈 PERFORMANCE

### Índices Criados Automaticamente

- ✅ `idx_nome` - Busca por nome
- ✅ `idx_cpf` - Busca por CPF
- ✅ `idx_tipo_integracao` - Filtro por tipo
- ✅ `idx_data_cadastro` - Ordenação por data
- ✅ `idx_estado_civil` - Filtro por estado civil
- ✅ `idx_sexo` - Filtro por sexo

### Otimizações Disponíveis

```sql
-- Otimizar tabela
OPTIMIZE TABLE membros;

-- Analisar tabela
ANALYZE TABLE membros;

-- Reparar tabela
REPAIR TABLE membros;
```

---

## 🐛 TROUBLESHOOTING

### Erro: "Connection refused"

**Causa:** MySQL não está rodando ou host está errado

**Solução:**
```php
define('DB_HOST', 'localhost'); // ou 127.0.0.1
```

### Erro: "Access denied"

**Causa:** Usuário ou senha incorretos

**Solução:**
1. Verifique credenciais em `config/database.php`
2. Teste credenciais no phpMyAdmin
3. Crie novo usuário se necessário

### Erro: "Unknown database"

**Causa:** Banco não foi criado

**Solução:**
1. Execute `setup-mysql.sql`
2. Verifique nome do banco em phpMyAdmin
3. Verifique em `config/database.php`

### Erro: "Table doesn't exist"

**Causa:** Tabelas não foram criadas

**Solução:**
1. Execute `setup-mysql.sql` novamente
2. Verifique em phpMyAdmin
3. Verifique permissões do usuário

---

## 🔄 MIGRAR DE SQLITE PARA MYSQL

Se já tem dados no SQLite:

### Script de Migração

```php
<?php
// Conectar ao SQLite
$sqlite = new PDO('sqlite:../data/membros.db');

// Conectar ao MySQL
$mysql = new PDO('mysql:host=localhost;dbname=igreja_membros', 'root', '');

// Copiar dados
$stmt = $sqlite->query('SELECT * FROM membros');
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($dados as $membro) {
    $insert = $mysql->prepare("
        INSERT INTO membros VALUES (
            NULL, :nome, :data_nasc, :nacionalidade, :naturalidade, :estado_uf,
            :sexo, :tipo_sang, :escolaridade, :profissao, :rg, :cpf, :titulo,
            :ctp, :cdi, :pai, :mae, :estado_civil, :conjuge, :filhos,
            :rua, :numero, :bairro, :cep, :cidade, :uf, :telefone,
            :tipo_integracao, :data_integracao, :batismo_aguas, :batismo_espirito,
            :procedencia, :congregacao, :area, :nucleo, :foto, NOW(), NOW()
        )
    ");
    $insert->execute($membro);
}
?>
```

---

## 📚 DOCUMENTAÇÃO COMPLETA

Veja `INSTALACAO_MYSQL.md` para:

- ✅ Guia passo a passo
- ✅ Criar banco de dados
- ✅ Criar usuário MySQL
- ✅ Configurar arquivo PHP
- ✅ Fazer upload
- ✅ Testar instalação
- ✅ Troubleshooting
- ✅ Backup e restauração
- ✅ Otimizações

---

## 🎯 PRÓXIMOS PASSOS

1. ✅ Executar `setup-mysql.sql`
2. ✅ Editar `config/database.php`
3. ✅ Fazer upload dos arquivos
4. ✅ Acessar o sistema
5. ✅ Testar funcionalidades
6. ✅ Fazer backup

---

## ✨ FUNCIONALIDADES

Todas as funcionalidades da versão SQLite funcionam igual:

- ✅ Dashboard com gráficos
- ✅ Cadastro de membros
- ✅ Listagem e busca
- ✅ Ficha de impressão
- ✅ Relatórios em PDF
- ✅ Edição de dados
- ✅ Exclusão de membros
- ✅ Interface responsiva

---

## 📞 SUPORTE

Se tiver dúvidas:

1. Consulte `INSTALACAO_MYSQL.md`
2. Verifique `config/database.php`
3. Teste credenciais no phpMyAdmin
4. Verifique permissões de arquivo
5. Contate suporte da hospedagem

---

**Desenvolvido para Igreja de Deus Nascer de Novo**

*Última atualização: Fevereiro 2026*
