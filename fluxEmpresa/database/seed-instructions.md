# Criação do usuário inicial

Por segurança, este repositório não deve conter senha, hash de senha ou credencial pronta.

Para criar o primeiro usuário com perfil global da L&J:

1. Gere um hash localmente usando PHP.
2. Abra o banco `fluxempresa_db`.
3. Insira um usuário na tabela `usuarios` com `empresa_id` nulo e perfil `SUPER_ADMIN`.
4. Use uma senha forte e troque imediatamente se ela for compartilhada durante implantação.

Campos mínimos:

```txt
empresa_id: NULL
nome: Super Admin L&J
email: e-mail administrativo
usuario: login administrativo
senha: hash gerado localmente
perfil: SUPER_ADMIN
ativo: 1
```

Nunca versionar credenciais reais.
