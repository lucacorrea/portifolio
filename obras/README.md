# OBRAS360 — Sistema de Gestão de Obras e Planejamento · Coari/AM

Aplicação **PHP 8.2+** com autenticação, banco de dados, visual institucional, cadastros reais, dashboard, planejamento, mapa, documentos e exportações. Esta versão foi desenvolvida a partir do protótipo OBRAS360 fornecido anteriormente. **Não tem vínculo institucional homologado com a Prefeitura Municipal de Coari; a identidade é conceitual até autorização oficial.**

## Conteúdo

- Painel com indicadores de obras, orçamento, medições, OS, materiais e gráficos dinâmicos.
- Obras: cadastro, edição, consulta, percentuais, contratos, datas e coordenadas geográficas.
- Ordens de serviço: controle de atendimento e quadro de situação.
- Planejamento: cronograma físico baseado no banco e marcos com cadastro separado.
- Fiscalização: vistorias vinculadas a obras.
- Projetos/documentos: metadados e anexos protegidos, armazenados fora de `public/`.
- Orçamentos/medições: registros financeiros vinculados a obras.
- Materiais/estoque: saldo persistido; entradas e saídas em transações com histórico.
- Prédios públicos: inventário, conservação, coordenadas e mapa.
- Protocolos: abertura, status e tramitação com histórico de encaminhamentos.
- Relatórios: filtros, impressão/salvar como PDF, **CSV** e **Excel XLSX** gerados no servidor.
- Mapa de obras e patrimônio com **Leaflet + OpenStreetMap**, filtros de camadas, links para fichas e **GeoJSON**.
- Usuários e auditoria: `admin`, `gestor`, `tecnico`, `consulta`, contas desativáveis e registro de operações.

## Instalação rápida (SQLite)

Exige PHP 8.2 ou superior e extensões **PDO + pdo_sqlite**, `fileinfo`, `openssl` e suporte a **Argon2id**. Para envio de documentos, habilite uploads PHP. Use terminal na raiz do projeto.

```bash
cp .env.example .env
php bin/setup.php --email=administrador@exemplo.gov.br --name="Administrador"
php -S 127.0.0.1:8000 -t public
```

Abra **http://127.0.0.1:8000**. A instalação exibe **uma única vez** uma senha temporária aleatória no terminal. No primeiro login será obrigatória a alteração. Não há senha padrão embutida.

**Teste com dados fictícios:** em um banco **novo e descartável**, execute em vez do setup normal:

```bash
php bin/setup.php --demo --email=administrador@exemplo.gov.br
```

Este comando importa os dados genéricos do protótipo, além de **coordenadas simuladas** em torno de Coari (não são obras reais). O sistema exibe faixa amarela de demonstração. **Não use a carga `--demo` num banco municipal real.**

## Instalação em MySQL / MariaDB (recomendada para servidor multiusuário)

1. Crie banco MySQL 8+ / MariaDB 10.6+ com `utf8mb4` e usuário restrito. Exemplo:

```sql
CREATE DATABASE obras360 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'obras360_app'@'localhost' IDENTIFIED BY 'SUBSTITUA_POR_SENHA_FORTE';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES
  ON obras360.* TO 'obras360_app'@'localhost';
```

2. Copie `.env.example` para `.env`, configure `DB_DRIVER=mysql`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`. Habilite extensão **pdo_mysql** do PHP.
3. Execute `php bin/setup.php --email=...` (aplica as tabelas de `database/mysql.sql` e cria o primeiro administrador).
4. Aponte **DocumentRoot exclusivamente para `public/`**. Configure HTTPS, backups, e restrinja acesso ao terminal/arquivos internos. O desenvolvimento local aceita `php -S 127.0.0.1:8000 -t public`.

> XAMPP/WAMP: verifique `pdo_mysql` ou `pdo_sqlite` em `php.ini`. **Abrir `index.php` com dois cliques não executa PHP:** é necessário um servidor PHP. Hospedagem compartilhada precisa permitir raiz pública `public/` e acesso a variáveis de ambiente fora dela.

## Docker (opcional, ambiente local)

Crie `.env` e defina `DB_DRIVER=mysql`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` e `MYSQL_ROOT_PASSWORD` (não reutilize exemplos fracos). Em seguida:

