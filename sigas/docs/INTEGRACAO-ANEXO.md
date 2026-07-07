# Integração ANEXO

O SIGAS consulta o banco do ANEXO diretamente em modo somente leitura para apoiar a consulta de CPF antes da inscrição no Programa Coari Comida na Mesa.

## Configuração externa

O arquivo real deve ficar fora do repositório e fora do `public_html`:

```text
/home/u784961086/configuracao/anexo/conect/.env
```

O SIGAS procura esse arquivo nesta ordem:

1. `ANEXO_ENV_PATH` em variável de servidor;
2. `ANEXO_ENV_PATH` no ambiente principal do SIGAS;
3. `$HOME/configuracao/anexo/conect/.env`;
4. `dirname($_SERVER['DOCUMENT_ROOT'])/configuracao/anexo/conect/.env`.

Use [ANEXO-ENV.example](ANEXO-ENV.example) como modelo seguro. Não salve credenciais reais no Git.

## Garantias

- A conexão do ANEXO usa uma instância PDO própria.
- O arquivo externo é lido por `App\Integrations\Anexo\AnexoEnvironment`, separado do ambiente principal do SIGAS.
- Apenas variáveis `ANEXO_*` são aceitas.
- Credenciais não são copiadas para `$_ENV`, `putenv`, logs, JavaScript ou banco do SIGAS.
- O repository do ANEXO executa somente `SELECT`.
- Falhas do ANEXO não interrompem o SIGAS; a consulta retorna estado indisponível.

## Superfícies integradas

- Modal `Consultar CPF para inscrição` em `modulo.php`.
- API `api/comida-mesa/consultar-cpf.php`.
- Dashboard com total de pessoas cadastradas no ANEXO quando a integração estiver disponível.

O SIGAS nunca altera registros no banco do ANEXO.
