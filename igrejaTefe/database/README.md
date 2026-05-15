# Banco de dados

SQL inicial do MVP financeiro para igrejas, compatível com MySQL 8+.

## Arquivos

- `init.sql`: ponto de entrada para criar o banco e carregar os scripts.
- `migrations/001_create_core_schema.sql`: tabelas, índices, foreign keys e constraints.
- `migrations/002_enforce_unique_user_email.sql`: garante email globalmente único em bases antigas.
- `seeds/001_categorias_padrao.sql`: categorias padrão para uma igreja existente.
- `seeds/002_usuario_suporte.sql`: usuário suporte por hash de senha informado na execução.

## Executar

A partir da raiz do projeto:

```bash
mysql -u root -p < database/init.sql
```

## Aplicar categorias padrão

O seed depende de uma igreja existente. Troque o ID conforme necessário:

```sql
USE igreja_tefe;
SET @igreja_id := 1;
SOURCE database/seeds/001_categorias_padrao.sql
```

## Criar usuário suporte

Não salve senha em texto puro no repositório. Gere um hash localmente e informe o hash ao seed:

Defina `SUPPORT_PASSWORD` somente na sessão local, execute `php scripts/make-password-hash.php`,
guarde o hash retornado e remova a variável da sessão em seguida.

Depois, no MySQL:

```sql
USE igreja_tefe;
SET @suporte_igreja_id := 1;
SET @suporte_nome := 'Suporte';
SET @suporte_email := 'suporte@igreja.local';
SET @suporte_senha_hash := 'cole-o-hash-gerado-aqui';
SOURCE database/seeds/002_usuario_suporte.sql
```

O seed cria ou atualiza o usuário por email com papel `admin` e `ativo = 1`.

## Decisões importantes

- Todas as tabelas financeiras usam `igreja_id`.
- Email de usuário é globalmente único para permitir login somente por email e senha.
- `entradas` e `saidas` validam o usuário pela dupla `(usuario_id, igreja_id)`.
- `saidas` valida a categoria pela dupla `(categoria_id, igreja_id)`.
- Não há `ON DELETE CASCADE` em dados financeiros.
- Valor financeiro usa `DECIMAL(12,2)`.
- Data futura deve ser bloqueada no backend para manter compatibilidade com MySQL.
- Usuário suporte deve ser criado por hash, nunca com senha em texto puro.
