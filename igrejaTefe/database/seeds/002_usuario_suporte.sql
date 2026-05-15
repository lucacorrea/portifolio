-- Support user seed for an existing igreja.
--
-- Required before running:
--   USE igreja_tefe;
--   SET @suporte_igreja_id := 1;
--   SET @suporte_senha_hash := 'hash generated with PHP password_hash';
--
-- Optional:
--   SET @suporte_nome := 'Suporte';
--   SET @suporte_email := 'suporte@igreja.local';
--
-- This script never stores a plain-text password and is idempotent by
-- (igreja_id, email).

SET @suporte_igreja_id := COALESCE(@suporte_igreja_id, 1);
SET @suporte_nome := COALESCE(NULLIF(@suporte_nome, ''), 'Suporte');
SET @suporte_email := LOWER(COALESCE(NULLIF(@suporte_email, ''), 'suporte@igreja.local'));
SET @suporte_senha_hash := NULLIF(@suporte_senha_hash, '');

SELECT
    CASE
        WHEN @suporte_senha_hash IS NULL THEN 'ERRO: defina @suporte_senha_hash antes de executar o seed.'
        WHEN CHAR_LENGTH(@suporte_senha_hash) < 50 THEN 'ERRO: @suporte_senha_hash parece invalido para password_hash.'
        WHEN NOT EXISTS (SELECT 1 FROM igrejas WHERE id = @suporte_igreja_id) THEN 'ERRO: @suporte_igreja_id nao existe em igrejas.'
        ELSE 'OK: seed de suporte pronto para inserir/atualizar usuario.'
    END AS validacao_seed_suporte;

INSERT INTO usuarios (
    igreja_id,
    nome,
    email,
    senha_hash,
    papel,
    ativo
)
SELECT
    @suporte_igreja_id,
    @suporte_nome,
    @suporte_email,
    @suporte_senha_hash,
    'admin',
    1
WHERE @suporte_senha_hash IS NOT NULL
  AND CHAR_LENGTH(@suporte_senha_hash) >= 50
  AND EXISTS (
      SELECT 1
      FROM igrejas
      WHERE id = @suporte_igreja_id
  )
ON DUPLICATE KEY UPDATE
    nome = @suporte_nome,
    senha_hash = @suporte_senha_hash,
    papel = 'admin',
    ativo = 1,
    atualizado_em = CURRENT_TIMESTAMP;

SELECT id, igreja_id, nome, email, papel, ativo, criado_em, atualizado_em
FROM usuarios
WHERE igreja_id = @suporte_igreja_id
  AND email = @suporte_email;
