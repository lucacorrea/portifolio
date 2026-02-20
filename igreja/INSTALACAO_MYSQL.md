# 🗄️ Guia de Instalação com MySQL

## Sistema de Membros - Igreja de Deus Nascer de Novo

---

## 📋 Diferenças: SQLite vs MySQL

| Aspecto | SQLite | MySQL |
|---------|--------|-------|
| **Instalação** | Nenhuma | Requer servidor MySQL |
| **Performance** | Boa para poucos dados | Excelente para muitos dados |
| **Escalabilidade** | Limitada | Ilimitada |
| **Backup** | Copiar arquivo | Dump SQL |
| **Usuários** | Não | Sim |
| **Replicação** | Não | Sim |
| **Melhor para** | Pequenas aplicações | Produção |

---

## ✅ PASSO 1: VERIFICAR REQUISITOS

### Sua hospedagem deve ter:

- ✅ PHP 7.4 ou superior
- ✅ MySQL 5.7+ ou MariaDB 10.2+
- ✅ Extensão PDO para MySQL (php-pdo-mysql)
- ✅ phpMyAdmin (para gerenciar banco)

### Verificar Versão PHP

```bash
# Via SSH
php -v

# Via navegador
# Crie arquivo info.php com:
<?php phpinfo(); ?>
```

### Verificar Extensão MySQL

```bash
# Via SSH
php -m | grep -i pdo
php -m | grep -i mysql

# Deve mostrar:
# PDO
# pdo_mysql
```

---

## 🗄️ PASSO 2: CRIAR BANCO DE DADOS

### Opção A: Via phpMyAdmin (Recomendado)

1. **Acesse phpMyAdmin:**
   ```
   https://seu_dominio.com/phpmyadmin
   ```

2. **Clique em "Novo"** no menu esquerdo

3. **Preencha:**
   - Nome do banco: `igreja_membros`
   - Charset: `utf8mb4`
   - Collation: `utf8mb4_unicode_ci`

4. **Clique em "Criar"**

5. **Selecione o banco criado**

6. **Vá para aba "SQL"**

7. **Cole o conteúdo do arquivo `setup-mysql.sql`**

8. **Clique em "Executar"**

### Opção B: Via SSH

```bash
# Conectar ao MySQL
mysql -u root -p

# Executar script
mysql -u root -p < setup-mysql.sql

# Ou dentro do MySQL:
source /caminho/para/setup-mysql.sql;
```

---

## 🔐 PASSO 3: CRIAR USUÁRIO MYSQL (OPCIONAL)

### Via phpMyAdmin

1. **Vá para "Contas de Usuário"**
2. **Clique em "Adicionar Conta de Usuário"**
3. **Preencha:**
   - Nome de usuário: `igreja_user`
   - Host: `localhost`
   - Senha: `sua_senha_segura`
4. **Marque "Criar banco de dados com mesmo nome"**
5. **Clique em "Ir"**

### Via SSH

```bash
mysql -u root -p << EOF
CREATE USER 'igreja_user'@'localhost' IDENTIFIED BY 'sua_senha_segura';
GRANT ALL PRIVILEGES ON igreja_membros.* TO 'igreja_user'@'localhost';
FLUSH PRIVILEGES;
EOF
```

---

## 📝 PASSO 4: CONFIGURAR ARQUIVO PHP

### Editar `config/database.php`

Abra o arquivo e configure com seus dados:

```php
<?php
// HOST (geralmente localhost)
define('DB_HOST', 'localhost');

// NOME DO BANCO
define('DB_NAME', 'igreja_membros');

// USUÁRIO (root ou igreja_user)
define('DB_USER', 'root');

// SENHA
define('DB_PASS', '');

// PORTA (padrão: 3306)
define('DB_PORT', 3306);

// CHARSET
define('DB_CHARSET', 'utf8mb4');
?>
```

### Exemplo com Usuário Específico

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'igreja_membros');
define('DB_USER', 'igreja_user');
define('DB_PASS', 'sua_senha_segura');
define('DB_PORT', 3306);
define('DB_CHARSET', 'utf8mb4');
```

---

## 🚀 PASSO 5: FAZER UPLOAD DOS ARQUIVOS

### Via FTP

1. **Conecte ao servidor**
2. **Crie pasta:** `/public_html/sistema-membros-mysql/`
3. **Faça upload de TODOS os arquivos**
4. **Verifique se `config/database.php` foi atualizado**

### Via Painel de Controle

1. **Acesse File Manager**
2. **Crie pasta:** `sistema-membros-mysql`
3. **Faça upload** do arquivo `.zip`
4. **Descompacte**

---

## 🔧 PASSO 6: CONFIGURAR PERMISSÕES

### Via SSH

```bash
cd /home/seu_usuario/public_html/sistema-membros-mysql

# Permissões básicas
chmod -R 755 .

# Pasta de uploads (escrita)
chmod -R 777 public/uploads/

