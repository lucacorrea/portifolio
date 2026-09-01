# Bianka Oficina — versão melhorada

Esta versão corrige e melhora o layout anterior com foco em qualidade visual e robustez técnica.

## Melhorias aplicadas
- Ícones em SVG inline (não dependem mais de fonte externa)
- Dashboard redesenhado e muito mais próximo do visual profissional desejado
- Topbar alinhada corretamente
- Sidebar profissional com identidade consistente
- Cards, tabelas e formulários padronizados
- Gráfico de barras e gráfico de distribuição renderizados só com CSS
- Modo claro/escuro salvo no navegador
- Melhor responsividade para notebook, tablet e celular

## Estrutura
- `includes/header.php`
- `includes/sidebar.php`
- `includes/footer.php`
- `includes/ui.php`
- `assets/css/app.css`
- `assets/js/app.js`

## Executar localmente
```bash
php -S localhost:8000
```

Depois acesse:
http://localhost:8000

## Próximo passo recomendado
Conectar com banco MySQL e transformar os módulos em CRUD real com:
- autenticação
- permissões
- CSRF
- validação server-side
- paginação
- filtros reais
