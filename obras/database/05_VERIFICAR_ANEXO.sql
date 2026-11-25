-- Apenas diagnóstico: não altera tabelas nem registros.
-- Substitua o ID se necessário.
SELECT id, module, record_code, original_name, stored_name,
       size_bytes, uploaded_at, deleted_at
FROM attachments
WHERE id = 1;
