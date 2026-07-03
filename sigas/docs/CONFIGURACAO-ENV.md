# Configuração do ambiente

O arquivo real de ambiente deve ficar em:

```text
configuracao/sigas/conect/.env
```

Ele deve estar fora da pasta pública do site.

## Ordem de localização

O sistema procura o `.env` nesta ordem:

1. variável de servidor `SIGAS_ENV_PATH`;
2. constante PHP `SIGAS_ENV_PATH`;
3. diretório `HOME` da conta de hospedagem;
4. diretório pai da raiz pública;
5. falha segura.

Não existe fallback dentro da pasta pública do SIGAS.

## Variáveis principais

- `APP_NAME`: nome exibido da aplicação.
- `APP_ENV`: ambiente de execução. Em produção, use `production`.
- `APP_DEBUG`: em produção, use `false`.
- `APP_URL`: URL pública do sistema.
- `APP_TIMEZONE`: fuso horário da aplicação.
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_CHARSET`: dados fornecidos pela hospedagem para conexão ao banco.
- `SESSION_NAME`, `SESSION_LIFETIME`, `SESSION_IDLE_TIMEOUT`: configuração de sessão.
- `SESSION_COOKIE_PATH`: caminho do cookie de sessão dentro do domínio.
- `TRUST_PROXY_HEADERS`: habilita confiança em cabeçalhos de proxy somente quando definido como `true`.
- `PRIVATE_BASE_PATH`: raiz privada do SIGAS.
- `SIGAS_IMAGE_PATH`: raiz privada das imagens.
- `SIGAS_DOCUMENT_PATH`: raiz privada dos documentos.
- `SIGAS_LOG_PATH`: raiz privada dos logs.
- `MAX_IMAGE_SIZE`, `MAX_DOCUMENT_SIZE`: limites de tamanho para upload futuro.
- `INSTALLATION_KEY`: chave temporária para criação do primeiro administrador.
- `INSTALLATION_ENABLED`: controla se a instalação inicial está ativa.
- `INSTALLATION_LOCK_PATH`: arquivo privado que bloqueia nova execução do instalador.

## Cuidados

- `.env.example` é apenas modelo e não deve conter credenciais reais.
- `.env` real não deve ser enviado ao repositório.
- Não registre valores do `.env` em logs ou telas de diagnóstico.
- Troque ou remova `INSTALLATION_KEY` após criar o primeiro administrador.
- Depois da criação inicial, defina `INSTALLATION_ENABLED=false` e remova a pasta `instalacao/`.
- Mantenha `APP_DEBUG=false` em produção.
