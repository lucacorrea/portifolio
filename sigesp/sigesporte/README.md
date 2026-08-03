# SIGESP

Demonstração navegável do Sistema Integrado de Gestão Esportiva, construída em PHP 8.2, HTML, CSS e JavaScript modular.

## Modo demonstração

- Todas as páginas são públicas e funcionam sem login real.
- Nenhuma rota demonstrativa consulta banco, inicia sessão ou grava arquivos.
- Dados fictícios ficam centralizados em `app/Demo/`.
- Formulários, exclusões, aprovações, frequência e exportações são simulados no navegador.
- Uploads geram somente um preview local com `URL.createObjectURL()`.
- Links e assets respeitam `APP_BASE_PATH`, inclusive em `/sigesp`.

O modo demo é o padrão quando `DEMO_MODE` não foi definido. Para deixar a intenção explícita, use:

```env
APP_NAME=SIGESP
APP_ENV=demo
APP_DEBUG=false
APP_URL=https://lucascorrea.pro/sigesp
APP_BASE_PATH=/sigesp
DEMO_MODE=true
```

## Execução local

Requer apenas PHP 8.2 com `mbstring`. Composer e MySQL não são necessários para a demonstração.

```bash
php -S localhost:8080 -t public
```

Acesse `http://localhost:8080/login` ou `http://localhost:8080/dashboard`. No ambiente local servido diretamente pela pasta `public/`, deixe `APP_BASE_PATH` vazio.

Não use a raiz do repositório como raiz do servidor PHP embutido: ele não aplica `.htaccess` e poderia expor arquivos privados.

## Validação

```bash
php -l public/index.php
node --check public/assets/js/app.js
```

Depois, percorra login, dashboard, atletas, documentos, eventos, relatórios e configurações. Confirme que não há cookies de sessão, chamadas de banco, uploads de rede ou erros no console.

As instruções completas de publicação estão em `DEPLOY.md`.
