<?php

declare(strict_types=1);

class PessoasRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listarBase(array $filters)
    {
        $where = array();
        $params = array();
        $respExpr = pc_responsavel_expr($this->pdo);
        $fotoField = pc_table_has_column($this->pdo, 'solicitantes', 'foto_path') ? 's.foto_path' : 'NULL AS foto_path';

        // Última ajuda efetivamente atribuída à pessoa.
        // A subconsulta usa ROW_NUMBER para evitar N+1: as entregas são ranqueadas
        // uma única vez e somente a mais recente de cada solicitante é ligada à lista.
        $ultimaAjudaFields = 'NULL AS ultima_ajuda_nome, NULL AS ultima_ajuda_data';
        $ultimaAjudaJoin = '';
        $temEstruturaUltimaAjuda =
            pc_table_has_column($this->pdo, 'ajudas_entregas', 'pessoa_id') &&
            pc_table_has_column($this->pdo, 'ajudas_entregas', 'ajuda_tipo_id') &&
            pc_table_has_column($this->pdo, 'ajudas_entregas', 'data_entrega') &&
            pc_table_has_column($this->pdo, 'ajudas_tipos', 'nome');

        if ($temEstruturaUltimaAjuda) {
            $orderHora = pc_table_has_column($this->pdo, 'ajudas_entregas', 'hora_entrega')
                ? "COALESCE(ae.hora_entrega, '00:00:00') DESC,"
                : '';
            $filtroEntregue = pc_table_has_column($this->pdo, 'ajudas_entregas', 'entregue')
                ? "WHERE UPPER(TRIM(COALESCE(ae.entregue, 'Sim'))) IN ('SIM','S')"
                : '';

            $ultimaAjudaFields = "COALESCE(NULLIF(TRIM(uat.nome), ''), 'Ajuda não identificada') AS ultima_ajuda_nome,
                                  ue.data_entrega AS ultima_ajuda_data";
            $ultimaAjudaJoin = "
                LEFT JOIN (
                    SELECT ranked.pessoa_id, ranked.ajuda_tipo_id, ranked.data_entrega
                    FROM (
                        SELECT ae.pessoa_id, ae.ajuda_tipo_id, ae.data_entrega, ae.id,
                               ROW_NUMBER() OVER (
                                   PARTITION BY ae.pessoa_id
                                   ORDER BY ae.data_entrega DESC, {$orderHora} ae.id DESC
                               ) AS rn
                        FROM ajudas_entregas ae
                        {$filtroEntregue}
                    ) ranked
                    WHERE ranked.rn = 1
                ) ue ON ue.pessoa_id = s.id
                LEFT JOIN ajudas_tipos uat ON uat.id = ue.ajuda_tipo_id";
        }

        if (!empty($filters['bairro_id'])) {
            $where[] = 's.bairro_id = :bairro_id';
            $params[':bairro_id'] = (int)$filters['bairro_id'];
        }

        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);
            $digits = pc_only_digits($q);
            $where[] = '(s.nome LIKE :q_nome OR s.cpf LIKE :q_cpf OR s.nis LIKE :q_nis OR s.telefone LIKE :q_tel OR ' . $respExpr . ' LIKE :q_resp OR s.id = :q_id)';
            $params[':q_nome'] = '%' . $q . '%';
            $params[':q_cpf'] = $digits !== '' ? '%' . $digits . '%' : '__SEM_CPF__';
            $params[':q_nis'] = $digits !== '' ? '%' . $digits . '%' : '__SEM_NIS__';
            $params[':q_tel'] = $digits !== '' ? '%' . $digits . '%' : '%' . $q . '%';
            $params[':q_resp'] = '%' . $q . '%';
            $params[':q_id'] = ctype_digit($digits) ? (int)$digits : 0;
        }

        $sql = 'SELECT s.id, s.nome, s.cpf, s.nis, s.telefone, s.bairro_id,
                       COALESCE(b.nome,\'—\') AS bairro_nome,
                       ' . $respExpr . ' AS responsavel_cadastro,
                       s.pbf, s.bpc, s.beneficio_municipal, s.beneficio_estadual,
                       s.renda_familiar, s.renda_mensal_faixa, s.trabalho, s.local_trabalho,
                       ' . $fotoField . ',
                       ' . $ultimaAjudaFields . ',
                       s.created_at
                FROM solicitantes s
                LEFT JOIN bairros b ON b.id = s.bairro_id
                ' . $ultimaAjudaJoin;

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY s.created_at DESC, s.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    }

    public function buscarPorId($id)
    {
        $respExpr = pc_responsavel_expr($this->pdo);
        $sql = 'SELECT s.*, COALESCE(b.nome,\'—\') AS bairro_nome,
                       ' . $respExpr . ' AS responsavel_cadastro,
                       at.nome AS ajuda_tipo_nome,
                       at.categoria AS ajuda_tipo_categoria
                FROM solicitantes s
                LEFT JOIN bairros b ON b.id = s.bairro_id
                LEFT JOIN ajudas_tipos at ON at.id = s.ajuda_tipo_id
                WHERE s.id = :id
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array(':id' => (int)$id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        // Entrega ao frontend a URL já resolvida pelo mesmo critério do editarSolicitante.php.
        $row['foto_url'] = pc_photo_url(isset($row['foto_path']) ? $row['foto_path'] : '');
        return $row;
    }

    public function bairros()
    {
        $stmt = $this->pdo->query('SELECT id, nome FROM bairros ORDER BY nome');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    }

    public function ajudasTipos()
    {
        try {
            $stmt = $this->pdo->query("SELECT id, nome FROM ajudas_tipos WHERE nome IS NOT NULL AND TRIM(nome) <> '' ORDER BY nome");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
        } catch (Throwable $e) {
            return array();
        }
    }

    public function estatisticasLocais()
    {
        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN cpf IS NULL OR TRIM(cpf) = '' THEN 1 ELSE 0 END) AS sem_cpf,
                    SUM(CASE WHEN UPPER(COALESCE(pbf,'')) IN ('SIM','S') THEN 1 ELSE 0 END) AS pbf,
                    SUM(CASE WHEN UPPER(COALESCE(bpc,'')) IN ('SIM','S') THEN 1 ELSE 0 END) AS bpc,
                    SUM(CASE WHEN COALESCE(beneficio_municipal,'') <> '' AND UPPER(COALESCE(beneficio_municipal,'')) NOT IN ('NAO','NÃO','N') THEN 1 ELSE 0 END) AS municipal,
                    SUM(CASE WHEN COALESCE(beneficio_estadual,'') <> '' AND UPPER(COALESCE(beneficio_estadual,'')) NOT IN ('NAO','NÃO','N') THEN 1 ELSE 0 END) AS estadual
                FROM solicitantes";
        $row = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        return $row ?: array('total' => 0, 'sem_cpf' => 0, 'pbf' => 0, 'bpc' => 0, 'municipal' => 0, 'estadual' => 0);
    }
}
