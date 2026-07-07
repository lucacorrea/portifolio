# Armazenamento privado

Os arquivos enviados pelo SIGAS devem ficar fora da pasta pública.

## Raízes privadas

```text
configuracao/sigas/uplouds/img
configuracao/sigas/uplouds/document
configuracao/sigas/logs
```

Imagens usam `SIGAS_IMAGE_PATH`. Documentos usam `SIGAS_DOCUMENT_PATH`. Logs usam `SIGAS_LOG_PATH`.

## Organização

Os arquivos serão organizados por ano e mês:

```text
2026/07
```

Essa estrutura é gerada por `Storage::buildRelativeDirectory()` e criada somente por `Storage::ensureImageDirectory()` ou `Storage::ensureDocumentDirectory()`.

## Regras de caminho

A classe `Storage` rejeita caminhos com:

- `..`
- byte nulo;
- `://`
- caminhos absolutos;
- letras de unidade;
- resolução fora da raiz privada configurada.

O banco deve armazenar apenas caminho relativo interno, nunca caminho absoluto da hospedagem.

## Permissões

Use `0750` ou `0755`, conforme suporte da hospedagem. Não use `0777`.

## Entrega futura de arquivos

Imagens e documentos privados não devem ser acessados diretamente por URL. A entrega deve ocorrer por controlador PHP autenticado, validando permissão, setor e registro do banco antes de enviar o arquivo.

## Logs

Os logs ficam em pasta privada e são separados por tipo:

- `application.log`
- `security.log`
- `authentication.log`
- `upload.log`

O logger remove valores sensíveis de chaves como senha, password, token, secret, authorization, cookie, db_password e installation_key.
