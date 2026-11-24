# Cadastro de usuários: correção de 24/11/2026

## Situação anterior

A página `usuarios.php` dependia de um formulário montado dentro de modal JavaScript e enviava à API com `fetch()`. Problemas de execução, validação ou resposta podiam impedir o cadastro sem deixar o erro visível na tela.

## Como funciona agora

1. Abra **Usuários e permissões** e clique em **Novo usuário**.
2. O sistema abre `cadastro-usuario.php`, usando o mesmo cabeçalho, menu e padrão visual das demais páginas.
3. Preencha nome, nome de usuário, e-mail, perfil, senha e confirmação.
4. O navegador envia o formulário diretamente a `actions-user.php`, que utiliza `app/user_management.php`.
5. O PHP valida os dados, grava no MySQL via consultas preparadas, protege a senha com Argon2id e registra auditoria.
6. Se ocorrer um erro, a página informa a causa e preserva os campos preenchidos, exceto senhas. Após salvar, retorna à listagem com mensagem de confirmação.

A mesma função do backend também atende `api.php?action=users.save` para compatibilidade. O cadastro não exige alterações no esquema SQL existente.

## Permissões

- `suporte`: pode criar, editar e administrar todos os perfis, incluindo outras contas de suporte.
- `administrador`: pode criar e editar administradores, gestores, operadores e contas de consulta; não pode conceder, editar nem desativar suporte.
- `gestor`, `operador`, `consulta`: não podem administrar contas.
- O último suporte ativo não pode perder o perfil ou ser desativado.
- Cada usuário recebe senha inicial de no mínimo oito caracteres, com maiúscula, minúscula, número e símbolo, e troca obrigatória no primeiro login.

## Teste após publicar na Hostinger

1. Com uma conta `suporte`, abra `usuarios.php` e clique em Novo usuário.
2. Crie `usuario.teste` com um e-mail novo, perfil `operador` e senha inicial forte.
3. Confira se `usuario.teste` aparece na lista e na tabela `users` do MySQL.
4. Faça logout, entre com `usuario.teste` e confirme a troca de senha obrigatória.
5. Volte como `suporte`, edite o perfil do usuário, desative e reative a conta.
6. Crie um segundo suporte e confirme que a conta pode entrar.
7. Entre com uma conta `administrador` e confirme que não pode criar contas `suporte`.

## Observações

- Não modifique ou reimporte as tabelas SQL apenas para essa correção.
- A correção não publica senhas MySQL; a configuração de conexão continua privada na Hostinger.
- Não foi possível executar integração com o MySQL remoto neste ambiente. O teste final de criação e login deve ser realizado na hospedagem.
