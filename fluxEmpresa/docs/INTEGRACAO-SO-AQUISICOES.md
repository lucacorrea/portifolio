# Integração SO — Aquisições

Quando `SO_INTEGRATION_ENABLED=true`, a aprovação reserva um UUID persistente, solicita por HTTPS a aquisição `ESPERANDO_OFICIO` ao SO e só então cria a OS local. O FluxEmpresa nunca acessa o banco do SO.

A chamada usa JSON, `X-Client-Id`, `X-Timestamp`, `X-Nonce`, `X-Idempotency-Key` e `X-Signature`. A assinatura é HMAC SHA-256 de timestamp, nonce, método, caminho e hash do corpo. O SO deve reutilizar a mesma aquisição para a mesma chave de idempotência e devolver `success`, `acquisition_id`, `acquisition_number`, `delivery_code` e `status`.

Configure `SO_API_BASE_URL`, `SO_API_ACQUISITION_PATH`, `SO_API_CLIENT_ID`, `SO_API_SECRET`, timeouts e TLS no ambiente. Em produção a URL precisa ser HTTPS e `SO_API_VERIFY_TLS` deve ser verdadeiro. Para desativar sem afetar a aprovação existente, use `SO_INTEGRATION_ENABLED=false`.

Falhas preservam o UUID, deixam o orçamento sem aprovação e registram a próxima tentativa com backoff de 1, 5, 15, 60 e 360 minutos. Nunca exponha requisições, assinaturas ou respostas internas no navegador.