```bash
docker compose up -d --build
docker compose exec app php bin/setup.php --email=administrador@exemplo.gov.br
```

A aplicação fica em `http://127.0.0.1:8000`. `docker compose` é uma alternativa de desenvolvimento; a implantação definitiva exige TLS, proxy reverso e políticas da equipe de TI.

## Organização do código

```text
public/
  index.php                 # Único ponto público de entrada (roteamento)
  assets/css/app.css        # Visual, fonte confortável e responsividade
  assets/js/app.js          # Gráficos em canvas, interações e confirmação
  assets/js/maps.js         # Mapa Leaflet e filtros
app/
  Core/                    # PDO, auth, validação, repositório, CSRF, auditoria
  Controllers/             # Regras de cada fluxo HTTP
  Services/                # Anexos, exportações, movimentações
  Views/                   # HTML separado em layouts e telas
config/modules.php          # Metadados dos módulos e formulários
database/mysql.sql          # Tabelas MariaDB/MySQL
database/sqlite.sql         # Tabelas SQLite
database/demo-original.json # Dados fictícios derivados do protótipo
bin/setup.php               # Instalação via CLI e admin inicial
bin/reset-admin.php         # Recuperação administrativa via terminal
storage/                    # Banco SQLite e documentos (fora da pasta pública)
```

## Senhas e segurança

- `password_hash(..., PASSWORD_ARGON2ID)` gera **salt aleatório diferente para cada hash**. Senhas iguais não produzem hashes idênticos. Não há criptografia reversível de senha. Verificação por `password_verify` e atualização do hash por `password_needs_rehash`.
- Senhas com mínimo de 15 caracteres; senha inicial temporária gerada por `random_bytes`, troca obrigatória no primeiro acesso.
- Sessão com cookie `HttpOnly`, `SameSite=Lax`, `Secure` sob HTTPS, regeneração de ID e timeout configurável.
- **CSRF em todas as ações POST**, consultas SQL parametrizadas (PDO), escape de saída HTML e permissões verificadas no servidor.
- Bloqueio temporário após tentativas de login fracassadas, limite adicional por IP, trilha de auditoria administrativa e arquivamento lógico dos registros.
- Anexos com extensão/MIME permitidos e tamanho limitado; arquivos servidos apenas por endpoint autenticado, fora de `public/`.
- CSV com proteção básica contra fórmulas, XLSX exportado como valores de texto/número (sem fórmulas). HTTPS e banco com usuário restrito são obrigatórios em produção.

**Antes de implantação oficial:** realizar revisão de segurança e LGPD, controle de acesso por unidade/setor, política de armazenamento e retenção, testes de concorrência/carga, backup/restore, monitoramento, varredura antimalware de anexos, endurecimento de infraestrutura, homologação técnica e validação dos fluxos administrativos. Esta entrega **não constitui auditoria nem certificação de segurança**.

## Mapa

As coordenadas são informadas nos formulários de obras e prédios públicos. O mapa exibe apenas pontos com latitude/longitude válidas. Requer **acesso à internet** para carregar Leaflet e tiles OSM; o aplicativo e os relatórios permanecem disponíveis sem o mapa. A política de tiles do OpenStreetMap não assegura uso pesado em produção: adote provedor cartográfico aprovado, com capacidade e licenciamento adequados. Mantenha atribuição OSM.

## Exportações

Na página de cada módulo use **CSV** ou **Excel**; na Central de relatórios selecione filtros e use CSV / XLSX / Impressão. A página imprimível permite escolher *Salvar como PDF* no navegador. Para dados geográficos, clique em *Exportar GeoJSON* no mapa. CSV/XLSX têm limite de **20.000 registros por solicitação** e a prévia/impressão limita-se a **200** (consulte a documentação para volume maior).

## Testes e limites de entrega

Consulte `docs/TESTES.md` e `docs/SEGURANCA.md`. Código e consultas foram validados estaticamente, mas a implantação real depende de instalar e testar o driver PDO, servidor, banco, HTTPS e política de acesso no ambiente-alvo. O aplicativo não realiza assinaturas digitais qualificadas, emissão de processos oficiais ou integração com outros sistemas municipais sem conectores adicionais.
