# 🚀 Guia de Instalação - Sistema de Membros

## Requisitos Mínimos

- **PHP 7.4+** (recomendado PHP 8.0+)
- **SQLite3** (geralmente já vem com PHP)
- **Servidor Web** (Apache com mod_rewrite ou Nginx)
- **Navegador moderno** (Chrome, Firefox, Safari, Edge)

## Passo 1: Preparar o Servidor

### No Apache:
```bash
# Habilitar mod_rewrite
sudo a2enmod rewrite

# Reiniciar Apache
sudo systemctl restart apache2
```

### No Nginx:
```bash
# Configurar virtual host para PHP
# Exemplo de configuração em /etc/nginx/sites-available/default
location ~ \.php$ {
    fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    fastcgi_index index.php;
    include fastcgi_params;
}
```

## Passo 2: Copiar Arquivos

```bash
# Copiar projeto para o servidor web
sudo cp -r sistema-membros-igreja /var/www/html/

# Ou para Nginx
sudo cp -r sistema-membros-igreja /usr/share/nginx/html/
```

## Passo 3: Definir Permissões

```bash
# Entrar no diretório
cd /var/www/html/sistema-membros-igreja

# Definir permissões corretas
sudo chmod -R 755 .
sudo chmod -R 777 data/
sudo chmod -R 777 public/uploads/

# Mudar proprietário (se necessário)
sudo chown -R www-data:www-data .
```

## Passo 4: Acessar o Sistema

Abra seu navegador e acesse:

```
http://localhost/sistema-membros-igreja/public/
```

Ou se tiver um domínio configurado:

```
http://seu-dominio.com/sistema-membros-igreja/public/
```

## Primeiro Acesso

1. O banco de dados SQLite será criado automaticamente em `data/membros.db`
2. As tabelas serão criadas na primeira requisição
3. Você pode começar a cadastrar membros imediatamente

## Páginas Disponíveis

### Sistema Principal
- **http://localhost/sistema-membros-igreja/public/** - Interface de gerenciamento
  - Dashboard com estatísticas
  - Listagem de membros
  - Cadastro de novo membro
  - Relatórios

### Dashboard de Estatísticas
- **http://localhost/sistema-membros-igreja/public/dashboard.php** - Visualização interativa de dados
  - Gráficos em tempo real
  - Estatísticas gerais
  - Exportação de relatórios em PDF

## Troubleshooting

### Erro: "Permission denied"
```bash
sudo chmod -R 777 data/
sudo chmod -R 777 public/uploads/
```

### Erro: "Database connection failed"
```bash
# Verificar se a pasta data existe
mkdir -p /var/www/html/sistema-membros-igreja/data
chmod 777 /var/www/html/sistema-membros-igreja/data
```

### Erro: "File upload failed"
```bash
# Verificar permissões da pasta uploads
chmod -R 777 /var/www/html/sistema-membros-igreja/public/uploads/
```

### Gráficos não aparecem
- Verifique conexão com internet (Chart.js é carregado via CDN)
- Verifique console do navegador (F12) para erros
- Limpe cache do navegador (Ctrl+Shift+Delete)

## Backup do Banco de Dados

```bash
# Fazer backup
cp /var/www/html/sistema-membros-igreja/data/membros.db /backup/membros.db.bak

# Restaurar backup
cp /backup/membros.db.bak /var/www/html/sistema-membros-igreja/data/membros.db
```

## Manutenção

### Limpar fotos antigas
```bash
# Remover fotos não utilizadas
find /var/www/html/sistema-membros-igreja/public/uploads/ -type f -mtime +30 -delete
```

### Otimizar banco de dados
```bash
# Via PHP CLI
php -r "
require 'config/database.php';
\$pdo->exec('VACUUM');
echo 'Banco otimizado!';
"
```

## Segurança

1. **Mude as permissões de arquivo** após instalação
2. **Faça backups regulares** do banco de dados
3. **Use HTTPS** em produção
4. **Mantenha PHP atualizado**
5. **Restrinja acesso** via .htaccess se necessário

### Exemplo de .htaccess para restrição:
```apache
<FilesMatch "\.(db|sqlite)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>
```

## Suporte

Para dúvidas ou problemas:
1. Verifique permissões de arquivo/pasta
2. Verifique versão do PHP: `php -v`
3. Verifique extensões: `php -m | grep -i sqlite`
4. Verifique logs do servidor: `/var/log/apache2/error.log`

---

**Desenvolvido para Igreja de Deus Nascer de Novo**
