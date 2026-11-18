# Segurança e homologação — OBRAS360

## Senhas

As senhas não são criptografadas para recuperação. O banco armazena apenas um **hash derivado por Argon2id com salt aleatório interno**, único a cada operação de hash; usar a mesma senha para duas contas resulta em hashes diferentes. Os parâmetros iniciais são 64 MiB / 3 iterações / 1 thread, sujeitos a benchmarking no servidor. Não crie salts manualmente: PHP já gera de forma segura. Validação com `password_verify()` e migração gradual com `password_needs_rehash()`.

## Fluxos protegidos

- Login com senha temporária aleatória e troca forçada. Rate limiting por IP e bloqueio por conta após falhas.
- Cookie de sessão `HttpOnly`, `SameSite=Lax`; `Secure` se HTTPS. Habilite HTTPS obrigatório/HSTS no proxy de produção.
- CSRF via token de sessão, comparação com `hash_equals`.
- Controle de acesso ao backend nas rotas de criação, edição, arquivamento, movimentos de estoque e gestão de usuários.
- PDO com prepared statements, formulários validados e campos permitidos definidos em `config/modules.php`.
- Headers básicos CSP, `X-Frame-Options`, `nosniff`, política de referência e acesso a geolocalização condicionado a permissão do usuário.
- Arquivos armazenados em `storage/uploads`, nomes aleatórios, download autenticado, limite de tamanho, extensão/MIME autorizados.
- Auditoria de tentativas de acesso, operações e exportações. Log não é imutável frente a privilégios diretos no banco.

## Prioridades antes de tratar dados administrativos reais

1. Definir papéis por **setor, obra, unidade** e matriz de autorização detalhada; os papéis atuais são globais.
2. Habilitar **MFA para administradores**, login institucional e logs centralizados, caso exigidos pela Prefeitura.
3. Segurança de documentos: **antivírus/CDR**, controle de versões, classificação de sigilo, retenção e varredura de downloads; os uploads atuais usam validação básica de tipo, não inspeção antimalware.
4. Submeter SQL e endpoints a revisão independente, testes OWASP (CSRF/XSS/IDOR/uploads), pentest autorizado, proteção DDoS e política de backups.
5. Mapear dados pessoais e bases legais sob a **LGPD**, formalizar retenção, consentimentos pertinentes e segregação por setores.
6. Implementar trilhas de alterações de campo e fluxos de aprovação com autoridade competente se a emissão for oficial.
7. Testar restauração de backup, replicação (se necessária), desempenho, concorrência transacional e disponibilidade.
8. Validar acesso aos tiles OSM e a substituição por provedor apropriado para produção.

## Restrições explícitas

Esta versão não implementa assinatura ICP-Brasil, certificação de documentos, publicação em diário oficial, integração de licitações, gestão completa de contratos, governança de orçamento público, conexão com GIS corporativo ou publicação externa de protocolos. Dados importados por `--demo` são **fictícios**. Os campos e os relatórios permitem operação administrativa interna e prototipação funcional, mas exigem homologação normativa e operacional antes de uso oficial.
