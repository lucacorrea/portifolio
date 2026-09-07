<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\RepositoryException;
use PDO;
use PDOException;
use Throwable;

final class SocioeconomicRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByPersonId(int $personId): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT s.*,
                        u.nome AS entrevistado_por_nome,
                        c.nome AS confirmado_por_nome,
                        a.nome AS atualizado_por_nome
                 FROM pessoa_socioeconomico s
                 LEFT JOIN usuarios u ON u.id = s.entrevistado_por
                 LEFT JOIN usuarios c ON c.id = s.confirmado_por
                 LEFT JOIN usuarios a ON a.id = s.atualizado_por
                 WHERE s.pessoa_id = :pessoa_id
                 LIMIT 1'
            );
            $stmt->execute(['pessoa_id' => $personId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }

            $row['membros'] = $this->members((int) $row['id']);
            $row['beneficios'] = $this->decodeList($row['beneficios_json'] ?? null);
            $row['vulnerabilidades'] = $this->decodeList($row['vulnerabilidades_json'] ?? null);
            return $row;
        } catch (PDOException $exception) {
            if ($this->missingTable($exception)) {
                return null;
            }
            throw new RepositoryException('Falha ao consultar o prontuário socioeconômico.', 0, $exception);
        }
    }

    /** @return list<array<string,mixed>> */
    public function members(int $socioeconomicId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, nome, data_nascimento, parentesco, escolaridade,
                        ocupacao, renda_mensal, possui_deficiencia, observacao, ordem
                 FROM pessoa_socioeconomico_membros
                 WHERE socioeconomico_id = :id
                 ORDER BY ordem ASC, id ASC'
            );
            $stmt->execute(['id' => $socioeconomicId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (PDOException $exception) {
            if ($this->missingTable($exception)) {
                return [];
            }
            throw new RepositoryException('Falha ao consultar a composição familiar.', 0, $exception);
        }
    }

    /**
     * @param array<string,mixed> $data
     * @param list<array<string,mixed>> $members
     */
    public function save(
        int $personId,
        ?int $familyId,
        array $data,
        array $members,
        int $userId,
        string $origin = 'sigas',
        ?string $reason = null,
    ): int {
        $this->pdo->beginTransaction();

        try {
            $lock = $this->pdo->prepare(
                'SELECT * FROM pessoa_socioeconomico WHERE pessoa_id = :pessoa_id LIMIT 1 FOR UPDATE'
            );
            $lock->execute(['pessoa_id' => $personId]);
            $current = $lock->fetch(PDO::FETCH_ASSOC);

            if (is_array($current)) {
                $current['membros'] = $this->members((int) $current['id']);
                $this->insertHistory(
                    (int) $current['id'],
                    $personId,
                    (string) ($current['origem'] ?? 'sigas'),
                    $reason ?? 'Atualização do prontuário socioeconômico',
                    $current,
                    $userId
                );
            }

            $params = $this->profileParams($personId, $familyId, $data, $userId, $origin);

            if (!is_array($current)) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO pessoa_socioeconomico
                        (pessoa_id, familia_id, origem, anexo_solicitante_id, anexo_atualizado_em,
                         versao_formulario, data_entrevista, entrevistado_por, confirmado_em, confirmado_por,
                         escolaridade, situacao_trabalho, ocupacao, renda_individual, renda_familiar, renda_per_capita,
                         grupo_tradicional, possui_deficiencia, deficiencia_descricao,
                         beneficios_json, vulnerabilidades_json,
                         tipo_moradia, material_moradia, numero_comodos, abastecimento_agua,
                         energia_eletrica, coleta_lixo, esgotamento_sanitario,
                         area_risco, area_risco_descricao, resumo_social, observacoes,
                         criado_por, atualizado_por)
                     VALUES
                        (:pessoa_id, :familia_id, :origem, :anexo_solicitante_id, :anexo_atualizado_em,
                         :versao_formulario, :data_entrevista, :entrevistado_por, :confirmado_em, :confirmado_por,
                         :escolaridade, :situacao_trabalho, :ocupacao, :renda_individual, :renda_familiar, :renda_per_capita,
                         :grupo_tradicional, :possui_deficiencia, :deficiencia_descricao,
                         :beneficios_json, :vulnerabilidades_json,
                         :tipo_moradia, :material_moradia, :numero_comodos, :abastecimento_agua,
                         :energia_eletrica, :coleta_lixo, :esgotamento_sanitario,
                         :area_risco, :area_risco_descricao, :resumo_social, :observacoes,
                         :usuario_id, :usuario_id)'
                );
                $stmt->execute($params);
                $socioeconomicId = (int) $this->pdo->lastInsertId();
            } else {
                $socioeconomicId = (int) $current['id'];
                $params['id'] = $socioeconomicId;
                $stmt = $this->pdo->prepare(
                    'UPDATE pessoa_socioeconomico
                     SET familia_id = :familia_id,
                         origem = :origem,
                         anexo_solicitante_id = :anexo_solicitante_id,
                         anexo_atualizado_em = :anexo_atualizado_em,
                         versao_formulario = :versao_formulario,
                         data_entrevista = :data_entrevista,
                         entrevistado_por = :entrevistado_por,
                         confirmado_em = :confirmado_em,
                         confirmado_por = :confirmado_por,
                         escolaridade = :escolaridade,
                         situacao_trabalho = :situacao_trabalho,
                         ocupacao = :ocupacao,
                         renda_individual = :renda_individual,
                         renda_familiar = :renda_familiar,
                         renda_per_capita = :renda_per_capita,
                         grupo_tradicional = :grupo_tradicional,
                         possui_deficiencia = :possui_deficiencia,
                         deficiencia_descricao = :deficiencia_descricao,
                         beneficios_json = :beneficios_json,
                         vulnerabilidades_json = :vulnerabilidades_json,
                         tipo_moradia = :tipo_moradia,
                         material_moradia = :material_moradia,
                         numero_comodos = :numero_comodos,
                         abastecimento_agua = :abastecimento_agua,
                         energia_eletrica = :energia_eletrica,
                         coleta_lixo = :coleta_lixo,
                         esgotamento_sanitario = :esgotamento_sanitario,
                         area_risco = :area_risco,
                         area_risco_descricao = :area_risco_descricao,
                         resumo_social = :resumo_social,
                         observacoes = :observacoes,
                         atualizado_por = :usuario_id
                     WHERE id = :id'
                );
                $stmt->execute($params);
            }

            $this->replaceMembers($socioeconomicId, $members);
            $saved = $this->findByPersonId($personId) ?? ['id' => $socioeconomicId, 'pessoa_id' => $personId];
            $this->insertHistory(
                $socioeconomicId,
                $personId,
                $origin,
                is_array($current) ? 'Nova versão confirmada' : 'Prontuário socioeconômico criado',
                $saved,
                $userId
            );

            $this->pdo->commit();
            return $socioeconomicId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof RepositoryException) {
                throw $exception;
            }
            throw new RepositoryException('Falha ao salvar o prontuário socioeconômico.', 0, $exception);
        }
    }

    /** @return list<array<string,mixed>> */
    public function history(int $personId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        try {
            $stmt = $this->pdo->prepare(
                'SELECT h.id, h.origem, h.motivo, h.dados_json, h.criado_em,
                        u.nome AS usuario_nome
                 FROM pessoa_socioeconomico_historico h
                 LEFT JOIN usuarios u ON u.id = h.usuario_id
                 WHERE h.pessoa_id = :pessoa_id
                 ORDER BY h.criado_em DESC, h.id DESC
                 LIMIT ' . $limit
            );
            $stmt->execute(['pessoa_id' => $personId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (PDOException $exception) {
            if ($this->missingTable($exception)) {
                return [];
            }
            throw new RepositoryException('Falha ao consultar o histórico socioeconômico.', 0, $exception);
        }
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function profileParams(int $personId, ?int $familyId, array $data, int $userId, string $origin): array
    {
        $membersCount = max(1, (int) ($data['quantidade_membros'] ?? 1));
        $familyIncome = $this->decimal($data['renda_familiar'] ?? null);
        $perCapita = $familyIncome === null ? null : number_format(((float) $familyIncome) / $membersCount, 2, '.', '');

        return [
            'pessoa_id' => $personId,
            'familia_id' => $familyId,
            'origem' => $origin,
            'anexo_solicitante_id' => $this->intOrNull($data['anexo_solicitante_id'] ?? null),
            'anexo_atualizado_em' => $this->nullable($data['anexo_atualizado_em'] ?? null),
            'versao_formulario' => max(1, (int) ($data['versao_formulario'] ?? 1)),
            'data_entrevista' => $this->nullable($data['data_entrevista'] ?? date('Y-m-d H:i:s')),
            'entrevistado_por' => $this->intOrNull($data['entrevistado_por'] ?? $userId),
            'confirmado_em' => $this->nullable($data['confirmado_em'] ?? date('Y-m-d H:i:s')),
            'confirmado_por' => $this->intOrNull($data['confirmado_por'] ?? $userId),
            'escolaridade' => $this->nullable($data['escolaridade'] ?? null),
            'situacao_trabalho' => $this->nullable($data['situacao_trabalho'] ?? null),
            'ocupacao' => $this->nullable($data['ocupacao'] ?? null),
            'renda_individual' => $this->decimal($data['renda_individual'] ?? null),
            'renda_familiar' => $familyIncome,
            'renda_per_capita' => $perCapita,
            'grupo_tradicional' => $this->nullable($data['grupo_tradicional'] ?? null),
            'possui_deficiencia' => !empty($data['possui_deficiencia']) ? 1 : 0,
            'deficiencia_descricao' => $this->nullable($data['deficiencia_descricao'] ?? null),
            'beneficios_json' => $this->jsonList($data['beneficios'] ?? []),
            'vulnerabilidades_json' => $this->jsonList($data['vulnerabilidades'] ?? []),
            'tipo_moradia' => $this->nullable($data['tipo_moradia'] ?? $data['situacao_habitacional'] ?? null),
            'material_moradia' => $this->nullable($data['material_moradia'] ?? $data['condicao_moradia'] ?? null),
            'numero_comodos' => $this->intOrNull($data['numero_comodos'] ?? null),
            'abastecimento_agua' => $this->nullable($data['abastecimento_agua'] ?? $data['agua_tratada'] ?? null),
            'energia_eletrica' => $this->nullable($data['energia_eletrica'] ?? null),
            'coleta_lixo' => $this->nullable($data['coleta_lixo'] ?? null),
            'esgotamento_sanitario' => $this->nullable($data['esgotamento_sanitario'] ?? null),
            'area_risco' => !empty($data['area_risco']) ? 1 : 0,
            'area_risco_descricao' => $this->nullable($data['area_risco_descricao'] ?? null),
            'resumo_social' => $this->nullable($data['resumo_social'] ?? $data['resumo_caso'] ?? null),
            'observacoes' => $this->nullable($data['observacoes'] ?? null),
            'usuario_id' => $userId,
        ];
    }

    /** @param list<array<string,mixed>> $members */
    private function replaceMembers(int $socioeconomicId, array $members): void
    {
        $delete = $this->pdo->prepare(
            'DELETE FROM pessoa_socioeconomico_membros WHERE socioeconomico_id = :id'
        );
        $delete->execute(['id' => $socioeconomicId]);

        if ($members === []) {
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO pessoa_socioeconomico_membros
                (socioeconomico_id, nome, data_nascimento, parentesco, escolaridade,
                 ocupacao, renda_mensal, possui_deficiencia, observacao, ordem)
             VALUES
                (:socioeconomico_id, :nome, :data_nascimento, :parentesco, :escolaridade,
                 :ocupacao, :renda_mensal, :possui_deficiencia, :observacao, :ordem)'
        );

        foreach (array_values($members) as $index => $member) {
            $name = trim((string) ($member['nome'] ?? $member['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $insert->execute([
                'socioeconomico_id' => $socioeconomicId,
                'nome' => mb_substr($name, 0, 160),
                'data_nascimento' => $this->nullable($member['data_nascimento'] ?? $member['birth_date'] ?? null),
                'parentesco' => $this->nullable($member['parentesco'] ?? $member['relationship'] ?? null),
                'escolaridade' => $this->nullable($member['escolaridade'] ?? $member['schooling'] ?? null),
                'ocupacao' => $this->nullable($member['ocupacao'] ?? null),
                'renda_mensal' => $this->decimal($member['renda_mensal'] ?? null),
                'possui_deficiencia' => !empty($member['possui_deficiencia']) ? 1 : 0,
                'observacao' => $this->nullable($member['observacao'] ?? null),
                'ordem' => $index + 1,
            ]);
        }
    }

    /** @param array<string,mixed> $data */
    private function insertHistory(
        int $socioeconomicId,
        int $personId,
        string $origin,
        string $reason,
        array $data,
        int $userId,
    ): void {
        unset($data['beneficios_json'], $data['vulnerabilidades_json']);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            $json = '{}';
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO pessoa_socioeconomico_historico
                (socioeconomico_id, pessoa_id, origem, motivo, dados_json, usuario_id)
             VALUES
                (:socioeconomico_id, :pessoa_id, :origem, :motivo, :dados_json, :usuario_id)'
        );
        $stmt->execute([
            'socioeconomico_id' => $socioeconomicId,
            'pessoa_id' => $personId,
            'origem' => $origin,
            'motivo' => mb_substr($reason, 0, 255),
            'dados_json' => $json,
            'usuario_id' => $userId,
        ]);
    }

    /** @return list<string> */
    private function decodeList(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_filter(array_map(static fn ($item): string => trim((string) $item), $decoded)));
    }

    private function jsonList(mixed $value): string
    {
        $items = is_array($value) ? $value : [$value];
        $items = array_values(array_unique(array_filter(array_map(
            static fn ($item): string => trim((string) $item),
            $items
        ))));
        return json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $number = (int) $value;
        return $number > 0 ? $number : null;
    }

    private function decimal(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $raw = trim((string) $value);
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (str_contains($raw, ',')) {
            $raw = str_replace(',', '.', $raw);
        }
        $raw = preg_replace('/[^0-9.\-]/', '', $raw) ?? '';
        return is_numeric($raw) ? number_format((float) $raw, 2, '.', '') : null;
    }

    private function missingTable(PDOException $exception): bool
    {
        $message = strtolower($exception->getMessage());
        return str_contains($message, 'doesn\'t exist') || str_contains($message, 'does not exist');
    }
}
