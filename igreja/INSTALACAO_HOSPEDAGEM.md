# 🌐 Guia de Instalação em Hospedagem Externa

## Sistema de Membros - Igreja de Deus Nascer de Novo

---

## 📋 Requisitos Mínimos

### Servidor Web
- ✅ PHP 7.4 ou superior
- ✅ Apache ou Nginx
- ✅ Suporte a .htaccess (se usar Apache)

### Banco de Dados
- ✅ SQLite3 (já incluído no PHP)
- OU
- ✅ MySQL 5.7+ (opcional, para melhor performance)

### Permissões
- ✅ Escrita na pasta `data/`
- ✅ Escrita na pasta `public/uploads/`
- ✅ Leitura em todas as pastas

---

## 🚀 PASSO 1: FAZER UPLOAD DOS ARQUIVOS

### Via FTP/SFTP

1. **Conecte ao seu servidor** usando um cliente FTP (FileZilla, WinSCP, etc.)

2. **Crie uma pasta** para o projeto:
   ```
   /public_html/sistema-membros/
   ```

3. **Faça upload de todos os arquivos** da pasta `sistema-membros-igreja/`

4. **Estrutura final deve ser:**
   ```
   /public_html/sistema-membros/
   ├── config/
   ├── includes/
   ├── api/
   ├── public/
   ├── data/
   ├── README.md
   ├── GUIA_COMPLETO.md
   └── ... (outros arquivos)
   ```

### Via Painel de Controle (cPanel, Plesk, etc.)

1. **Acesse o File Manager**
2. **Crie pasta:** `sistema-membros`
3. **Faça upload** do arquivo `.zip` ou `.tar.gz`
4. **Descompacte** usando a opção "Extract"

---

## 🔧 PASSO 2: CONFIGURAR PERMISSÕES

### Via FTP/SFTP

1. **Pasta `data/`:**
   - Permissões: `755` (ou `777` se necessário)
   - Propriedário: seu usuário FTP

2. **Pasta `public/uploads/`:**
   - Permissões: `755` (ou `777` se necessário)
   - Propriedário: seu usuário FTP

3. **Arquivo `data/membros.db`:**
   - Permissões: `644` (ou `666`)

### Via SSH (se disponível)

```bash
# Conectar ao servidor
ssh seu_usuario@seu_dominio.com

# Ir para pasta do projeto
cd public_html/sistema-membros

# Configurar permissões
chmod -R 755 .
chmod -R 777 data/
chmod -R 777 public/uploads/
chmod 666 data/membros.db
```

---

## 📝 PASSO 3: CONFIGURAR ARQUIVO .htaccess

Se usar **Apache**, crie arquivo `.htaccess` na raiz do projeto:

**Arquivo:** `/public_html/sistema-membros/.htaccess`

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Redirecionar para public/
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>

# Proteger arquivos sensíveis
<FilesMatch "\.db$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

<FilesMatch "\.php$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Permitir acesso a arquivos específicos
<FilesMatch "^(index|dashboard|ficha-impressao|membros|relatorio|seed-database)\.php$">
    Order Allow,Deny
    Allow from all
</FilesMatch>
```

---

## 🗄️ PASSO 4: CONFIGURAR BANCO DE DADOS

### Opção A: Usar SQLite (Recomendado para começar)

O banco de dados SQLite já vem criado no arquivo `data/membros.db`.

**Não precisa fazer nada!** Basta fazer upload do arquivo.

### Opção B: Usar MySQL (Para melhor performance)

Se sua hospedagem oferece MySQL:

1. **Criar banco de dados:**
   - Acesse phpMyAdmin
   - Crie novo banco: `igreja_membros`
   - Charset: `utf8mb4`

2. **Editar arquivo** `config/database.php`:

```php
<?php
// Comentar a linha do SQLite:
// $pdo = new PDO('sqlite:' . __DIR__ . '/../data/membros.db');

