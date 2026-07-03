# Instalação em hospedagem

Este guia considera hospedagem compartilhada com Apache, PHP 8.2 ou superior e MySQL/MariaDB.

## Estrutura esperada

Mantenha os arquivos públicos do SIGAS na pasta pública configurada no painel da hospedagem. As configurações e arquivos enviados devem ficar fora dessa pasta.

Estrutura privada recomendada:

```text
/home/USUARIO/configuracao/sigas/conect/.env
/home/USUARIO/configuracao/sigas/uplouds/img
/home/USUARIO/configuracao/sigas/uplouds/document
/home/USUARIO/configuracao/sigas/logs
```

O nome `uplouds` deve ser mantido.

## Passos

1. Envie a pasta do sistema para o diretório público configurado no domínio ou subdomínio.
2. Crie a estrutura privada `configuracao/sigas` fora da pasta pública.
3. Copie o conteúdo de `.env.example` para `configuracao/sigas/conect/.env`.
4. Ajuste as variáveis do banco, URL, sessão e caminhos privados no `.env` real.
5. Crie as pastas `uplouds/img`, `uplouds/document` e `logs`.
6. Defina permissões de pasta como `0750` ou `0755`, conforme permitido pela hospedagem.
7. Importe `database/schema.sql` pelo phpMyAdmin.
8. Importe `database/seed.sql` pelo phpMyAdmin.
9. Use `database/create-first-admin.php` somente de forma temporária e protegida para criar o primeiro administrador.
10. Remova ou bloqueie o instalador após o uso.
11. Ative HTTPS no domínio ou subdomínio.

## Observações de segurança

- Não crie `.env` dentro da pasta pública.
- Não coloque senha real em arquivos versionados.
- Não deixe `database/`, `app/` ou `tests/` acessíveis pela web.
- Não altere os arquivos HTML, CSS e JavaScript do protótipo nesta etapa.
- O host do banco deve ser o valor informado pela hospedagem.
