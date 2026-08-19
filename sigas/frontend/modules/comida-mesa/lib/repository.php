<?php

declare(strict_types=1);

final class ComidaMesaModuleRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed>|null */
    public function defaultCompetence(): ?array
    {
        $stmt = $this->pdo->query("SELECT id, ano, mes, status, inicio_entregas, fim_entregas, observacao FROM comida_mesa_competencias WHERE status = 'aberta' ORDER BY ano DESC, mes DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) return $row;
        $stmt = $this->pdo->query('SELECT id, ano, mes, status, inicio_entregas, fim_entregas, observacao FROM comida_mesa_competencias ORDER BY ano DESC, mes DESC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return array<string,int|float> */
    public function dashboardStats(?int $competenceId): array
    {
        $params = [];
        $deliveryWhereDelivered = '1 = 0';
        $deliveryWhereCancelled = '1 = 0';

        if ($competenceId !== null) {
            /*
             * Database::connection() usa PDO::ATTR_EMULATE_PREPARES = false.
             * O MySQL não permite reutilizar o mesmo placeholder nomeado
             * duas vezes em um prepared statement nativo.
             */
            $deliveryWhereDelivered = 'e.competencia_id = :competencia_entregas';
            $deliveryWhereCancelled = 'e.competencia_id = :competencia_canceladas';
            $params['competencia_entregas'] = $competenceId;
            $params['competencia_canceladas'] = $competenceId;
        }

        $sql = "SELECT
            (SELECT COUNT(*) FROM comida_mesa_inscricoes) AS inscricoes,
            (SELECT COUNT(*) FROM comida_mesa_inscricoes WHERE status = 'ativa') AS ativas,
            (SELECT COUNT(*) FROM comida_mesa_inscricoes WHERE status = 'em_analise') AS em_analise,
            (SELECT COUNT(*) FROM comida_mesa_inscricoes WHERE status = 'lista_espera') AS lista_espera,
            (SELECT COUNT(*) FROM comida_mesa_inscricoes WHERE status IN ('suspensa','bloqueada')) AS restricoes,
            (SELECT COUNT(*) FROM comida_mesa_polos WHERE ativo = 1) AS polos_ativos,
            (SELECT COUNT(*) FROM comida_mesa_documentos) AS documentos,
            (SELECT COUNT(*) FROM comida_mesa_historico) AS eventos,
            (SELECT COUNT(*) FROM comida_mesa_entregas e WHERE {$deliveryWhereDelivered} AND e.status = 'entregue') AS entregas,
            (SELECT COUNT(*) FROM comida_mesa_entregas e WHERE {$deliveryWhereCancelled} AND e.status = 'cancelada') AS entregas_canceladas";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $active = (int) ($row['ativas'] ?? 0);
        $delivered = (int) ($row['entregas'] ?? 0);
        $row['aguardando'] = max(0, $active - $delivered);
        $row['execucao_percentual'] = $active > 0 ? round(($delivered / $active) * 100, 1) : 0.0;

        return $row;
    }

    /** @return list<array{label:string,value:int}> */
    public function monthlyDeliveries(int $months = 8): array
    {
        $months = max(3, min(18, $months));
        $sql = "SELECT c.ano, c.mes,
                       SUM(CASE WHEN e.status = 'entregue' THEN 1 ELSE 0 END) AS total
                FROM comida_mesa_competencias c
                LEFT JOIN comida_mesa_entregas e ON e.competencia_id = c.id
                GROUP BY c.id, c.ano, c.mes
                ORDER BY c.ano DESC, c.mes DESC
                LIMIT {$months}";
        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $rows = array_reverse($rows);
        return array_map(static fn(array $row): array => [
            'label' => cm_month_label((int) $row['mes'], (int) $row['ano']),
            'value' => (int) $row['total'],
        ], $rows);
    }

    /** @return list<array{label:string,value:int}> */
    public function programStatusDistribution(): array
    {
        $rows = $this->pdo->query("SELECT status, COUNT(*) total FROM comida_mesa_inscricoes GROUP BY status ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static fn(array $row): array => ['label' => cm_program_label((string) $row['status']), 'value' => (int) $row['total']], $rows);
    }

    /** @return list<array{label:string,value:int}> */
    public function deliveryDistribution(?int $competenceId): array
    {
        if ($competenceId === null) return [];
        $stmt = $this->pdo->prepare("SELECT
            SUM(CASE WHEN e.status = 'entregue' THEN 1 ELSE 0 END) AS entregues,
            SUM(CASE WHEN e.status = 'cancelada' THEN 1 ELSE 0 END) AS canceladas,
            SUM(CASE WHEN i.status = 'ativa' AND e.id IS NULL THEN 1 ELSE 0 END) AS aguardando,
            SUM(CASE WHEN i.status IN ('suspensa','bloqueada') THEN 1 ELSE 0 END) AS bloqueadas
            FROM comida_mesa_inscricoes i
            LEFT JOIN comida_mesa_entregas e ON e.inscricao_id = i.id AND e.competencia_id = :competencia_id");
        $stmt->execute(['competencia_id' => $competenceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            ['label' => 'Recebidas', 'value' => (int) ($row['entregues'] ?? 0)],
            ['label' => 'Aguardando retirada', 'value' => (int) ($row['aguardando'] ?? 0)],
            ['label' => 'Canceladas', 'value' => (int) ($row['canceladas'] ?? 0)],
            ['label' => 'Bloqueadas', 'value' => (int) ($row['bloqueadas'] ?? 0)],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function topPoles(?int $competenceId, int $limit = 8): array
    {
        $limit = max(3, min(20, $limit));
        $deliveryJoin = $competenceId === null
            ? 'LEFT JOIN comida_mesa_entregas e ON 1 = 0'
            : 'LEFT JOIN comida_mesa_entregas e ON e.inscricao_id = i.id AND e.competencia_id = :competencia_id';
        $sql = "SELECT p.id, p.nome, p.ativo, COUNT(i.id) AS familias,
                       SUM(CASE WHEN i.status = 'ativa' THEN 1 ELSE 0 END) AS ativas,
                       SUM(CASE WHEN e.status = 'entregue' THEN 1 ELSE 0 END) AS entregas
                FROM comida_mesa_polos p
                LEFT JOIN comida_mesa_inscricoes i ON i.polo_id = p.id
                {$deliveryJoin}
                GROUP BY p.id, p.nome, p.ativo
                ORDER BY familias DESC, p.nome ASC LIMIT {$limit}";
        $stmt = $this->pdo->prepare($sql);
        if ($competenceId !== null) $stmt->bindValue(':competencia_id', $competenceId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function competences(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['ano'])) { $where[] = 'c.ano = :ano'; $params['ano'] = (int) $filters['ano']; }
        if (!empty($filters['status'])) { $where[] = 'c.status = :status'; $params['status'] = $filters['status']; }
        $sql = 'SELECT c.id, c.ano, c.mes, c.status, c.inicio_entregas, c.fim_entregas, c.observacao, c.criado_em,
                       COUNT(DISTINCT e.id) AS registros_entrega,
                       SUM(CASE WHEN e.status = "entregue" THEN 1 ELSE 0 END) AS entregas,
                       SUM(CASE WHEN e.status = "cancelada" THEN 1 ELSE 0 END) AS canceladas,
                       COUNT(DISTINCT e.polo_id) AS polos_com_entrega
                FROM comida_mesa_competencias c
                LEFT JOIN comida_mesa_entregas e ON e.competencia_id = c.id
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY c.id, c.ano, c.mes, c.status, c.inicio_entregas, c.fim_entregas, c.observacao, c.criado_em
                ORDER BY c.ano DESC, c.mes DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function poles(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (isset($filters['ativo']) && $filters['ativo'] !== '') { $where[] = 'p.ativo = :ativo'; $params['ativo'] = (int) $filters['ativo']; }
        if (!empty($filters['q'])) { $where[] = '(p.nome LIKE :q OR p.endereco LIKE :q)'; $params['q'] = '%' . trim((string) $filters['q']) . '%'; }
        $sql = 'SELECT p.id, p.nome, p.slug, p.endereco, p.ativo, p.criado_em, p.atualizado_em,
                       COUNT(i.id) AS familias,
                       SUM(CASE WHEN i.status = "ativa" THEN 1 ELSE 0 END) AS beneficiarias_ativas
                FROM comida_mesa_polos p
                LEFT JOIN comida_mesa_inscricoes i ON i.polo_id = p.id
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY p.id, p.nome, p.slug, p.endereco, p.ativo, p.criado_em, p.atualizado_em
                ORDER BY p.ativo DESC, p.nome ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function documents(array $filters = [], int $limit = 200): array
    {
        $limit = max(20, min(10000, $limit));
        $where = ['a.ativo = 1'];
        $params = [];
        if (!empty($filters['q'])) {
            $where[] = '(p.nome LIKE :q OR f.codigo LIKE :q OR d.tipo LIKE :q OR d.descricao LIKE :q OR a.nome_original LIKE :q)';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }
        if (!empty($filters['tipo'])) { $where[] = 'd.tipo = :tipo'; $params['tipo'] = $filters['tipo']; }
        if (!empty($filters['polo_id'])) { $where[] = 'i.polo_id = :polo_id'; $params['polo_id'] = (int) $filters['polo_id']; }
        $sql = 'SELECT d.id, d.inscricao_id, d.tipo, d.descricao, d.criado_em,
                       a.nome_original, a.mime_type, a.tamanho,
                       f.codigo AS familia_codigo, p.nome AS responsavel_nome, p.cpf,
                       polo.nome AS polo_nome, u.nome AS enviado_por_nome
                FROM comida_mesa_documentos d
                INNER JOIN arquivos a ON a.id = d.arquivo_id
                INNER JOIN comida_mesa_inscricoes i ON i.id = d.inscricao_id
                INNER JOIN familias f ON f.id = i.familia_id
                INNER JOIN pessoas p ON p.id = f.responsavel_pessoa_id
                LEFT JOIN comida_mesa_polos polo ON polo.id = i.polo_id
                LEFT JOIN usuarios u ON u.id = d.enviado_por
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY d.criado_em DESC, d.id DESC LIMIT ' . $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<string> */
    public function documentTypes(): array
    {
        $rows = $this->pdo->query('SELECT DISTINCT tipo FROM comida_mesa_documentos WHERE tipo IS NOT NULL AND TRIM(tipo) <> "" ORDER BY tipo')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return array_values(array_map('strval', $rows));
    }

    /** @return list<array<string,mixed>> */
    public function histories(array $filters = [], int $limit = 250): array
    {
        $limit = max(20, min(10000, $limit));
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['q'])) {
            $where[] = '(p.nome LIKE :q OR f.codigo LIKE :q OR h.acao LIKE :q OR h.descricao LIKE :q OR u.nome LIKE :q)';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }
        if (!empty($filters['acao'])) { $where[] = 'h.acao = :acao'; $params['acao'] = $filters['acao']; }
        if (!empty($filters['data_inicio'])) { $where[] = 'DATE(h.criado_em) >= :data_inicio'; $params['data_inicio'] = $filters['data_inicio']; }
        if (!empty($filters['data_fim'])) { $where[] = 'DATE(h.criado_em) <= :data_fim'; $params['data_fim'] = $filters['data_fim']; }
        $sql = 'SELECT h.id, h.inscricao_id, h.acao, h.descricao, h.dados_anteriores, h.dados_novos, h.criado_em,
                       f.codigo AS familia_codigo, p.nome AS responsavel_nome, u.nome AS usuario_nome
                FROM comida_mesa_historico h
                INNER JOIN comida_mesa_inscricoes i ON i.id = h.inscricao_id
                INNER JOIN familias f ON f.id = i.familia_id
                INNER JOIN pessoas p ON p.id = f.responsavel_pessoa_id
                LEFT JOIN usuarios u ON u.id = h.usuario_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY h.criado_em DESC, h.id DESC LIMIT ' . $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<string> */
    public function historyActions(): array
    {
        $rows = $this->pdo->query('SELECT DISTINCT acao FROM comida_mesa_historico WHERE acao IS NOT NULL AND TRIM(acao) <> "" ORDER BY acao')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return array_values(array_map('strval', $rows));
    }

    /** @return list<array<string,mixed>> */
    public function registrationsForSelect(int $limit = 1000): array
    {
        $limit = max(50, min(3000, $limit));
        $sql = 'SELECT i.id, f.codigo, p.nome, p.cpf, polo.nome AS polo_nome
                FROM comida_mesa_inscricoes i
                INNER JOIN familias f ON f.id = i.familia_id
                INNER JOIN pessoas p ON p.id = f.responsavel_pessoa_id
                LEFT JOIN comida_mesa_polos polo ON polo.id = i.polo_id
                ORDER BY p.nome ASC LIMIT ' . $limit;
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string,mixed> */
    public function reportOverview(?int $competenceId = null): array
    {
        $stats = $this->dashboardStats($competenceId);
        $status = $this->programStatusDistribution();
        $delivery = $this->deliveryDistribution($competenceId);
        $poles = $this->topPoles($competenceId, 12);

        $zones = $this->pdo->query("SELECT COALESCE(NULLIF(zona,''),'Não informado') label, COUNT(*) value FROM familias GROUP BY COALESCE(NULLIF(zona,''),'Não informado') ORDER BY value DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $districts = $this->pdo->query("SELECT COALESCE(NULLIF(bairro,''),'Não informado') label, COUNT(*) value FROM familias GROUP BY COALESCE(NULLIF(bairro,''),'Não informado') ORDER BY value DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'stats' => $stats,
            'status' => $status,
            'delivery' => $delivery,
            'poles' => $poles,
            'zones' => array_map(static fn(array $r): array => ['label'=>(string)$r['label'],'value'=>(int)$r['value']], $zones),
            'districts' => array_map(static fn(array $r): array => ['label'=>(string)$r['label'],'value'=>(int)$r['value']], $districts),
            'monthly' => $this->monthlyDeliveries(12),
        ];
    }
}
