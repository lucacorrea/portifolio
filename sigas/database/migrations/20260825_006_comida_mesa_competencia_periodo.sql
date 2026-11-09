-- SIGAS / Coari Comida na Mesa
-- Correção segura do ciclo mensal atual.
-- Pode ser executado mais de uma vez.

START TRANSACTION;

-- 1) Encerra competências que ficaram administrativamente abertas,
--    mas cujo período de entregas já terminou.
UPDATE comida_mesa_competencias
SET status = 'encerrada'
WHERE status = 'aberta'
  AND fim_entregas IS NOT NULL
  AND fim_entregas < CURDATE();

-- Quantas competências permanecem abertas após encerrar as vencidas.
SET @cm_competencias_abertas := (
    SELECT COUNT(*)
    FROM comida_mesa_competencias
    WHERE status = 'aberta'
);

-- 2) Se ainda não existe competência do mês atual e nenhuma outra está aberta,
--    cria o ciclo atual com o período completo do mês.
INSERT INTO comida_mesa_competencias
    (ano, mes, status, inicio_entregas, fim_entregas, observacao)
SELECT
    YEAR(CURDATE()),
    MONTH(CURDATE()),
    'aberta',
    DATE_FORMAT(CURDATE(), '%Y-%m-01'),
    LAST_DAY(CURDATE()),
    CONCAT('Competência ', DATE_FORMAT(CURDATE(), '%m/%Y'), ' criada pelo ajuste de período.')
WHERE @cm_competencias_abertas = 0
  AND NOT EXISTS (
      SELECT 1
      FROM comida_mesa_competencias
      WHERE ano = YEAR(CURDATE())
        AND mes = MONTH(CURDATE())
  );

-- 3) Se a competência do mês atual já existia como planejada,
--    abre somente quando não havia outra competência aberta.
UPDATE comida_mesa_competencias
SET status = 'aberta',
    inicio_entregas = COALESCE(inicio_entregas, DATE_FORMAT(CURDATE(), '%Y-%m-01')),
    fim_entregas = COALESCE(fim_entregas, LAST_DAY(CURDATE()))
WHERE ano = YEAR(CURDATE())
  AND mes = MONTH(CURDATE())
  AND status = 'planejada'
  AND @cm_competencias_abertas = 0;

-- 4) Corrige datas ausentes caso a competência atual já esteja aberta.
UPDATE comida_mesa_competencias
SET inicio_entregas = COALESCE(inicio_entregas, DATE_FORMAT(CURDATE(), '%Y-%m-01')),
    fim_entregas = COALESCE(fim_entregas, LAST_DAY(CURDATE()))
WHERE ano = YEAR(CURDATE())
  AND mes = MONTH(CURDATE())
  AND status = 'aberta';

COMMIT;

-- Conferência final.
SELECT id, ano, mes, status, inicio_entregas, fim_entregas, observacao
FROM comida_mesa_competencias
ORDER BY ano DESC, mes DESC;
