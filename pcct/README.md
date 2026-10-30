# PCCT — Ensino Médio

Plataforma em **Laravel 9, PHP e MySQL/MariaDB**, com biblioteca de materiais didáticos, área de professores, estudantes e administradores.

## Produção na Hostinger

- URL pública: https://lucascorrea.pro/pcct/
- Banco e usuário MySQL: `u784961086_pcct`
- Exemplo de variáveis de produção: `.env.hostinger.example`

**A senha do MySQL deve ser preenchida somente no arquivo `.env` da hospedagem. Nunca no GitHub.**

1. Na Hostinger, abra a raiz da aplicação Laravel PCCT, onde ficam `artisan`, `config/`, `app/` e `bootstrap/`.
2. No arquivo `.env` **existente** na hospedagem, confira `DB_DATABASE=u784961086_pcct`, `DB_USERNAME=u784961086_pcct`, `DB_HOST` conforme o hPanel e informe a senha real no `DB_PASSWORD`.
3. Confira `APP_URL=https://lucascorrea.pro/pcct`, `APP_ENV=production` e `APP_DEBUG=false`. **Preserve o `APP_KEY` atual**; não substitua todo o `.env` sem verificar.
4. Garanta que o servidor exponha apenas `pcct/public/` e que as rotas e arquivos funcionem em `/pcct/`.
5. Se houver configuração em cache, execute `php artisan config:clear` após alterar o `.env` (não precisa rodar Composer para isso).
6. Na implantação automática, preserve `.env`, `storage/` e `vendor/`. As dependências do Laravel devem estar instaladas no servidor por seu processo de deploy.

**Banco existente:** o SQL inicial já foi importado. Antes de usar as funções novas, confira se os campos complementares da migration `2026_10_30_000000_pcct_ensino_medio.php` estão presentes (por exemplo, `ano_escolar`, `status`, `arquivo` e `area_conhecimento`). Faça backup antes de mudanças estruturais. Não rode `migrate:fresh` em produção.

O arquivo `.env.hostinger.example` é somente um **modelo de configuração**, não muda automaticamente a senha nem a configuração efetiva da Hostinger. O Laravel já lê as credenciais do `.env` em `config/database.php`.

## Desenvolvimento local (opcional)

Use `.env.example` para o ambiente local. Com PHP, MySQL e as dependências do Laravel já instaladas, os comandos de uso são:

```bash
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan pcct:admin
php artisan serve
```

O Composer serve para instalar as dependências em `vendor/` quando ainda não estão disponíveis; não é necessário executá-lo toda vez que o sistema inicia.

## Funções implementadas

- Cadastro de estudantes, login por perfil e painel de usuário.
- Administração de professores, estudantes, instituições, disciplinas e habilidades da BNCC.
- Cadastro, edição, publicação e exclusão de materiais por professor.
- Biblioteca com filtros por texto, série, disciplina, BNCC e tipo de objeto.
- Rascunhos e seus anexos protegidos por autorização.

## Testes automatizados

Com as dependências instaladas e `pdo_sqlite` habilitado, execute `php artisan test --filter=PcctWorkflowTest`. Os testes usam SQLite em memória e não alteram o MySQL da Hostinger.