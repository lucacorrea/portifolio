# 📊 Documentação do Banco de Dados

## Visão Geral

O sistema utiliza **SQLite3** como banco de dados, um sistema leve, confiável e sem necessidade de servidor separado. O banco de dados é armazenado em um arquivo único (`membros.db`) na pasta `data/`.

## Estrutura do Banco de Dados

### Localização
```
/home/ubuntu/sistema-membros-igreja/data/membros.db
```

### Tamanho
- Arquivo único SQLite3
- Sem limite de tamanho (até 281 TB teoricamente)
- Geralmente ocupa poucos MB para milhares de registros

## Tabela: membros

A tabela `membros` armazena todos os dados dos membros da Igreja.

### Campos da Tabela

#### Identificação
| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | INTEGER PRIMARY KEY | ID único do membro (auto-incremento) |
| `data_cadastro` | DATETIME | Data/hora de criação do registro |
| `data_atualizacao` | DATETIME | Data/hora da última atualização |

#### Dados Pessoais
| Campo | Tipo | Descrição |
|-------|------|-----------|
| `nome_completo` | TEXT NOT NULL | Nome completo do membro |
| `data_nascimento` | DATE | Data de nascimento (YYYY-MM-DD) |
| `sexo` | TEXT | M (Masculino) ou F (Feminino) |
| `tipo_sanguineo` | TEXT | Tipo sanguíneo (O+, A+, B+, AB+, etc.) |
| `nacionalidade` | TEXT | Nacionalidade (ex: Brasileira) |
| `naturalidade` | TEXT | Cidade/Estado de nascimento |
| `estado_uf` | TEXT | Estado (UF) de naturalidade |

#### Documentos
| Campo | Tipo | Descrição |
|-------|------|-----------|
| `cpf` | TEXT UNIQUE | CPF (único, sem formatação) |
| `rg` | TEXT | RG |
| `titulo_eleitor` | TEXT | Número do Título de Eleitor |
| `ctp` | TEXT | CTP |
| `cdi` | TEXT | CDI |

#### Profissão e Educação
| Campo | Tipo | Descrição |
|-------|------|-----------|
| `escolaridade` | TEXT | Nível de escolaridade |
| `profissao` | TEXT | Profissão/Ocupação |

#### Filiação
| Campo | Tipo | Descrição |
|-------|------|-----------|
| `filiacao_pai` | TEXT | Nome do pai |
| `filiacao_mae` | TEXT | Nome da mãe |

#### Estado Civil
| Campo | Tipo | Descrição |
|-------|------|-----------|
| `estado_civil` | TEXT | Solteiro, Casado, Divorciado, Viúvo, etc. |
| `conjuge` | TEXT | Nome do cônjuge (se casado) |
| `filhos` | INTEGER | Número de filhos |

#### Endereço Residencial
| Campo | Tipo | Descrição |
|-------|------|-----------|
| `endereco_rua` | TEXT | Nome da rua/avenida |
| `endereco_numero` | TEXT | Número do imóvel |
| `endereco_bairro` | TEXT | Bairro |
| `endereco_cep` | TEXT | CEP (sem formatação) |
| `endereco_cidade` | TEXT | Cidade |
| `endereco_uf` | TEXT | Estado (UF) |
| `telefone` | TEXT | Telefone (sem formatação) |

#### Dados Eclesiásticos
| Campo | Tipo | Descrição |
|-------|------|-----------|
| `tipo_integracao` | TEXT | Batismo, Mudança ou Aclamação |
| `data_integracao` | DATE | Data de integração à membrasia |
| `batismo_aguas` | TEXT | Data do batismo em águas |
| `batismo_espirito_santo` | TEXT | Data do batismo no Espírito Santo |
| `procedencia` | TEXT | Procedência religiosa anterior |
| `congregacao` | TEXT | Congregação/Célula |
| `area` | TEXT | Área de atuação (Administrativa, Educação, etc.) |
| `nucleo` | TEXT | Núcleo (Centro, Norte, Leste, Oeste, Sul, etc.) |

#### Mídia
| Campo | Tipo | Descrição |
|-------|------|-----------|
| `foto_path` | TEXT | Caminho da foto 3x4 do membro |

## Consultas SQL Úteis

### Listar todos os membros
```sql
SELECT * FROM membros ORDER BY nome_completo;
```

### Contar total de membros
```sql
SELECT COUNT(*) as total FROM membros;
```

### Membros por tipo de integração
```sql
SELECT tipo_integracao, COUNT(*) as quantidade 
FROM membros 
GROUP BY tipo_integracao 
ORDER BY quantidade DESC;
```

### Membros por sexo
```sql
SELECT 
    CASE WHEN sexo = 'M' THEN 'Masculino' ELSE 'Feminino' END as sexo,
    COUNT(*) as quantidade 
FROM membros 
GROUP BY sexo;
```

### Membros por estado civil
```sql
SELECT estado_civil, COUNT(*) as quantidade 
FROM membros 
GROUP BY estado_civil 
ORDER BY quantidade DESC;
```

### Membros por faixa etária
```sql
SELECT 
    CASE 
        WHEN (julianday('now') - julianday(data_nascimento))/365.25 < 18 THEN 'Menores'
        WHEN (julianday('now') - julianday(data_nascimento))/365.25 < 30 THEN '18-30'
        WHEN (julianday('now') - julianday(data_nascimento))/365.25 < 50 THEN '30-50'
        ELSE '50+'
    END as faixa_etaria,
    COUNT(*) as quantidade
FROM membros
GROUP BY faixa_etaria;
```

