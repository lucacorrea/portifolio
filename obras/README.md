# OBRAS360 — PHP 8.2+ / MySQL, mantendo o layout original

Este pacote adapta o **mesmo protótipo visual enviado**. Os arquivos CSS originais foram preservados. As telas do painel continuam sendo montadas pelo JavaScript original e seus formulários agora enviam dados para uma API PHP autenticada. O banco persiste os registros.

## Instalação na Hostinger (ou Apache com PHP)

1. Tenha PHP **8.2 ou mais recente**, com extensões `pdo_mysql`, `fileinfo`, `openssl` e suporte a **Argon2id**, MySQL 8 / MariaDB 10.4+ e **HTTPS** habilitado. Ajuste `post_max_size` para pelo menos 16M, `upload_max_filesize` para 12M, `memory_limit` recomendado >=128M. Apache e acesso aos arquivos além da raiz pública são recomendados.
2. No **phpMyAdmin**, selecione o banco já existente **`u784961086_obras`**. Importe `database/01_estrutura_e_suporte.sql` para criar as tabelas, sequências e o primeiro usuário de suporte. Não é preciso `CREATE DATABASE`.
3. Se quiser recuperar os **41 registros do protótipo** (dados fictícios, para apresentação), importe também `database/02_dados_ficticios_opcional.sql`. Não use os dados falsos como registros oficiais. Os marcadores do mapa nesses registros têm coordenadas **inventadas e identificadas como demonstração**.
4. Envie os arquivos **de dentro de `public/`** para `public_html/` (sem copiar a pasta `public` dentro dela). Deixe `app/`, `config/` e `storage/` no diretório **pai** de `public_html`, isto é, fora da área publicada pela web. Exemplo:

```
.../seu-dominio/
  public_html/              <-- conteúdo da pasta public/ deste ZIP
    index.php
    acesso.php
    api.php
    assets/
    ...
  app/                      <-- PHP interno (privado)
  config/
    .env                    <-- conexão com o banco (privado)
    modules.json
  storage/
    uploads/                <-- anexos privados (privado)
```

5. O arquivo `config/.env` já contém **o nome, usuário e senha MySQL informados na conversa**, com `DB_HOST=localhost` como suposição inicial. Confirme em **Hostinger → Bancos de dados MySQL** qual é o endereço de servidor de banco de dados e atualize `DB_HOST` se necessário. Não é possível testar uma conexão remota sem o host correto e acesso à hospedagem.
6. Confirme que `storage/uploads/` pode receber arquivos pelo usuário do PHP sem ficar acessível por URL.
7. Acesse **`https://seu-dominio/acesso.php`**. Use usuário `suporte` **ou** e-mail `suporte@obras360.local`, junto da senha inicial em `INSTALACAO_CREDENCIAIS.txt` **(não envie esse TXT para o servidor)**. No primeiro acesso a troca de senha é obrigatória.
8. Acesse **Usuários e permissões** para cadastrar os demais perfis. O menu de perfil permite trocar sua própria senha e encerrar sessão.

**IMPORTANTE:** Não envie `database/`, `INSTALACAO_CREDENCIAIS.txt`, `README.md` ou o código privado para a pasta pública. Na Hostinger alguns planos criam `public_html` diretamente dentro do domínio, conforme a estrutura ilustrada. Caso a estrutura do seu plano seja outra, mantenha a relação `public_html/../app/`, `public_html/../config/` e `public_html/../storage/`.

## Funções realmente conectadas ao banco

- Login com usuário ou e-mail, logout, alteração obrigatória da senha inicial, contas ativas/desativadas e painel de administração de usuários.
- CRUD dos oito módulos: obras, ordens de serviço, fiscalização, projetos/documentos, orçamentos/medições, materiais e estoque, prédios públicos, protocolos/processos.
- Formulários completos, edição e exclusão lógica de registros (auditoria preservada); busca, filtros e ordenação executados na interface com os registros carregados do servidor.
- Movimentações de estoque (`Entrada` e `Saída`) transacionais com bloqueio de linha, conferência de saldo e histórico; a quantidade de material existente é alterada por movimentações, não por edição arbitrária.
- Marcos do planejamento com inclusão, edição e exclusão; encaminhamento de protocolos com histórico de despachos.
- Mapas Leaflet / OpenStreetMap e coordenadas persistidas por registro; apenas cadastros com coordenadas aparecem nos mapas. A base de mapas exige internet. Páginas têm alternativa esquemática se o mapa online falhar.
- Upload de PDF, JPG, PNG, DOC e DOCX até 12 MB, armazenados fora da raiz pública, com listagem, download autenticado e remoção lógica. Outros tipos não são aceitos.
- Relatórios e CSV gerados com dados carregados do banco. Relatórios filtram situação e datas quando os registros têm data de referência. Impressão/PDF usa a função de impressão do navegador.
- Registro de auditoria das alterações e controle de acesso por perfil.

