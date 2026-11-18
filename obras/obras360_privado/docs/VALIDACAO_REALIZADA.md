# OBRAS360 — verificação executada na entrega

## Verificado com execução local

- Arquivos PHP passam na verificação de sintaxe (`php -l`)
- JavaScript passou na verificação de sintaxe do Node
- Duas senhas iguais produzem hashes Argon2id diferentes, e `password_verify` funciona
- O gerador de Excel gera um ZIP OOXML bem formado
- Esquema SQLite foi executado em banco temporário de teste via Python
- Os nove módulos declarados referenciam colunas presentes nas 14 tabelas do esquema
- O servidor PHP forneceu tela de login, apresentação, CSS e JS na subpasta `/obras360`
- Dashboard sem autenticação redirecionou para login
- Cookie de sessão usa o caminho `/obras360/`

## NÃO verificado e obrigatório antes do uso real

- Conexão remota com seu banco na Hostinger; o banco ainda precisa ser confirmado/criado no hPanel
- Instalação completa do MySQL e o formulário web de instalação: este ambiente de teste não tem driver `pdo_mysql`
- Operações de cadastro, fiscalização, upload, estoque, protocolo, auditoria e relatórios contra o MySQL real
- Backup/restore, testes de concorrência, LGPD, permissões administrativas e homologação institucional

A sintaxe aprovada não significa que a aplicação foi testada ponta a ponta. Faça os passos do `INSTALAR_NA_HOSTINGER.md` e os testes do `docs/TESTES.md` com contas de teste antes de carregar dados oficiais.
