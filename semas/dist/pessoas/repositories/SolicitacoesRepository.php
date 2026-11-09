<?php

declare(strict_types=1);

class SolicitacoesRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listarPorPessoa($solicitanteId)
    {
        try {
            $hasEntregaLink = pc_table_has_column($this->pdo, 'ajudas_entregas', 'solicitacao_id');
            $entregaJoin = '';
            $entregaFields = '0 AS entregas_count, NULL AS data_entrega, NULL AS hora_entrega';
            if ($hasEntregaLink) {
                $entregaJoin = "LEFT JOIN (
                    SELECT solicitacao_id, COUNT(*) AS entregas_count,
                           MAX(data_entrega) AS data_entrega, MAX(hora_entrega) AS hora_entrega
                    FROM ajudas_entregas
                    WHERE solicitacao_id IS NOT NULL AND UPPER(TRIM(entregue)) = 'SIM'
                    GROUP BY solicitacao_id
                ) ent ON ent.solicitacao_id = sol.id";
                $entregaFields = 'COALESCE(ent.entregas_count,0) AS entregas_count, ent.data_entrega, ent.hora_entrega';
            }

            $origemField = pc_table_has_column($this->pdo, 'solicitacoes', 'origem') ? 'sol.origem' : 'NULL AS origem';
            $sql = "SELECT sol.id, sol.solicitante_id, sol.ajuda_tipo_id, sol.resumo_caso,
                           sol.data_solicitacao, sol.status, sol.created_by, {$origemField},
                           at.nome AS ajuda_nome, at.categoria AS ajuda_categoria,
                           {$entregaFields}
                    FROM solicitacoes sol
                    LEFT JOIN ajudas_tipos at ON at.id = sol.ajuda_tipo_id
                    {$entregaJoin}
                    WHERE sol.solicitante_id = :id
                    ORDER BY sol.data_solicitacao DESC, sol.id DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array(':id' => (int)$solicitanteId));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();

            // Carrega todas as atribuições vinculadas em uma única consulta para evitar N+1.
            $atribuicoesPorSolicitacao = array();
            if ($hasEntregaLink && $rows) {
                $ids = array();
                foreach ($rows as $row) {
                    $id = isset($row['id']) ? (int)$row['id'] : 0;
                    if ($id > 0) {
                        $ids[$id] = $id;
                    }
                }
                if ($ids) {
                    $atribuicoesPorSolicitacao = $this->listarAtribuicoesPorSolicitacoes(array_values($ids));
                }
            }

            foreach ($rows as &$row) {
                $id = isset($row['id']) ? (int)$row['id'] : 0;
                $row['atribuicoes'] = ($id > 0 && isset($atribuicoesPorSolicitacao[$id]))
                    ? $atribuicoesPorSolicitacao[$id]
                    : array();
                // Mantém o contador coerente com os detalhes retornados.
                if ($row['atribuicoes']) {
                    $row['entregas_count'] = count($row['atribuicoes']);
                }
            }
            unset($row);

            return $rows;
        } catch (Throwable $e) {
            return array();
        }
    }

    /**
     * @param array<int,int> $solicitacaoIds
     * @return array<int,array<int,array<string,mixed>>>
     */
    private function listarAtribuicoesPorSolicitacoes(array $solicitacaoIds)
    {
        $ids = array();
        foreach ($solicitacaoIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if (!$ids) {
            return array();
        }

        $params = array();
        $placeholders = array();
        $i = 0;
        foreach (array_values($ids) as $id) {
            $key = ':sid' . $i++;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        $origemField = pc_table_has_column($this->pdo, 'ajudas_entregas', 'origem_ajuda')
            ? 'ae.origem_ajuda'
            : 'NULL AS origem_ajuda';
        $origemDetalheField = pc_table_has_column($this->pdo, 'ajudas_entregas', 'origem_ajuda_detalhe')
            ? 'ae.origem_ajuda_detalhe'
            : 'NULL AS origem_ajuda_detalhe';
        $updatedAtField = pc_table_has_column($this->pdo, 'ajudas_entregas', 'updated_at')
            ? 'ae.updated_at'
            : 'NULL AS updated_at';
        $updatedByField = pc_table_has_column($this->pdo, 'ajudas_entregas', 'updated_by')
            ? 'ae.updated_by'
            : 'NULL AS updated_by';

        $sql = "SELECT ae.id,
                       ae.solicitacao_id,
                       ae.ajuda_tipo_id,
                       at.nome AS ajuda_nome,
                       at.categoria AS ajuda_categoria,
                       ae.data_entrega,
                       ae.hora_entrega,
                       ae.quantidade,
                       ae.valor_aplicado,
                       ae.responsavel,
                       ae.observacao,
                       ae.foto_path,
                       ae.foto_mime,
                       ae.entregue,
                       {$origemField},
                       {$origemDetalheField},
                       {$updatedAtField},
                       {$updatedByField}
                  FROM ajudas_entregas ae
             LEFT JOIN ajudas_tipos at ON at.id = ae.ajuda_tipo_id
                 WHERE ae.solicitacao_id IN (" . implode(',', $placeholders) . ")
                   AND UPPER(TRIM(COALESCE(ae.entregue, ''))) = 'SIM'
              ORDER BY ae.data_entrega DESC, ae.hora_entrega DESC, ae.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();

        $map = array();
        foreach ($rows as $row) {
            $solicitacaoId = isset($row['solicitacao_id']) ? (int)$row['solicitacao_id'] : 0;
            if ($solicitacaoId <= 0) {
                continue;
            }
            if (!isset($map[$solicitacaoId])) {
                $map[$solicitacaoId] = array();
            }
            $map[$solicitacaoId][] = $row;
        }

        return $map;
    }

    public function criar($solicitanteId, $ajudaTipoId, $resumo, $usuario)
    {
        $sql = 'INSERT INTO solicitacoes (solicitante_id, ajuda_tipo_id, resumo_caso, data_solicitacao, status, created_by)
                VALUES (:sid, :aid, :resumo, NOW(), \'Aberto\', :usuario)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array(
            ':sid' => (int)$solicitanteId,
            ':aid' => $ajudaTipoId ? (int)$ajudaTipoId : null,
            ':resumo' => trim((string)$resumo) !== '' ? trim((string)$resumo) : null,
            ':usuario' => (string)$usuario,
        ));
        return (int)$this->pdo->lastInsertId();
    }

    public function buscarPorId($id)
    {
        $origemField = pc_table_has_column($this->pdo, 'solicitacoes', 'origem') ? 'origem' : 'NULL AS origem';
        $stmt = $this->pdo->prepare(
            'SELECT id, solicitante_id, ajuda_tipo_id, resumo_caso, data_solicitacao, status, created_by, ' . $origemField . '
             FROM solicitacoes
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(array(':id' => (int)$id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Procura a linha real que representa a solicitação criada no cadastro.
     * Retorna null quando a instalação ainda não possui a coluna origem ou
     * quando o cadastro é legado e ainda não foi materializado em solicitacoes.
     */
    public function buscarInicialCadastroPorPessoa($solicitanteId)
    {
        if (!pc_table_has_column($this->pdo, 'solicitacoes', 'origem')) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, solicitante_id, ajuda_tipo_id, resumo_caso, data_solicitacao, status, created_by, origem
               FROM solicitacoes
              WHERE solicitante_id = :sid
                AND LOWER(COALESCE(origem, '')) LIKE '%cadastro%'
              ORDER BY id ASC
              LIMIT 1"
        );
        $stmt->execute(array(':sid' => (int)$solicitanteId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Localiza, sem criar duplicidade, a linha real que representa a demanda
     * informada no cadastro do solicitante.
     *
     * A busca prioriza origem=cadastro. Para bases legadas, também aceita uma
     * solicitação equivalente por tipo de ajuda, resumo e proximidade da data.
     */
    public function buscarInicialCompativelPorPessoa($solicitanteId, $ajudaTipoId, $resumo, $dataSolicitacao)
    {
        $solicitanteId = (int)$solicitanteId;
        if ($solicitanteId <= 0) {
            return null;
        }

        $hasOrigem = pc_table_has_column($this->pdo, 'solicitacoes', 'origem');
        $origemField = $hasOrigem ? 'origem' : 'NULL AS origem';

        $sql = "SELECT id, solicitante_id, ajuda_tipo_id, resumo_caso, data_solicitacao, status, created_by, {$origemField}
                  FROM solicitacoes
                 WHERE solicitante_id = :sid
                 ORDER BY id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array(':sid' => $solicitanteId));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();

        if (!$rows) {
            return null;
        }

        $normalizar = static function ($valor) {
            $texto = trim((string)$valor);
            if ($texto === '') {
                return '';
            }
            $texto = function_exists('mb_strtolower')
                ? mb_strtolower($texto, 'UTF-8')
                : strtolower($texto);
            $texto = preg_replace('/\\s+/u', ' ', $texto);
            return is_string($texto) ? $texto : '';
        };

        $aidInicial = $ajudaTipoId !== null ? (int)$ajudaTipoId : 0;
        $resumoInicial = $normalizar($resumo);
        $tsInicial = strtotime((string)$dataSolicitacao);

        $compativel = static function (array $row) use ($aidInicial, $resumoInicial, $normalizar) {
            $aidRow = isset($row['ajuda_tipo_id']) ? (int)$row['ajuda_tipo_id'] : 0;
            if ($aidInicial > 0 && $aidRow > 0 && $aidInicial !== $aidRow) {
                return false;
            }

            $resumoRow = $normalizar(isset($row['resumo_caso']) ? $row['resumo_caso'] : '');
            if ($resumoInicial !== '' && $resumoRow !== '' && $resumoInicial !== $resumoRow) {
                return false;
            }

            return true;
        };

        if ($hasOrigem) {
            foreach ($rows as $row) {
                $origem = $normalizar(isset($row['origem']) ? $row['origem'] : '');
                if ($origem === '' || strpos($origem, 'cadastro') === false || strpos($origem, 'duplicada') !== false) {
                    continue;
                }
                if ($compativel($row)) {
                    return $row;
                }
            }
        }

        foreach ($rows as $row) {
            if (!$compativel($row)) {
                continue;
            }

            $resumoRow = $normalizar(isset($row['resumo_caso']) ? $row['resumo_caso'] : '');
            $resumosEquivalentes = ($resumoInicial === $resumoRow)
                || ($resumoInicial === '' && $resumoRow === '');
            if (!$resumosEquivalentes) {
                continue;
            }

            $aidRow = isset($row['ajuda_tipo_id']) ? (int)$row['ajuda_tipo_id'] : 0;
            if ($aidInicial > 0 && $aidRow <= 0) {
                continue;
            }

            $tsRow = !empty($row['data_solicitacao']) ? strtotime((string)$row['data_solicitacao']) : false;
            if ($tsInicial !== false && $tsRow !== false && abs($tsInicial - $tsRow) <= 300) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Materializa uma solicitação inicial legada sem alterar a data original
     * do cadastro da pessoa. A data informada passa a existir exclusivamente
     * em solicitacoes.data_solicitacao.
     */
    public function criarInicialCadastro($solicitanteId, $ajudaTipoId, $resumo, $dataSolicitacao, $status, $usuario)
    {
        $hasOrigem = pc_table_has_column($this->pdo, 'solicitacoes', 'origem');

        if ($hasOrigem) {
            $sql = "INSERT INTO solicitacoes
                        (solicitante_id, ajuda_tipo_id, resumo_caso, data_solicitacao, status, created_by, origem)
                    VALUES
                        (:sid, :aid, :resumo, :data_solicitacao, :status, :usuario, 'cadastro')";
        } else {
            $sql = "INSERT INTO solicitacoes
                        (solicitante_id, ajuda_tipo_id, resumo_caso, data_solicitacao, status, created_by)
                    VALUES
                        (:sid, :aid, :resumo, :data_solicitacao, :status, :usuario)";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array(
            ':sid' => (int)$solicitanteId,
            ':aid' => $ajudaTipoId !== null ? (int)$ajudaTipoId : null,
            ':resumo' => trim((string)$resumo) !== '' ? trim((string)$resumo) : null,
            ':data_solicitacao' => (string)$dataSolicitacao,
            ':status' => (string)$status,
            ':usuario' => (string)$usuario,
        ));

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Atualiza somente a solicitação indicada e confirma também o vínculo com
     * o solicitante para impedir edição por troca manual de ID na requisição.
     */
    public function atualizarCompleta($id, $solicitanteId, $ajudaTipoId, $resumo, $dataSolicitacao, $status)
    {
        $stmt = $this->pdo->prepare(
            'UPDATE solicitacoes
                SET ajuda_tipo_id = :aid,
                    resumo_caso = :resumo,
                    data_solicitacao = :data_solicitacao,
                    status = :status
              WHERE id = :id
                AND solicitante_id = :sid
              LIMIT 1'
        );

        return $stmt->execute(array(
            ':aid' => $ajudaTipoId !== null ? (int)$ajudaTipoId : null,
            ':resumo' => trim((string)$resumo) !== '' ? trim((string)$resumo) : null,
            ':data_solicitacao' => (string)$dataSolicitacao,
            ':status' => (string)$status,
            ':id' => (int)$id,
            ':sid' => (int)$solicitanteId,
        ));
    }

    /**
     * Mantido para compatibilidade com pontos antigos do sistema.
     */
    public function atualizar($id, $status, $resumo)
    {
        $stmt = $this->pdo->prepare('UPDATE solicitacoes SET status = :status, resumo_caso = :resumo WHERE id = :id LIMIT 1');
        return $stmt->execute(array(':status' => $status, ':resumo' => trim((string)$resumo), ':id' => (int)$id));
    }
}
