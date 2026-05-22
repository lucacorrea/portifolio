# K.Yamaguchi Service — Layout profissional PHP/JS/AJAX

Pacote de telas em PHP puro, JavaScript puro e AJAX para sistema de refrigeração.

## Telas incluídas

- `dashboard.php`
- `clientes.php`
- `ordens-servico.php`
- `orcamentos.php`
- `pecas.php`
- `tipos-servico.php`
- `relatorios.php`
- `notas-fiscais.php`
- `configuracoes.php`
- `tabelas.php` — página padrão de listagem reutilizável

## Recursos incluídos

- Layout mais profissional, corporativo e quadrado.
- Cards e botões sem sombras pesadas.
- Sidebar e topbar com bordas suaves.
- Tabelas responsivas que viram cards no mobile.
- APIs mockadas em PHP retornando JSON.
- Gráficos em Canvas com JavaScript puro.
- Geração de PDF de orçamento sem dependência externa.
- Fluxo de WhatsApp: gera PDF e abre a conversa automaticamente com mensagem e link.
- Endpoint preparado para WhatsApp Business Cloud API via variáveis de ambiente.

## Como rodar localmente

```bash
php -S localhost:8000
```

Acesse:

```txt
http://localhost:8000
```

## WhatsApp e PDF

O botão da tela de Orçamentos chama:

1. `api/gerar_orcamento_pdf.php`
2. `api/enviar_orcamento_whatsapp.php`

Sem token da WhatsApp Business API, o sistema abre o WhatsApp com mensagem pronta e link do PDF.

Para envio automático real de documento pelo WhatsApp Business API, configure no servidor:

```bash
WHATSAPP_CLOUD_TOKEN=seu_token
WHATSAPP_PHONE_NUMBER_ID=seu_phone_number_id
```

Depois o endpoint tenta enviar o PDF como documento pela Cloud API.

## Próxima etapa

Trocar os dados mockados dos arquivos `api/*.php` por consultas MySQL com autenticação, permissões, validação, logs e tratamento de erro.