// Descomente e configure para MySQL:
$host = 'localhost';
$db = 'igreja_membros';
$user = 'seu_usuario';
$pass = 'sua_senha';

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>
```

3. **Criar tabelas** - Execute o SQL em phpMyAdmin:

```sql
CREATE TABLE membros (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    nome_completo TEXT NOT NULL,
    data_nascimento DATE,
    nacionalidade TEXT,
    naturalidade TEXT,
    estado_uf TEXT,
    sexo TEXT,
    tipo_sanguineo TEXT,
    escolaridade TEXT,
    profissao TEXT,
    rg TEXT,
    cpf TEXT UNIQUE,
    titulo_eleitor TEXT,
    ctp TEXT,
    cdi TEXT,
    filiacao_pai TEXT,
    filiacao_mae TEXT,
    estado_civil TEXT,
    conjuge TEXT,
    filhos INTEGER DEFAULT 0,
    endereco_rua TEXT,
    endereco_numero TEXT,
    endereco_bairro TEXT,
    endereco_cep TEXT,
    endereco_cidade TEXT,
    endereco_uf TEXT,
    telefone TEXT,
    tipo_integracao TEXT,
    data_integracao DATE,
    batismo_aguas TEXT,
    batismo_espirito_santo TEXT,
    procedencia TEXT,
    congregacao TEXT,
    area TEXT,
    nucleo TEXT,
    foto_path TEXT,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

---

## ✅ PASSO 5: TESTAR A INSTALAÇÃO

### Acessar o Sistema

1. **Abra seu navegador**
2. **Digite a URL:**
   ```
   https://seu_dominio.com/sistema-membros/
   ```

3. **Você deve ver:**
   - Página principal com Dashboard
   - Sidebar com menu
   - Listagem de membros (vazia ou com dados de exemplo)

### Testar Funcionalidades

- ✅ Clique em "Novo Membro"
- ✅ Preencha o formulário
- ✅ Clique em "Salvar Membro"
- ✅ Verifique se o membro aparece na listagem
- ✅ Clique em "Imprimir" para testar ficha
- ✅ Vá ao Dashboard para ver gráficos

---

## 🐛 TROUBLESHOOTING

### Erro: "Permissão negada" ao salvar

**Solução:**
```bash
chmod -R 777 data/
chmod -R 777 public/uploads/
```

### Erro: "Banco de dados não encontrado"

**Solução:**
1. Verifique se arquivo `data/membros.db` existe
2. Verifique permissões da pasta `data/`
3. Se necessário, execute: `php seed-database.php`

### Erro: "Página em branco"

**Solução:**
1. Verifique logs do PHP: `/var/log/php-fpm.log`
2. Ative exibição de erros em `config/database.php`
3. Verifique se PHP 7.4+ está instalado

### Erro: "Arquivo não encontrado" (404)

**Solução:**
1. Verifique se `.htaccess` está configurado
2. Verifique se mod_rewrite está ativado
3. Tente acessar: `https://seu_dominio.com/sistema-membros/public/`

### Erro: "Foto não faz upload"

**Solução:**
1. Verifique permissões de `public/uploads/`
2. Verifique tamanho máximo de upload em `php.ini`
3. Verifique formato do arquivo (JPG, PNG, GIF)

---

## 📊 POPULAR COM DADOS DE EXEMPLO

Se quiser adicionar dados de teste:

1. **Via navegador:**
   ```
   https://seu_dominio.com/sistema-membros/public/seed-database.php
   ```

2. **Via SSH:**
   ```bash
   cd public_html/sistema-membros/public
   php seed-database.php
   ```

Isso adicionará 5 membros de exemplo para teste.

---

## 🔐 SEGURANÇA

### Proteger Arquivos Sensíveis

1. **Criar `.htaccess` na pasta `data/`:**

```apache
<FilesMatch "\.db$">
    Order Allow,Deny
    Deny from all
</FilesMatch>
```

2. **Criar `.htaccess` na pasta `config/`:**

```apache
<FilesMatch "\.php$">
    Order Allow,Deny
    Deny from all
</FilesMatch>
```

### Fazer Backup Regular

```bash
# Via SSH
tar -czf backup_$(date +%Y%m%d).tar.gz public_html/sistema-membros/

# Ou via FTP
# Baixe a pasta inteira regularmente
```

---

## 📈 OTIMIZAÇÃO

### Ativar Compressão GZIP

Adicione ao `.htaccess`:

```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE text/javascript
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/json
</IfModule>
```

### Ativar Cache

Adicione ao `.htaccess`:

```apache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

---

## 🆘 SUPORTE

### Verificar Versão PHP

```bash
# Via SSH
php -v

# Via navegador
# Crie arquivo info.php com:
<?php phpinfo(); ?>
```

### Verificar Extensões Necessárias

```bash
# Via SSH
php -m | grep -E "sqlite|pdo"
```

### Contatar Suporte da Hospedagem

Se tiver problemas:
1. Anote a mensagem de erro exata
2. Verifique logs do servidor
3. Contate o suporte da hospedagem
4. Forneça informações sobre PHP, MySQL, etc.

---

## ✨ PRÓXIMOS PASSOS

Após instalação bem-sucedida:

1. ✅ Cadastre seus membros
2. ✅ Explore o Dashboard
3. ✅ Imprima fichas
4. ✅ Gere relatórios
5. ✅ Faça backup regularmente

---

## 📞 DÚVIDAS FREQUENTES

**P: Qual é a melhor hospedagem?**  
R: Qualquer hospedagem com PHP 7.4+ e permissões de escrita funciona.

**P: Preciso de domínio próprio?**  
R: Não, pode usar o subdomínio da hospedagem.

**P: Posso usar HTTPS?**  
R: Sim! A maioria das hospedagens oferece SSL gratuito.

**P: Quantos membros o sistema suporta?**  
R: Ilimitado (até limites do servidor).

**P: Como faço backup?**  
R: Baixe a pasta inteira via FTP ou use SSH.

---

**Desenvolvido para Igreja de Deus Nascer de Novo**

*Última atualização: Fevereiro 2026*
