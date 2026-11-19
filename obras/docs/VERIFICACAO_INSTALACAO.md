# Roteiro de testes depois do deploy

1. Importar SQL 01; confirmar 9 tabelas operacionais (users, login_limits, sequences, records, milestones, stock_movements, protocol_forwards, attachments, audit_logs).
2. Acessar tela de login sem autenticação e tentar abrir `index.php` diretamente: o painel deve redirecionar para login.
3. Entrar com `suporte` ou e-mail alternativo e senha temporária; confirmar exigência de criar senha forte e impossibilidade de entrar com senha antiga depois da troca.
4. Criar um usuário `consulta`; verificar leitura de módulos e bloqueio de cadastro/edição no backend.
5. Criar uma obra marcando coordenadas no mapa; conferir a obra no módulo, no mapa e no banco; editar, anexar PDF e baixar pela ficha.
6. Registrar ordem, vistoria, documento, medição, material, prédio e protocolo. Testar edição, busca, exportação CSV e exclusão.
7. Registrar entrada e saída de material; confirmar histórico e recusa de saída maior que o saldo.
8. Encaminhar protocolo e confirmar histórico de setores e despachos; adicionar, editar e excluir um marco do planejamento.
9. Testar relatório por módulo/situação e filtros de data; imprimir via navegador.
10. Conferir painel de usuários, ativação/desativação, auditoria, logout e expiração de sessão.
11. Tentar acessar `config/.env` e `storage/uploads/` por URL (não devem estar na raiz pública). Confirmar HTTPS e backups automáticos.

A validação em servidor real é necessária. Este pacote teve verificações locais de sintaxe e estrutura, mas não foi possível realizar um teste de conexão ao banco remoto com as informações fornecidas.