## Perfis

| Perfil | Acesso |
|---|---|
| `suporte` | Tudo, incluindo criação de outros usuários de suporte |
| `administrador` | Tudo, exceto atribuir/alterar a conta de suporte |
| `gestor` | Consultar e alterar os módulos operacionais |
| `operador` | Consultar e alterar os módulos operacionais |
| `consulta` | Apenas consulta e exportação |

Os papéis `gestor` e `operador` usam o mesmo conjunto de permissões nesta versão. Refinar permissões por módulo ou secretaria requer uma política de acesso específica.

## Segurança

- **Argon2id** para senhas (hash não reversível, em vez de criptografia recuperável) com memória de 64 MiB, 3 iterações e 2 threads.
- Senha inicial aleatória no arquivo confidencial local, **nunca no SQL em texto puro**; troca obrigatória no primeiro login. Requer senha nova de pelo menos 16 caracteres com maiúscula, minúscula, número e símbolo.
- Sessões de PHP com cookies HttpOnly, SameSite, Secure via HTTPS, regeneração de ID após login e mudança de senha.
- Tokens de proteção contra CSRF, prepared statements do PDO, validação dos dados no servidor, proteção por perfis e cabeçalhos de segurança.
- Limite de tentativas de login por identificador/IP e bloqueio temporário da conta; auditoria e exclusão lógica.
- Anexos verificados por extensão, tipo MIME e tamanho, salvos com nomes aleatórios e servidos somente via `download.php` após autenticação.
- `.env` contém segredo de conexão e **deve permanecer fora do `public_html`**. Habilite HTTPS/SSL na hospedagem e **troque a senha do banco** se ela tiver sido compartilhada sem proteção.

Não há integração com e-mail transacional, recuperação automática por e-mail, assinatura digital, ou autenticação multifator nesta versão. O código não passou por auditoria independente de segurança nem teste de carga. Recomenda-se homologação, cópias de segurança e teste de permissões antes de operar dados municipais reais.

## Arquivos

```
OBRAS360_PHP_COMPLETO/
├── public/                  páginas PHP públicas + assets CSS/JS originais
│   ├── api.php              API autenticada por sessão e CSRF
│   ├── actions-login.php
│   ├── actions-logout.php
│   ├── actions-password.php
│   ├── download.php
│   ├── acesso.php
│   ├── minha-senha.php
│   ├── usuarios.php / auditoria.php
│   └── demais módulos e formulários .php
├── app/
│   ├── bootstrap.php        conexão, sessão, autorização, CSRF, segurança
│   ├── auth.php             login e hash de senhas
│   └── data.php             módulos, validação, registros, uploads
├── config/
│   ├── .env                 dados de conexão privados
│   ├── .env.example
│   └── modules.json         definição dos campos preservados
├── database/
│   ├── 01_estrutura_e_suporte.sql
│   └── 02_dados_ficticios_opcional.sql
├── storage/uploads/        arquivos privados
└── INSTALACAO_CREDENCIAIS.txt (não publicar)
```

## Solução de problemas

- **Tela branca/erro 500:** confira extensões PHP (`pdo_mysql`, `fileinfo`, Argon2id), logs da hospedagem e `config/.env`. Os detalhes do erro ficam nos logs do servidor, não na tela.
- **Loop https:** configure SSL na hospedagem. `APP_FORCE_HTTPS=0` pode ser usado **apenas para desenvolvimento local**; use `1` para produção.
- **Banco não conecta:** confira DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS e se o banco foi criado na mesma hospedagem.
- **Imagem/anexo falha:** confira `storage/uploads`, permissões da pasta e configurações de upload no `php.ini`.
- **Senha inicial não entra:** confirme que o SQL foi importado somente após selecionar o banco. O `INSERT` do primeiro usuário não substitui uma conta `suporte` já existente.
- **Mapas não carregam:** confira internet, CSP do servidor, CDN Leaflet e disponibilidade dos blocos de mapa OSM.
