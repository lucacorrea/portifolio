<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use App\Core\Validator;
use App\DTO\ComidaMesaFilter;
use App\DTO\PaginatedResult;
use App\Exceptions\RepositoryException;
use PDO;
use PDOException;
use Throwable;

final class ComidaMesaRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $callback($this);
            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /** @return list<array<string,mixed>> */
    public function listCompetences(): array
    {
        return $this->fetchAll('SELECT id, ano, mes, status, inicio_entregas, fim_entregas, observacao FROM comida_mesa_competencias ORDER BY ano DESC, mes DESC');
    }

    /** @return list<array<string,mixed>> */
    public function listActivePoles(): array
    {
        return $this->fetchAll('SELECT id, nome, slug FROM comida_mesa_polos WHERE ativo = 1 ORDER BY nome');
    }

    /** @return array<string,mixed>|null */
    public function findCompetenceById(int $id): ?array
    {
        return $this->fetchOne('SELECT id, ano, mes, status, inicio_entregas, fim_entregas, observacao FROM comida_mesa_competencias WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    /** @return array<string,mixed>|null */
    public function findCompetenceByMonth(int $year, int $month, ?int $exceptId = null): ?array
    {
        $where = 'ano = :ano AND mes = :mes';
        $params = ['ano' => $year, 'mes' => $month];

        if ($exceptId !== null) {
            $where .= ' AND id <> :except_id';
            $params['except_id'] = $exceptId;
        }

        return $this->fetchOne(
            "SELECT id, ano, mes, status FROM comida_mesa_competencias WHERE {$where} LIMIT 1",
            $params
        );
    }

    /** @return array<string,mixed>|null */
    public function findActivePoleById(int $id): ?array
    {
        return $this->fetchOne('SELECT id, nome, slug FROM comida_mesa_polos WHERE id = :id AND ativo = 1 LIMIT 1', ['id' => $id]);
    }

    /** @return array<string,mixed>|null */
    public function findDefaultCompetence(): ?array
    {
        return $this->fetchOne("SELECT id, ano, mes, status, inicio_entregas, fim_entregas, observacao FROM comida_mesa_competencias WHERE status = 'aberta' ORDER BY ano DESC, mes DESC LIMIT 1")
            ?? $this->fetchOne('SELECT id, ano, mes, status, inicio_entregas, fim_entregas, observacao FROM comida_mesa_competencias ORDER BY ano DESC, mes DESC LIMIT 1');
    }

    /** @return array<string,int> */
    public function getStatistics(?int $competenceId): array
    {
        $statistics = [
            'familias_cadastradas' => $this->count('SELECT COUNT(DISTINCT familia_id) FROM comida_mesa_inscricoes'),
            'beneficiarias_ativas' => $this->count("SELECT COUNT(*) FROM comida_mesa_inscricoes WHERE status = 'ativa'"),
            'em_analise' => $this->count("SELECT COUNT(*) FROM comida_mesa_inscricoes WHERE status = 'em_analise'"),
            'lista_espera' => $this->count("SELECT COUNT(*) FROM comida_mesa_inscricoes WHERE status = 'lista_espera'"),
            'suspensas' => $this->count("SELECT COUNT(*) FROM comida_mesa_inscricoes WHERE status = 'suspensa'"),
            'bloqueadas' => $this->count("SELECT COUNT(*) FROM comida_mesa_inscricoes WHERE status = 'bloqueada'"),
            'polos_ativos' => $this->count('SELECT COUNT(*) FROM comida_mesa_polos WHERE ativo = 1'),
            'entregas_competencia' => 0,
            'aguardando_retirada' => 0,
        ];

        if ($competenceId !== null) {
            $statistics['entregas_competencia'] = $this->count(
                "SELECT COUNT(*) FROM comida_mesa_entregas WHERE competencia_id = :competencia_id AND status = 'entregue'",
                ['competencia_id' => $competenceId]
            );
            $statistics['aguardando_retirada'] = $this->count(
                "SELECT COUNT(*) FROM comida_mesa_inscricoes i
                 WHERE i.status = 'ativa'
                   AND NOT EXISTS (
                       SELECT 1 FROM comida_mesa_entregas e
                       WHERE e.inscricao_id = i.id AND e.competencia_id = :competencia_id AND e.status = 'entregue'
                   )",
                ['competencia_id' => $competenceId]
            );
        }

        return $statistics;
    }

    public function paginate(ComidaMesaFilter $filter): PaginatedResult
    {
        [$where, $params] = $this->filterWhere($filter);
        $deliveryJoin = $this->deliveryJoin($filter->competenceId, 'entrega_competencia_id');

        if ($filter->competenceId !== null) {
            $params['entrega_competencia_id'] = $filter->competenceId;
        }

        try {
            $count = $this->pdo->prepare(
                "SELECT COUNT(*)
                 FROM comida_mesa_inscricoes i
                 INNER JOIN familias f ON f.id = i.familia_id
                 INNER JOIN pessoas p ON p.id = f.responsavel_pessoa_id
                 LEFT JOIN comida_mesa_polos polo ON polo.id = i.polo_id
                 {$deliveryJoin}
                 WHERE {$where}"
            );
            $this->bind($count, $params);
            $count->execute();
            $total = (int) $count->fetchColumn();
            $totalPages = max(1, (int) ceil($total / $filter->perPage));
            $page = min($filter->page, $totalPages);
            $offset = ($page - 1) * $filter->perPage;

            $stmt = $this->pdo->prepare(
                "SELECT i.id AS inscricao_id, f.id AS familia_id, f.codigo AS familia_codigo,
                    p.id AS pessoa_id, p.nome AS responsavel_nome, p.cpf, p.nis, f.zona, f.bairro, f.comunidade,
                    i.polo_id, polo.nome AS polo_nome, i.status AS inscricao_status, i.prioridade, i.data_inscricao,
                    i.atualizado_em, entrega.id AS entrega_id, entrega.status AS entrega_status, entrega.entregue_em AS entrega_data
                 FROM comida_mesa_inscricoes i
                 INNER JOIN familias f ON f.id = i.familia_id
                 INNER JOIN pessoas p ON p.id = f.responsavel_pessoa_id
                 LEFT JOIN comida_mesa_polos polo ON polo.id = i.polo_id
                 {$deliveryJoin}
                 WHERE {$where}
                 ORDER BY CASE i.prioridade WHEN 'alta' THEN 1 WHEN 'normal' THEN 2 WHEN 'baixa' THEN 3 ELSE 4 END, p.nome, i.id
                 LIMIT :limit OFFSET :offset"
            );
            $this->bind($stmt, $params);
            $stmt->bindValue(':limit', $filter->perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return new PaginatedResult($stmt->fetchAll(), $total, $page, $filter->perPage);
        } catch (PDOException $exception) {
            throw $this->fail('paginate', 'Falha ao consultar inscrições.', $exception);
        }
    }

    /** @return array<string,mixed>|null */
    public function findByCpf(string $cpf, ?int $competenceId): ?array
    {
        $deliveryJoin = $this->deliveryJoin($competenceId, 'entrega_competencia_id');
        $params = ['cpf' => Validator::onlyDigits($cpf)];

        if ($competenceId !== null) {
            $params['entrega_competencia_id'] = $competenceId;
        }

        return $this->fetchOne(
            "SELECT p.id AS pessoa_id, p.nome AS responsavel_nome, p.cpf, p.nis, fm.parentesco,
                f.id AS familia_id, f.codigo AS familia_codigo,
                CASE WHEN f.responsavel_pessoa_id = p.id THEN 'responsavel'
                     WHEN fm.pessoa_id IS NOT NULL THEN 'integrante'
                     ELSE 'sem_familia' END AS vinculo_familiar,
                i.id AS inscricao_id, i.status AS inscricao_status, polo.nome AS polo_nome,
                entrega.id AS entrega_id, entrega.status AS entrega_status, entrega.entregue_em AS entrega_data
             FROM pessoas p
             LEFT JOIN familias f ON f.id = (
                SELECT fam.id
                FROM familias fam
                LEFT JOIN familia_membros fmx ON fmx.familia_id = fam.id AND fmx.pessoa_id = p.id
                WHERE fam.responsavel_pessoa_id = p.id OR fmx.pessoa_id = p.id
                ORDER BY CASE WHEN fam.responsavel_pessoa_id = p.id THEN 1 WHEN fam.status = 'ativo' THEN 2 ELSE 3 END, fmx.criado_em DESC, fam.id DESC
                LIMIT 1
             )
             LEFT JOIN familia_membros fm ON fm.familia_id = f.id AND fm.pessoa_id = p.id
             LEFT JOIN comida_mesa_inscricoes i ON i.familia_id = f.id
             LEFT JOIN comida_mesa_polos polo ON polo.id = i.polo_id
             {$deliveryJoin}
             WHERE p.cpf = :cpf
             LIMIT 1",
            $params
        );
    }

    /** @return array<string,mixed>|null */
    public function detail(int $registrationId, bool $includeDocuments, bool $includeHistory): ?array
    {
        $registration = $this->fetchOne(
            "SELECT i.*, f.codigo AS familia_codigo, f.responsavel_pessoa_id, f.zona, f.logradouro, f.numero, f.complemento,
                    f.bairro, f.comunidade, f.ponto_referencia, f.cep, f.quantidade_membros, f.renda_familiar,
                    p.nome, p.cpf, p.nis, p.rg, p.data_nascimento, p.telefone, p.email, polo.nome AS polo_nome
             FROM comida_mesa_inscricoes i
             INNER JOIN familias f ON f.id = i.familia_id
             INNER JOIN pessoas p ON p.id = f.responsavel_pessoa_id
             LEFT JOIN comida_mesa_polos polo ON polo.id = i.polo_id
             WHERE i.id = :id LIMIT 1",
            ['id' => $registrationId]
        );

        if ($registration === null) {
            return null;
        }

        $registration['entregas'] = $this->fetchAll(
            "SELECT e.*, c.ano, c.mes, polo.nome AS polo_nome, u.nome AS operador_nome, uc.nome AS cancelador_nome
             FROM comida_mesa_entregas e
             INNER JOIN comida_mesa_competencias c ON c.id = e.competencia_id
             LEFT JOIN comida_mesa_polos polo ON polo.id = e.polo_id
             LEFT JOIN usuarios u ON u.id = e.entregue_por
             LEFT JOIN usuarios uc ON uc.id = e.cancelada_por
             WHERE e.inscricao_id = :id ORDER BY c.ano DESC, c.mes DESC, e.id DESC",
            ['id' => $registrationId]
        );
        $registration['integrantes'] = $this->fetchAll(
            'SELECT p.id, p.nome, p.cpf, fm.parentesco, fm.responsavel, fm.renda_mensal, fm.criado_em
             FROM familia_membros fm INNER JOIN pessoas p ON p.id = fm.pessoa_id
             WHERE fm.familia_id = :familia_id ORDER BY fm.responsavel DESC, p.nome',
            ['familia_id' => (int) $registration['familia_id']]
        );
        $registration['documentos'] = $includeDocuments ? $this->documentsByRegistration($registrationId) : [];
        $registration['historico'] = $includeHistory ? $this->historyByRegistration($registrationId) : [];

        return $registration;
    }

    /** @return array<string,mixed>|null */
    public function findPersonByCpf(string $cpf): ?array
    {
        return $this->fetchOne('SELECT * FROM pessoas WHERE cpf = :cpf LIMIT 1', ['cpf' => Validator::onlyDigits($cpf)]);
    }

    /** @return array<string,mixed>|null */
    public function findFamilyLinkForPerson(int $personId): ?array
    {
        return $this->fetchOne(
            "SELECT f.*, fm.parentesco,
                CASE WHEN f.responsavel_pessoa_id = :responsavel_id THEN 'responsavel' ELSE 'integrante' END AS vinculo_familiar
             FROM familias f
             LEFT JOIN familia_membros fm ON fm.familia_id = f.id AND fm.pessoa_id = :membro_id
             WHERE f.responsavel_pessoa_id = :where_responsavel_id OR fm.pessoa_id = :where_membro_id
             ORDER BY CASE WHEN f.responsavel_pessoa_id = :order_responsavel_id THEN 1 WHEN f.status = 'ativo' THEN 2 ELSE 3 END, fm.criado_em DESC, f.id DESC
             LIMIT 1",
            [
                'responsavel_id' => $personId,
                'membro_id' => $personId,
                'where_responsavel_id' => $personId,
                'where_membro_id' => $personId,
                'order_responsavel_id' => $personId,
            ]
        );
    }

    public function insertPerson(array $data): int
    {
        $this->execute(
            'INSERT INTO pessoas (nome, cpf, nis, rg, data_nascimento, telefone, email, criado_por, atualizado_por)
             VALUES (:nome, :cpf, :nis, :rg, :data_nascimento, :telefone, :email, :criado_por, :atualizado_por)',
            $data
        );

        return (int) $this->pdo->lastInsertId();
    }

    public function updatePerson(int $id, array $data): void
    {
        $data = $this->only($data, ['nome', 'nis', 'rg', 'data_nascimento', 'telefone', 'email', 'atualizado_por']) + ['id' => $id];
        $this->execute(
            'UPDATE pessoas SET nome = :nome, nis = :nis, rg = :rg, data_nascimento = :data_nascimento, telefone = :telefone,
                email = :email, atualizado_por = :atualizado_por WHERE id = :id',
            $data
        );
    }

    public function insertFamily(array $data): int
    {
        $this->execute(
            'INSERT INTO familias (codigo, responsavel_pessoa_id, zona, logradouro, numero, complemento, bairro, comunidade,
                ponto_referencia, cep, quantidade_membros, renda_familiar, criado_por, atualizado_por)
             VALUES (:codigo, :responsavel_pessoa_id, :zona, :logradouro, :numero, :complemento, :bairro, :comunidade,
                :ponto_referencia, :cep, :quantidade_membros, :renda_familiar, :criado_por, :atualizado_por)',
            $data
        );

        return (int) $this->pdo->lastInsertId();
    }

    public function updateFamily(int $id, array $data): void
    {
        $data = $this->only($data, ['codigo', 'zona', 'logradouro', 'numero', 'complemento', 'bairro', 'comunidade', 'ponto_referencia', 'cep', 'quantidade_membros', 'renda_familiar', 'atualizado_por']) + ['id' => $id];
        $this->execute(
            'UPDATE familias SET codigo = :codigo, zona = :zona, logradouro = :logradouro, numero = :numero, complemento = :complemento,
                bairro = :bairro, comunidade = :comunidade, ponto_referencia = :ponto_referencia, cep = :cep,
                quantidade_membros = :quantidade_membros, renda_familiar = :renda_familiar, atualizado_por = :atualizado_por
             WHERE id = :id',
            $data
        );
    }

    public function insertRegistration(array $data): int
    {
        $this->execute(
            'INSERT INTO comida_mesa_inscricoes (familia_id, polo_id, status, prioridade, data_inscricao, data_aprovacao,
                aprovado_por, motivo_suspensao, observacao, criado_por, atualizado_por)
             VALUES (:familia_id, :polo_id, :status, :prioridade, :data_inscricao, :data_aprovacao,
                :aprovado_por, :motivo_suspensao, :observacao, :criado_por, :atualizado_por)',
            $data
        );

        return (int) $this->pdo->lastInsertId();
    }

    public function updateRegistration(int $id, array $data): void
    {
        $data = $this->only($data, ['polo_id', 'status', 'prioridade', 'data_inscricao', 'data_aprovacao', 'aprovado_por', 'motivo_suspensao', 'observacao', 'atualizado_por']) + ['id' => $id];
        $this->execute(
            'UPDATE comida_mesa_inscricoes SET polo_id = :polo_id, status = :status, prioridade = :prioridade,
                data_inscricao = :data_inscricao, data_aprovacao = :data_aprovacao, aprovado_por = :aprovado_por,
                motivo_suspensao = :motivo_suspensao, observacao = :observacao, atualizado_por = :atualizado_por
             WHERE id = :id',
            $data
        );
    }

    /** @return array<string,mixed>|null */
    public function findRegistrationByFamily(int $familyId): ?array
    {
        return $this->fetchOne('SELECT * FROM comida_mesa_inscricoes WHERE familia_id = :familia_id LIMIT 1', ['familia_id' => $familyId]);
    }

    /** @return array<string,mixed>|null */
    public function findRegistrationById(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM comida_mesa_inscricoes WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function saveCompetence(array $data): int
    {
        if (($data['id'] ?? null) !== null) {
            $data = $this->only($data, ['id', 'mes', 'ano', 'status', 'inicio_entregas', 'fim_entregas', 'observacao']);
            $this->execute(
                'UPDATE comida_mesa_competencias SET mes = :mes, ano = :ano, status = :status, inicio_entregas = :inicio_entregas,
                    fim_entregas = :fim_entregas, observacao = :observacao WHERE id = :id',
                $data
            );

            return (int) $data['id'];
        }

        unset($data['id']);
        $data = $this->only($data, ['mes', 'ano', 'status', 'inicio_entregas', 'fim_entregas', 'observacao', 'criado_por']);
        $this->execute(
            'INSERT INTO comida_mesa_competencias (mes, ano, status, inicio_entregas, fim_entregas, observacao, criado_por)
             VALUES (:mes, :ano, :status, :inicio_entregas, :fim_entregas, :observacao, :criado_por)',
            $data
        );

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function lockRegistrationForDelivery(int $registrationId): ?array
    {
        return $this->fetchOne(
            "SELECT i.*, f.codigo AS familia_codigo, p.nome AS responsavel_nome, polo.ativo AS polo_ativo
             FROM comida_mesa_inscricoes i
             INNER JOIN familias f ON f.id = i.familia_id
             INNER JOIN pessoas p ON p.id = f.responsavel_pessoa_id
             LEFT JOIN comida_mesa_polos polo ON polo.id = i.polo_id
             WHERE i.id = :id LIMIT 1 FOR UPDATE",
            ['id' => $registrationId]
        );
    }

    /** @return array<string,mixed>|null */
    public function lockDelivery(int $registrationId, int $competenceId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM comida_mesa_entregas WHERE inscricao_id = :inscricao_id AND competencia_id = :competencia_id LIMIT 1 FOR UPDATE',
            ['inscricao_id' => $registrationId, 'competencia_id' => $competenceId]
        );
    }

    public function insertDelivery(array $data): int
    {
        $this->execute(
            "INSERT INTO comida_mesa_entregas (inscricao_id, competencia_id, polo_id, recebedor_nome, recebedor_cpf,
                recebedor_parentesco, entregue_por, entregue_em, status, observacao)
             VALUES (:inscricao_id, :competencia_id, :polo_id, :recebedor_nome, :recebedor_cpf,
                :recebedor_parentesco, :entregue_por, :entregue_em, 'entregue', :observacao)",
            $data
        );

        return (int) $this->pdo->lastInsertId();
    }

    public function reactivateDelivery(int $id, array $data): void
    {
        $data['id'] = $id;
        $this->execute(
            "UPDATE comida_mesa_entregas SET polo_id = :polo_id, recebedor_nome = :recebedor_nome, recebedor_cpf = :recebedor_cpf,
                recebedor_parentesco = :recebedor_parentesco, entregue_por = :entregue_por, entregue_em = :entregue_em,
                status = 'entregue', observacao = :observacao, cancelada_por = NULL, cancelada_em = NULL, motivo_cancelamento = NULL
             WHERE id = :id",
            $data
        );
    }

    public function cancelDelivery(int $id, int $userId, string $dateTime, string $reason): void
    {
        $this->execute(
            "UPDATE comida_mesa_entregas SET status = 'cancelada', cancelada_por = :usuario_id, cancelada_em = :cancelada_em,
                motivo_cancelamento = :motivo WHERE id = :id AND status = 'entregue'",
            ['id' => $id, 'usuario_id' => $userId, 'cancelada_em' => $dateTime, 'motivo' => $reason]
        );
    }

    public function insertArchiveAndDocument(array $archive, array $document): int
    {
        $this->execute(
            'INSERT INTO arquivos (usuario_id, setor_id, tipo, finalidade, nome_original, nome_armazenado, caminho_relativo,
                mime_type, extensao, tamanho, hash_arquivo, ativo)
             VALUES (:usuario_id, :setor_id, :tipo, :finalidade, :nome_original, :nome_armazenado, :caminho_relativo,
                :mime_type, :extensao, :tamanho, :hash_arquivo, 1)',
            $archive
        );
        $document['arquivo_id'] = (int) $this->pdo->lastInsertId();
        $this->execute(
            'INSERT INTO comida_mesa_documentos (inscricao_id, arquivo_id, tipo, descricao, enviado_por)
             VALUES (:inscricao_id, :arquivo_id, :tipo, :descricao, :enviado_por)',
            $document
        );

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function findDocumentForView(int $documentId): ?array
    {
        return $this->fetchOne(
            "SELECT d.*, a.nome_original, a.nome_armazenado, a.caminho_relativo, a.mime_type, a.extensao, a.tamanho, a.ativo AS arquivo_ativo
             FROM comida_mesa_documentos d INNER JOIN arquivos a ON a.id = d.arquivo_id
             WHERE d.id = :id AND a.ativo = 1 LIMIT 1",
            ['id' => $documentId]
        );
    }

    public function addHistory(int $registrationId, ?int $userId, string $action, ?string $description, ?array $before, ?array $after): int
    {
        $this->execute(
            'INSERT INTO comida_mesa_historico (inscricao_id, usuario_id, acao, descricao, dados_anteriores, dados_novos)
             VALUES (:inscricao_id, :usuario_id, :acao, :descricao, :dados_anteriores, :dados_novos)',
            [
                'inscricao_id' => $registrationId,
                'usuario_id' => $userId,
                'acao' => mb_substr($action, 0, 100),
                'descricao' => $description === null ? null : mb_substr($description, 0, 255),
                'dados_anteriores' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'dados_novos' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]
        );

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $params */
    private function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->statement($sql, $params);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $params @return list<array<string,mixed>> */
    private function fetchAll(string $sql, array $params = []): array
    {
        return $this->statement($sql, $params)->fetchAll();
    }

    /** @param array<string,mixed> $params */
    private function count(string $sql, array $params = []): int
    {
        return (int) $this->statement($sql, $params)->fetchColumn();
    }

    /** @param array<string,mixed> $params */
    private function statement(string $sql, array $params): \PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bind($stmt, $params);
            $stmt->execute();

            return $stmt;
        } catch (PDOException $exception) {
            throw $this->fail('statement', 'Falha ao executar operação do módulo.', $exception);
        }
    }

    /** @param array<string,mixed> $params */
    private function execute(string $sql, array $params): void
    {
        $this->statement($sql, $params);
    }

    private function deliveryJoin(?int $competenceId, string $placeholder): string
    {
        if ($competenceId === null) {
            return 'LEFT JOIN comida_mesa_entregas entrega ON 1 = 0';
        }

        return 'LEFT JOIN comida_mesa_entregas entrega ON entrega.inscricao_id = i.id AND entrega.competencia_id = :' . $placeholder;
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function filterWhere(ComidaMesaFilter $filter): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($filter->search !== null) {
            $where[] = '(p.nome LIKE :search_name OR p.nis LIKE :search_nis OR f.codigo LIKE :search_code'
                . (Validator::onlyDigits($filter->search) === '' ? ')' : ' OR p.cpf LIKE :search_cpf)');
            $params['search_name'] = '%' . $filter->search . '%';
            $params['search_nis'] = '%' . $filter->search . '%';
            $params['search_code'] = '%' . $filter->search . '%';
            if (Validator::onlyDigits($filter->search) !== '') {
                $params['search_cpf'] = '%' . Validator::onlyDigits($filter->search) . '%';
            }
        }

        if ($filter->programStatus !== null) {
            $where[] = 'i.status = :program_status';
            $params['program_status'] = $filter->programStatus;
        }

        foreach (['zone' => 'f.zona', 'district' => 'f.bairro', 'community' => 'f.comunidade'] as $property => $column) {
            if ($filter->{$property} !== null) {
                $where[] = "{$column} = :{$property}";
                $params[$property] = $filter->{$property};
            }
        }

        if ($filter->poleId !== null) {
            $where[] = 'i.polo_id = :pole_id';
            $params['pole_id'] = $filter->poleId;
        }

        if ($filter->deliveryStatus === 'recebida') {
            $where[] = $filter->competenceId === null ? '1 = 0' : "entrega.id IS NOT NULL AND entrega.status = 'entregue'";
        } elseif ($filter->deliveryStatus === 'aguardando') {
            if ($filter->competenceId === null) {
                $where[] = '1 = 0';
            } else {
                $where[] = "i.status = 'ativa' AND NOT EXISTS (
                    SELECT 1 FROM comida_mesa_entregas e2
                    WHERE e2.inscricao_id = i.id AND e2.competencia_id = :aguardando_competencia_id AND e2.status = 'entregue'
                )";
                $params['aguardando_competencia_id'] = $filter->competenceId;
            }
        } elseif ($filter->deliveryStatus === 'bloqueada') {
            $where[] = "i.status IN ('suspensa', 'bloqueada')";
        } elseif ($filter->deliveryStatus === 'indisponivel') {
            $where[] = "i.status IN ('em_analise', 'lista_espera', 'encerrada')";
        }

        return [implode(' AND ', $where), $params];
    }

    /** @return list<array<string,mixed>> */
    private function documentsByRegistration(int $registrationId): array
    {
        return $this->fetchAll(
            "SELECT d.id, d.tipo, d.descricao, d.criado_em, u.nome AS enviado_por_nome,
                    a.nome_original, a.mime_type, a.tamanho
             FROM comida_mesa_documentos d
             INNER JOIN arquivos a ON a.id = d.arquivo_id AND a.ativo = 1
             LEFT JOIN usuarios u ON u.id = d.enviado_por
             WHERE d.inscricao_id = :id ORDER BY d.criado_em DESC",
            ['id' => $registrationId]
        );
    }

    /** @return list<array<string,mixed>> */
    private function historyByRegistration(int $registrationId): array
    {
        return $this->fetchAll(
            'SELECT h.id, h.acao, h.descricao, h.dados_anteriores, h.dados_novos, h.criado_em, u.nome AS usuario_nome
             FROM comida_mesa_historico h LEFT JOIN usuarios u ON u.id = h.usuario_id
             WHERE h.inscricao_id = :id ORDER BY h.criado_em DESC, h.id DESC',
            ['id' => $registrationId]
        );
    }

    /** @param array<string,mixed> $params */
    private function bind(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }

    /** @param array<string,mixed> $data @param list<string> $keys @return array<string,mixed> */
    private function only(array $data, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $data[$key] ?? null;
        }
        return $result;
    }

    private function fail(string $operation, string $message, PDOException $exception): RepositoryException
    {
        Logger::application('Repository operation failed.', [
            'repository' => self::class,
            'operation' => $operation,
            'type' => $exception::class,
            'code' => $exception->getCode(),
        ]);

        return new RepositoryException($message, 0, $exception);
    }
}