# Arquivo .htaccess
chmod 644 public/.htaccess
```

### Via FTP

1. **Clique direito em pasta**
2. **Propriedades → Permissões**
3. **Defina 755 para pastas**
4. **Defina 777 para `public/uploads/`**

---

## ✅ PASSO 7: TESTAR A INSTALAÇÃO

### Acessar o Sistema

```
https://seu_dominio.com/sistema-membros-mysql/
```

### Verificar Conexão

Se ver a página com dados de exemplo, está funcionando! ✅

### Se der erro:

1. **Verifique credenciais MySQL** em `config/database.php`
2. **Verifique se banco foi criado** em phpMyAdmin
3. **Verifique permissões** de arquivo
4. **Verifique logs** do PHP

---

## 🔄 MIGRAR DE SQLITE PARA MYSQL

Se já tem dados no SQLite:

### Opção 1: Exportar e Importar Dados

```bash
# Exportar SQLite para CSV
sqlite3 data/membros.db ".mode csv" ".output membros.csv" "SELECT * FROM membros;"

# Depois importar no MySQL via phpMyAdmin
```

### Opção 2: Script de Migração

```php
<?php
// Conectar ao SQLite
$sqlite = new PDO('sqlite:../data/membros.db');

// Conectar ao MySQL
$mysql = new PDO('mysql:host=localhost;dbname=igreja_membros', 'root', '');

// Copiar dados
$stmt = $sqlite->query('SELECT * FROM membros');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Inserir no MySQL
    // ... código de inserção
}
?>
```

---

## 📊 OTIMIZAÇÕES MYSQL

### Ativar Query Cache

Edite `/etc/mysql/my.cnf`:

```ini
[mysqld]
query_cache_type = 1
query_cache_size = 256M
```

### Criar Índices Adicionais

```sql
-- Já inclusos no setup-mysql.sql
-- Mas pode adicionar mais se necessário:

ALTER TABLE membros ADD FULLTEXT INDEX ft_nome (nome_completo);
ALTER TABLE membros ADD INDEX idx_congregacao (congregacao);
ALTER TABLE membros ADD INDEX idx_area (area);
```

### Fazer Backup Automático

```bash
# Cron job para backup diário
0 2 * * * mysqldump -u root -p'senha' igreja_membros > /backup/igreja_$(date +\%Y\%m\%d).sql
```

---

## 🐛 TROUBLESHOOTING

### Erro: "Connection refused"

**Solução:**
1. Verifique se MySQL está rodando
2. Verifique host (localhost vs 127.0.0.1)
3. Verifique porta (padrão: 3306)

### Erro: "Access denied for user"

**Solução:**
1. Verifique usuário e senha em `config/database.php`
2. Verifique permissões do usuário MySQL
3. Crie novo usuário se necessário

### Erro: "Unknown database"

**Solução:**
1. Verifique se banco foi criado
2. Execute `setup-mysql.sql` novamente
3. Verifique nome do banco em `config/database.php`

### Erro: "Table doesn't exist"

**Solução:**
1. Verifique se tabelas foram criadas
2. Execute `setup-mysql.sql` novamente
3. Verifique em phpMyAdmin

### Performance Lenta

**Solução:**
1. Crie índices adicionais
2. Otimize tabelas: `OPTIMIZE TABLE membros;`
3. Aumente `max_connections` em MySQL
4. Verifique logs de erro

---

## 📈 MONITORAR BANCO DE DADOS

### Ver Tamanho do Banco

```sql
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.tables
WHERE table_schema = 'igreja_membros';
```

### Ver Número de Registros

```sql
SELECT COUNT(*) as total FROM membros;
```

### Ver Queries Lentas

```sql
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;
```

---

## 🔒 SEGURANÇA MYSQL

### Proteger Banco de Dados

1. **Use usuário específico** (não root)
2. **Senha forte** (mínimo 12 caracteres)
3. **Restrinja host** (localhost apenas)
4. **Faça backups regulares**
5. **Atualize MySQL regularmente**

### Exemplo de Usuário Seguro

```sql
CREATE USER 'igreja_user'@'localhost' IDENTIFIED BY 'P@ssw0rd!Segura123';
GRANT SELECT, INSERT, UPDATE, DELETE ON igreja_membros.* TO 'igreja_user'@'localhost';
FLUSH PRIVILEGES;
```

---

## 📞 COMPARAÇÃO: SQLite vs MySQL

### Quando usar SQLite:
- ✅ Aplicação pequena (< 1000 membros)
- ✅ Poucos usuários simultâneos
- ✅ Desenvolvimento local
- ✅ Sem necessidade de replicação

### Quando usar MySQL:
- ✅ Aplicação grande (> 10000 membros)
- ✅ Muitos usuários simultâneos
- ✅ Produção
- ✅ Necessidade de replicação/backup
- ✅ Integração com outros sistemas

---

## 🎯 PRÓXIMOS PASSOS

1. ✅ Criar banco de dados
2. ✅ Configurar arquivo PHP
3. ✅ Fazer upload dos arquivos
4. ✅ Testar a instalação
5. ✅ Cadastrar membros
6. ✅ Fazer backups regulares

---

## 📚 RECURSOS ADICIONAIS

- [Documentação MySQL](https://dev.mysql.com/doc/)
- [Documentação PDO](https://www.php.net/manual/pt_BR/book.pdo.php)
- [phpMyAdmin](https://www.phpmyadmin.net/)

---

**Desenvolvido para Igreja de Deus Nascer de Novo**

*Última atualização: Fevereiro 2026*
