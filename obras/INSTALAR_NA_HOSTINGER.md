# OBRAS360 — Guia completo de publicação na Hostinger

## Antes de publicar

- **Troque a senha MySQL que apareceu na captura de tela da conversa**, pois foi exposta visualmente.
- Verifique no hPanel que o banco **foi realmente criado**. Pela captura, ele ainda estava na janela de criação.
- Não envie senhas no chat, nos arquivos ZIP, no GitHub ou dentro de `public_html`.
- Esta entrega **não acessa nem altera remotamente sua conta Hostinger**. Será necessário enviar e instalar os arquivos.

## 1. Crie banco e usuário MySQL no hPanel

Confirme o nome completo do banco e usuário. Foram preenchidos na captura:

- Banco: `u784961086_obras` (confirme no hPanel)
- Usuário: `u784961086_obras` (confirme no hPanel)
- Host: `localhost` para PHP e MySQL na mesma hospedagem (caso contrário, consulte o host no painel)
- Porta: `3306`

Deixe desmarcada a importação de backup; o instalador criará as tabelas.

## 2. Envie as pastas para a hospedagem

Os arquivos neste ZIP estão organizados assim:

```text
RAIZ_DO_SITE/                         (geralmente domains/lucascorrea.pro/)
├── obras360_privado/                 código, .env, banco, documentos (NÃO público)
│   ├── app/Controllers/              regras HTTP
│   ├── app/Core/                     autenticação, DB e segurança
│   ├── app/Services/                 instalação, arquivos, exportação
│   ├── app/Views/                    HTML e layouts
│   ├── config/                       campos e módulos
│   ├── database/mysql.sql            esquema
│   ├── bin/                           CLI opcional
│   ├── storage/                       anexos privados
│   └── .env                           somente no servidor
└── public_html/
    └── obras360/
        ├── index.php                  entrada da aplicação
        ├── .htaccess
        └── assets/                    CSS, JavaScript e ícones
```

Não coloque `obras360_privado` dentro de `public_html`. Se o caminho do servidor for diferente, ajuste o `require` no `public_html/obras360/index.php`.

## 3. Configure o `.env` (SOMENTE no servidor)

Copie `obras360_privado/.env.hostinger.example` para `obras360_privado/.env` e altere o que for necessário:

```ini
APP_ENV=production
APP_URL=https://lucascorrea.pro/obras360
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=u784961086_obras
DB_USER=u784961086_obras
DB_PASSWORD="COLOQUE_A_SENHA_MYSQL_NOVA_AQUI"
INSTALL_ENABLED=true
INSTALL_TOKEN="CRIE_UMA_CHAVE_ALEATORIA_DE_32_OU_MAIS_CARACTERES"
```

**Gerar token forte** no Windows PowerShell (somente no seu computador):

```powershell
$b = New-Object byte[] 32
[Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($b)
[Convert]::ToHexString($b)
```

Se o PowerShell antigo não tiver `ToHexString`, use `([BitConverter]::ToString($b)).Replace('-', '')`.

Mantenha `.env` fora do diretório público e com permissões restritas. Na instalação em produção, PHP 8.2+, `pdo_mysql`, `fileinfo`, `openssl`, zlib e suporte a `PASSWORD_ARGON2ID` são obrigatórios.

## 4. Instale sem precisar de SSH

1. Abra `https://lucascorrea.pro/obras360/index.php?r=instalar` usando HTTPS.
2. Informe a **chave privada** de `INSTALL_TOKEN`, seu nome e e-mail institucional.
3. Clique **Criar tabelas e administrador**. O instalador cria as 14 tabelas e o administrador inicial (somente se o banco não tiver nenhum usuário).
4. Copie a senha temporária exibida **uma única vez**.
5. **Imediatamente depois, altere** no `.env`:

```ini
INSTALL_ENABLED=false
INSTALL_TOKEN=""
```

6. Abra `https://lucascorrea.pro/obras360/`, entre e altere a senha temporária no primeiro acesso.

O instalador exige HTTPS em produção, uma chave com mais de 32 caracteres, token CSRF e não permite reinicializar quando existe usuário. Se o banco já tem usuário, ele retornará página não encontrada.

### Alternativa com terminal SSH

Na pasta `obras360_privado`:

```sh
php bin/diagnosticar-conexao.php
php bin/setup.php --email=seu_email@dominio.gov.br --name="Administrador"
```

O instalador CLI também gera senha temporária (uma única vez). Após ele terminar, mantenha `INSTALL_ENABLED=false`.

## 5. Rotinas do sistema

- Painel e gráficos: são alimentados pelas tabelas do banco.
- Obras, OS, planejamento, fiscalização, documentos, medições, estoque, patrimônio e protocolos: fichas e cadastros persistentes.
- Movimentação de estoque: entradas/saídas com histórico e bloqueio de saldo negativo.
- Protocolos: tramitação auditável por destino e observação.
- Usuários: criação, perfis de acesso, desativação e redefinição de senha por administrador.
- Mapas: use latitude/longitude em obras e prédios públicos; o mapa Leaflet precisa de internet.
- Relatórios: CSV, Excel XLSX, impressão para PDF e GeoJSON.

## 6. Checklist de homologação antes de colocar dados públicos ou oficiais

- [ ] Credencial MySQL alterada depois de aparecer na captura
- [ ] Banco realmente criado e conexão verificada
- [ ] Pastas privadas fora de `public_html`
- [ ] `.env` configurado sem divulgar segredos
- [ ] Instalação concluída; instalador desativado
- [ ] HTTPS e PHP Argon2id habilitados
- [ ] Login e mudança de senha inicial testados
- [ ] Cadastro, edição, arquivamento e permissões conferidos
- [ ] Estoque e protocolos testados com dois usuários
- [ ] Upload, download privado e relatórios testados
- [ ] Backup automático e restauração ensaiados
- [ ] Aprovação técnica e LGPD por área responsável

### Erros comuns

- `Erro 503`: `.env` ausente, driver MySQL indisponível, usuário/senha ou permissões incorretos.
- `Erro 404 no instalador`: chave desativada ou usuário já criado; confirme `.env`.
- `Erro 403 no instalador`: acesse HTTPS.
- `CSS/JS sem carregar`: confirme os caminhos `public_html/obras360` e a URL.
- `Falta pdo_mysql`: habilite a extensão na versão ativa do PHP no hPanel.
- `O mapa não aparece`: requer conectividade externa com OpenStreetMap e Leaflet.

**Importante:** a aplicação é uma base funcional de gestão, sem integração com procedimentos oficiais, assinaturas eletrônicas ou sistemas municipais. Homologação técnica e testes no servidor de destino ainda são necessários.
