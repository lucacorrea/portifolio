# Escopo de validação desta entrega

- Validação de sintaxe de todos os arquivos PHP (`php -l`).
- Verificação de que a primeira requisição a `/obras/public/` renderiza login.
- Verificação da ausência de dependência `.env` e ausência de senhas MySQL nos
  arquivos versionáveis.
- Teste de correspondência `password_verify` com hash Argon2id do SQL.
- Estrutura SQL revisada e arquivos SQL separados para importação.

**Não foi testado contra o banco Hostinger real.** Neste ambiente não há
PDO MySQL instalado nem acesso autorizado ao banco da conta.
Teste na Hostinger: conexão, login inicial, alteração de senha, cadastros,
relatórios CSV/XLSX/GeoJSON, anexos, permissões e mapa. Até isso ocorrer,
não classifique a instalação como homologada para produção.
