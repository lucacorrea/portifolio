-- Diagnóstico do OBRAS360 no phpMyAdmin.
-- Selecione u784961086_obras antes de executar.
-- Não altera dados nem senhas existentes.
SELECT DATABASE() AS banco_selecionado;

SELECT esperada.nome AS tabela_necessaria,
       CASE WHEN t.table_name IS NULL THEN 'AUSENTE' ELSE 'OK' END AS situacao
FROM (
    SELECT 'users' AS nome UNION ALL SELECT 'login_limits' UNION ALL
    SELECT 'sequences' UNION ALL SELECT 'records' UNION ALL
    SELECT 'milestones' UNION ALL SELECT 'stock_movements' UNION ALL
    SELECT 'protocol_forwards' UNION ALL SELECT 'attachments' UNION ALL
    SELECT 'audit_logs'
) esperada
LEFT JOIN information_schema.tables t
       ON t.table_schema = DATABASE() AND t.table_name = esperada.nome
ORDER BY esperada.nome;

-- Execute separadamente APENAS SE a tabela users estiver marcada como OK:
-- SELECT username, email, role, active, must_change_password, failed_attempts,
--        locked_until, CASE WHEN password_hash LIKE '$argon2id$%' THEN 'ARGON2ID' ELSE 'OUTRO' END AS tipo_hash
-- FROM users WHERE username = 'suporte' OR email = 'suporte@obras360.local';
