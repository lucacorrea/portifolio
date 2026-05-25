# L&J Caixa Premium - Demo App-like

Esta versão foi ajustada para parecer mais com um aplicativo mobile real:

- PWA instalável no Android;
- Tela full-screen no celular;
- `manifest.json`;
- `service-worker.js`;
- navegação inferior estilo app;
- UI mobile-first;
- cards, gráficos CSS e interações em JavaScript;
- sem dependência de Flutter.

## Como testar localmente

Abra `index.html` no navegador.

## Como testar como aplicativo no celular

O ideal é subir a pasta para a Hostinger, porque PWA precisa rodar via HTTPS para instalar corretamente.

1. Envie todos os arquivos para uma pasta da hospedagem.
2. Acesse o link pelo Chrome no Android.
3. Toque em `⋮`.
4. Toque em `Adicionar à tela inicial`.
5. Abra pelo ícone criado.

Ao abrir pelo ícone, ele fica com aparência de app, sem barra do navegador.

## Estrutura

```txt
index.html
manifest.json
service-worker.js
assets/
  css/styles.css
  js/app.js
  icons/icon.svg
```

## Próximo passo

Depois da aprovação visual, integrar com API PHP/MySQL usando `fetch()` no `app.js`.