### Buscar membro por CPF
```sql
SELECT * FROM membros WHERE cpf = '12345678901';
```

### Buscar membro por nome (parcial)
```sql
SELECT * FROM membros WHERE nome_completo LIKE '%João%' ORDER BY nome_completo;
```

### Membros cadastrados no último mês
```sql
SELECT * FROM membros 
WHERE date(data_cadastro) >= date('now', '-1 month')
ORDER BY data_cadastro DESC;
```

### Membros por congregação
```sql
SELECT congregacao, COUNT(*) as quantidade 
FROM membros 
GROUP BY congregacao 
ORDER BY quantidade DESC;
```

### Membros por núcleo
```sql
SELECT nucleo, COUNT(*) as quantidade 
FROM membros 
GROUP BY nucleo 
ORDER BY quantidade DESC;
```

## Backup e Restauração

### Fazer Backup
```bash
# Backup simples
cp /home/ubuntu/sistema-membros-igreja/data/membros.db /backup/membros_$(date +%Y%m%d_%H%M%S).db

# Backup com compressão
tar -czf /backup/membros_$(date +%Y%m%d_%H%M%S).tar.gz /home/ubuntu/sistema-membros-igreja/data/membros.db
```

### Restaurar Backup
```bash
# Restaurar arquivo
cp /backup/membros_backup.db /home/ubuntu/sistema-membros-igreja/data/membros.db

# Restaurar de arquivo comprimido
tar -xzf /backup/membros_backup.tar.gz -C /
```

### Exportar para CSV
```bash
sqlite3 /home/ubuntu/sistema-membros-igreja/data/membros.db \
  ".mode csv" \
  ".output membros_export.csv" \
  "SELECT * FROM membros;"
```

### Importar de CSV
```bash
sqlite3 /home/ubuntu/sistema-membros-igreja/data/membros.db \
  ".mode csv" \
  ".import membros_import.csv membros"
```

## Manutenção do Banco de Dados

### Otimizar Banco de Dados
```bash
sqlite3 /home/ubuntu/sistema-membros-igreja/data/membros.db "VACUUM;"
```

### Verificar Integridade
```bash
sqlite3 /home/ubuntu/sistema-membros-igreja/data/membros.db "PRAGMA integrity_check;"
```

### Obter Informações do Banco
```bash
sqlite3 /home/ubuntu/sistema-membros-igreja/data/membros.db "PRAGMA database_list;"
```

### Listar Todas as Tabelas
```bash
sqlite3 /home/ubuntu/sistema-membros-igreja/data/membros.db ".tables"
```

### Visualizar Schema
```bash
sqlite3 /home/ubuntu/sistema-membros-igreja/data/membros.db ".schema"
```

## Permissões de Arquivo

```bash
# Permissões recomendadas
chmod 755 /home/ubuntu/sistema-membros-igreja/data
chmod 644 /home/ubuntu/sistema-membros-igreja/data/membros.db

# Mudar proprietário (se necessário)
chown www-data:www-data /home/ubuntu/sistema-membros-igreja/data/membros.db
```

## Dados de Exemplo

O sistema inclui um script para popular o banco com dados de teste:

```bash
php /home/ubuntu/sistema-membros-igreja/public/seed-database.php
```

Este script adiciona 5 membros de exemplo com dados realistas para teste do sistema.

## Limitações e Considerações

### Vantagens do SQLite
- ✅ Sem necessidade de servidor separado
- ✅ Arquivo único e portável
- ✅ Baixo consumo de recursos
- ✅ Ideal para aplicações pequenas e médias
- ✅ Fácil backup (copiar arquivo)

### Limitações do SQLite
- ⚠️ Não é ideal para mais de 100 usuários simultâneos
- ⚠️ Melhor para leitura que para escrita intensiva
- ⚠️ Sem suporte nativo a replicação

### Quando Migrar para MySQL/PostgreSQL
- Mais de 10.000 registros com acesso frequente
- Múltiplos usuários simultâneos (>50)
- Necessidade de replicação/backup automático
- Integração com outros sistemas

## Segurança

### Proteção de Dados
1. **Backup regular**: Faça backup do banco diariamente
2. **Permissões**: Restrinja acesso ao arquivo `membros.db`
3. **Validação**: Todos os dados são validados antes de inserção
4. **Prepared Statements**: Previne SQL injection
5. **Criptografia**: Considere criptografar dados sensíveis (CPF, etc.)

### Exemplo de Backup Automático (Cron)
```bash
# Adicionar ao crontab (crontab -e)
0 2 * * * cp /home/ubuntu/sistema-membros-igreja/data/membros.db /backup/membros_$(date +\%Y\%m\%d).db
```

## Suporte

Para dúvidas sobre o banco de dados:
1. Verifique permissões de arquivo/pasta
2. Verifique logs do PHP: `/var/log/php-fpm.log`
3. Teste conexão: `php -r "require 'config/database.php'; echo 'OK';"`
4. Verifique integridade: `sqlite3 membros.db "PRAGMA integrity_check;"`

---

**Desenvolvido para Igreja de Deus Nascer de Novo**
