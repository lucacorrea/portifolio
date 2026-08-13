-- Backfill legacy stock rows for products already assigned to a branch/deposit.
-- Existing per-branch stock is preserved by INSERT IGNORE.
INSERT IGNORE INTO estoque_filiais (produto_id, filial_id, quantidade, estoque_minimo)
SELECT p.id, p.filial_id, p.quantidade, p.estoque_minimo
FROM produtos p
JOIN filiais f ON f.id = p.filial_id
WHERE p.filial_id IS NOT NULL AND p.filial_id > 0;

UPDATE estoque_filiais ef
JOIN produtos p ON p.id = ef.produto_id AND p.filial_id = ef.filial_id
SET ef.quantidade = p.quantidade,
    ef.estoque_minimo = COALESCE(NULLIF(ef.estoque_minimo, 0), p.estoque_minimo)
WHERE ef.quantidade <= 0
  AND p.quantidade > 0;

-- Deposito/logistica may still own legacy products without filial_id.
INSERT IGNORE INTO estoque_filiais (produto_id, filial_id, quantidade, estoque_minimo)
SELECT p.id, f.id, p.quantidade, p.estoque_minimo
FROM produtos p
CROSS JOIN filiais f
WHERE COALESCE(p.filial_id, 0) = 0
  AND p.quantidade > 0
  AND (
      LOWER(f.nome) LIKE '%deposito%'
      OR (LOWER(f.nome) LIKE '%dep%' AND LOWER(f.nome) LIKE '%sito%')
      OR LOWER(f.nome) LIKE '%logistica%'
      OR (LOWER(f.nome) LIKE '%log%' AND LOWER(f.nome) LIKE '%stica%')
  );

UPDATE estoque_filiais ef
JOIN produtos p ON p.id = ef.produto_id AND COALESCE(p.filial_id, 0) = 0
JOIN filiais f ON f.id = ef.filial_id
SET ef.quantidade = p.quantidade,
    ef.estoque_minimo = COALESCE(NULLIF(ef.estoque_minimo, 0), p.estoque_minimo)
WHERE ef.quantidade <= 0
  AND p.quantidade > 0
  AND (
      LOWER(f.nome) LIKE '%deposito%'
      OR (LOWER(f.nome) LIKE '%dep%' AND LOWER(f.nome) LIKE '%sito%')
      OR LOWER(f.nome) LIKE '%logistica%'
      OR (LOWER(f.nome) LIKE '%log%' AND LOWER(f.nome) LIKE '%stica%')
  );
