# Meu Primeiro Emprego — implementação das fichas e importação

Este pacote mantém a estrutura do módulo original e transforma o fluxo principal em persistência real de dados.

## Fluxo implementado

1. **Triagem social** (`pages/novo-candidato.php`)
   - Reproduz os campos da primeira ficha: identificação, endereço, família, escolaridade, habitação e vulnerabilidades.
   - CPF é validado no servidor quando informado.
   - Os dados alimentam as fichas seguintes.

2. **Visita social / parecer técnico** (`pages/acompanhamentos.php`)
   - Seleciona o candidato já cadastrado.
   - Nome, endereço, bairro e contato são carregados da triagem.
   - Registra informações complementares, parecer técnico, decisão (Deferido/Indeferido/Pendente) e técnico responsável.
   - Mantém histórico de visitas.

3. **Ficha cadastral / local de atuação** (`pages/lotacoes.php`)
   - Busca nome, CPF, telefone, endereço e NIS da primeira ficha.
   - Permite registrar dados escolares, série/período, turno e local de atuação.
   - Foto opcional JPG/PNG/WEBP de até 3 MB.

4. **Importação de planilha** (`pages/importar.php` e rota existente `pages/documentacao.php`)
   - Aceita XLSX e CSV.
   - Reconhece o layout: `NOME`, `DATA NASC.`, `RESPONSAVEL`, `BAIRRO`, `ENDEREÇO`, `TELEFONE`, `CPF`, `IDADE`, `SETOR`.
   - Possui modo **Validar** (sem gravar) e **Importar**.
   - CPFs com zero inicial perdido no Excel são completados à esquerda antes da validação.
   - CPF inconsistente de base legada é preservado em `cpf_informado`, mas não entra como CPF validado.
   - Evita duplicidade por CPF validado e por chave de importação.
   - `SETOR` é gravado como `local_atuacao` na ficha cadastral.

5. **Relatório** (`pages/relatorios.php`)
   - Mantém as colunas da planilha original.
   - Acrescenta parecer/status para gestão.
   - Filtros por pesquisa, status, bairro e setor.
   - Exportação CSV UTF-8 compatível com Excel.

6. **Candidatos** (`pages/candidatos.php`)
   - Pesquisa unificada dos registros manuais e importados.

## Banco de dados

Execute uma vez:

```sql
SOURCE database/001_primeiro_emprego.sql;
```

ou importe o arquivo `database/001_primeiro_emprego.sql` pelo phpMyAdmin.

### Conexão

O módulo tenta primeiro usar um `$GLOBALS['pdo']` já fornecido pelo sistema principal. Se não existir, utiliza `config/database.php`, que lê:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

Nenhuma senha foi gravada no pacote.

## Requisito para XLSX

A importação `.xlsx` usa a extensão PHP `ZipArchive`. Se o servidor não tiver essa extensão, salve a planilha como **CSV UTF-8** e importe normalmente.

## Segurança aplicada

- Queries parametrizadas via PDO.
- `PDO::ATTR_EMULATE_PREPARES = false`.
- Token CSRF nos formulários de gravação.
- Validação de CPF, datas, e-mail, telefone e tamanho/tipo de foto.
- Upload com nome aleatório e bloqueio de execução de scripts no diretório de fotos.
- Importação com transação e controle de duplicidades.

## Observação de integração

O ZIP original é um **módulo de um sistema maior**, pois `menu.php` depende de `navigation/module-menu.php`. Por isso, a estrutura de integração foi preservada; não foi criado um segundo layout, login ou roteador paralelo que poderia conflitar com o sistema principal.
