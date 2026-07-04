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
        return $this->fetchAll(
            'SELECT id, ajuda_tipo_id, data_solicitacao, status, created_by
             FROM solicitacoes
             WHERE solicitante_id = :solicitante_id
             ORDER BY data_solicitacao DESC, id DESC
             LIMIT 5',
            ['solicitante_id' => $solicitanteId]
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
