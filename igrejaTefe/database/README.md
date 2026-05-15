# Banco de dados

SQL inicial do MVP financeiro para igrejas, compatível com MySQL 8+.

## Arquivos

- `init.sql`: ponto de entrada para criar o banco e carregar os scripts.
- `migrations/001_create_core_schema.sql`: tabelas, índices, foreign keys e constraints.
- `seeds/001_categorias_padrao.sql`: categorias padrão para uma igreja existente.

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

## Decisões importantes

- Todas as tabelas financeiras usam `igreja_id`.
- `entradas` e `saidas` validam o usuário pela dupla `(usuario_id, igreja_id)`.
- `saidas` valida a categoria pela dupla `(categoria_id, igreja_id)`.
- Não há `ON DELETE CASCADE` em dados financeiros.
- Valor financeiro usa `DECIMAL(12,2)`.
- Data futura deve ser bloqueada no backend para manter compatibilidade com MySQL.

