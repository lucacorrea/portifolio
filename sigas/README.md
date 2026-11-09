# SIGAS Coari

Sistema institucional da Secretaria Municipal de Assistência Social de Coari/AM.

## Entrada principal

A entrada web permanece na raiz do projeto:

```text
/sigas/index.php
```

Após autenticação, o usuário é direcionado para:

```text
/sigas/portal.php
```

O portal exibe somente os módulos independentes autorizados para o usuário.

## Estrutura principal

```text
sigas/
├── index.php                       # login / entrada pública
├── portal.php                      # portal de módulos
├── sair.php                        # encerramento de sessão
├── bootstrap.php                   # bootstrap interno, bloqueado para acesso HTTP direto
├── prontuario-socioeconomico.php   # prontuário social transversal
├── historico-pessoa.php            # trajetória intersetorial da pessoa
│
├── kit-maternidade/                # módulo independente
├── aluguel-social/                 # módulo independente
├── beneficios-eventuais/           # módulo independente
├── comida-mesa/                    # módulo independente
├── primeiro-emprego/               # módulo independente
├── governanca-acessos/             # governança, usuários e permissões
│
├── api/                            # endpoints HTTP autenticados
├── assets/                         # CSS, JavaScript e imagens públicas
│
├── app/                            # domínio, serviços, repositórios e infraestrutura (protegido)
├── database/                       # schema e migrations (protegido)
├── frontend/                       # layouts e componentes internos (protegido)
├── scripts/                        # scripts CLI / preflight / verify (protegido)
├── tests/                          # testes automatizados (protegido)
├── docs/                           # documentação técnica (protegido)
└── instalacao/                     # utilitários de instalação, bloqueados na produção
```

## Regras arquiteturais

- `pessoas.id` é a identidade central da pessoa no SIGAS.
- Uma mesma pessoa pode possuir várias solicitações de benefícios sem novo cadastro.
- O prontuário socioeconômico é transversal e compartilhado entre os módulos autorizados.
- O ANEXO é fonte de consulta/importação em modo somente leitura.
- Permissões são resolvidas por setor, nível e exceções individuais.
- APIs validam autenticação, autorização e CSRF.
- Migrations e scripts internos não são acessíveis diretamente pela web.

## Segurança de arquivos

O `.htaccess` bloqueia acesso HTTP direto a:

- `app/`
- `database/`
- `frontend/`
- `tests/`
- `docs/`
- `scripts/`
- `instalacao/`
- `storage/`
- `bootstrap.php`
- arquivos `.env`, `.ini`, `.log`, `.sql`, `.bak`, `.md`, `.txt` e `.zip`

Os scripts em `scripts/` continuam disponíveis normalmente pelo terminal/CLI.

## Configuração

Credenciais reais não ficam dentro do repositório. O SIGAS utiliza configuração externa por `.env`, com `SIGAS_ENV_PATH` disponível para execução via CLI quando necessário.

## Deploy

Em produção, atualize o projeto pelo `main`, valide o código e execute migrations somente após os respectivos preflights. Nunca altere migrations já aplicadas em produção; crie uma nova migration idempotente para mudanças futuras.
