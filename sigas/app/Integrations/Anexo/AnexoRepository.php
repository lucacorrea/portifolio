<?php

declare(strict_types=1);

namespace App\Integrations\Anexo;

use App\Core\Validator;
use PDO;
use Throwable;

final class AnexoRepository
{
    public function __construct(private readonly AnexoDatabase $database)
    {
    }

    /** @return array<string,mixed>|null */
    public function findSolicitanteByCpf(string $cpf): ?array
    {
        $sql = "SELECT s.id, s.nome, s.cpf, s.nis, s.telefone, s.bairro_id, b.nome AS bairro_nome,
                    s.genero, s.estado_civil, s.data_nascimento, s.nacionalidade, s.naturalidade,
                    s.rg, s.rg_emissao, s.rg_uf, s.endereco, s.numero, s.complemento, s.referencia,
                    s.renda_familiar, s.total_moradores, s.total_familias, s.resumo_caso,
                    s.conj_nome, s.conj_cpf, s.conj_nis, s.conj_rg, s.conj_nasc,
                    s.created_at, s.updated_at, s.responsavel
                FROM solicitantes s
                LEFT JOIN bairros b ON b.id = s.bairro_id
                WHERE s.cpf = :cpf
                LIMIT 1";

        return $this->fetchOne($sql, ['cpf' => Validator::onlyDigits($cpf)]);
    }

    /** @return list<array<string,mixed>> */
    public function familiares(int $solicitanteId): array
    {
        return $this->fetchAll(
            'SELECT nome, data_nascimento, parentesco, escolaridade, obs
             FROM familiares
             WHERE solicitante_id = :solicitante_id
             ORDER BY nome
             LIMIT 20',
            ['solicitante_id' => $solicitanteId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function solicitacoes(int $solicitanteId): array
    {
        try {
            return $this->fetchAll(
                "SELECT s.id, s.solicitante_id, s.ajuda_tipo_id, s.resumo_caso,
                        s.data_solicitacao, s.status, s.created_by, s.origem,
                        at.nome AS ajuda_nome, at.categoria AS ajuda_categoria,
                        COALESCE(ent.entregas_count, 0) AS entregas_count,
                        ent.data_entrega, ent.hora_entrega
                 FROM solicitacoes s
                 LEFT JOIN ajudas_tipos at ON at.id = s.ajuda_tipo_id
                 LEFT JOIN (
                    SELECT solicitacao_id, COUNT(*) AS entregas_count,
                           MAX(data_entrega) AS data_entrega, MAX(hora_entrega) AS hora_entrega
                    FROM ajudas_entregas
                    WHERE solicitacao_id IS NOT NULL
                      AND UPPER(entregue) = 'SIM'
                    GROUP BY solicitacao_id
                 ) ent ON ent.solicitacao_id = s.id
                 WHERE s.solicitante_id = :solicitante_id
                   AND COALESCE(s.origem, '') <> 'cadastro_duplicada'
                 ORDER BY s.data_solicitacao DESC, s.id DESC",
                ['solicitante_id' => $solicitanteId]
            );
        } catch (AnexoUnavailableException) {
            return $this->fetchAll(
                "SELECT id, solicitante_id, ajuda_tipo_id, resumo_caso,
                        data_solicitacao, status, created_by, NULL AS origem,
                        NULL AS ajuda_nome, NULL AS ajuda_categoria,
                        0 AS entregas_count, NULL AS data_entrega, NULL AS hora_entrega
                 FROM solicitacoes
                 WHERE solicitante_id = :solicitante_id
                 ORDER BY data_solicitacao DESC, id DESC",
                ['solicitante_id' => $solicitanteId]
            );
        }
    }

    /** @return list<array<string,mixed>> */
    public function entregasPorPessoa(int $solicitanteId, string $cpf): array
    {
        return $this->fetchAll(
            "SELECT e.id, e.ajuda_tipo_id, e.pessoa_id, e.pessoa_cpf, e.familia_id,
                    e.data_entrega, e.hora_entrega, e.quantidade, e.valor_aplicado,
                    e.responsavel, e.observacao, e.entregue, e.created_at, e.solicitacao_id,
                    at.nome AS ajuda_nome, at.categoria AS ajuda_categoria,
                    s.status AS solicitacao_status
             FROM ajudas_entregas e
             LEFT JOIN ajudas_tipos at ON at.id = e.ajuda_tipo_id
             LEFT JOIN solicitacoes s ON s.id = e.solicitacao_id
             WHERE (e.pessoa_id = :solicitante_id OR e.pessoa_cpf = :cpf)
               AND UPPER(e.entregue) = 'SIM'
             ORDER BY e.data_entrega DESC, e.hora_entrega DESC, e.id DESC
             LIMIT 50",
            [
                'solicitante_id' => $solicitanteId,
                'cpf' => Validator::onlyDigits($cpf),
            ]
        );
    }

    public function countSolicitantes(): int
    {
        $stmt = $this->database->connection()->query('SELECT COUNT(*) FROM solicitantes');

        return (int) $stmt->fetchColumn();
    }

    /** @param array<string,mixed> $params @return array<string,mixed>|null */
    private function fetchOne(string $sql, array $params): ?array
    {
        $rows = $this->fetchAll($sql, $params);

        return $rows[0] ?? null;
    }

    /** @param array<string,mixed> $params @return list<array<string,mixed>> */
    private function fetchAll(string $sql, array $params): array
    {
        try {
            $stmt = $this->database->connection()->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (Throwable $exception) {
            throw new AnexoUnavailableException('ANEXO query failed.', 0, $exception);
        }
    }
}
