<?php

declare(strict_types=1);

/**
 * Consulta SOMENTE LEITURA do SIGAS.
 *
 * Primeira fase:
 * - Primeiro Emprego: pe_candidatos
 * - Comida na Mesa: comida_mesa_inscricoes + familias + pessoas
 *
 * Não altera nenhum registro no SIGAS.
 * Não depende das views de integração para funcionar.
 */
class BeneficiosSigasRepository
{
    private $pdo;
    private $sourceChecked = false;
    private $sourceAvailable = false;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function disponivel()
    {
        if (!($this->pdo instanceof PDO)) {
            return false;
        }

        if ($this->sourceChecked) {
            return $this->sourceAvailable;
        }

        $this->sourceChecked = true;

        try {
            $stmt = $this->pdo->query('SELECT 1');

            if ($stmt instanceof PDOStatement) {
                $stmt->fetchColumn();
            }

            $this->sourceAvailable = true;
        } catch (Throwable $e) {
            $this->sourceAvailable = false;
        }

        return $this->sourceAvailable;
    }

    public function porCpf($cpf)
    {
        $digits = pc_only_digits($cpf);

        if (strlen($digits) !== 11) {
            return array();
        }

        $map = $this->porCpfs(array($digits));

        return isset($map[$digits])
            ? $map[$digits]
            : array();
    }

    /**
     * @return array<string,array<string,array<string,mixed>>>
     */
    public function porCpfs(array $cpfs)
    {
        $result = array();

        if (!$this->disponivel()) {
            return $result;
        }

        $valid = array();

        foreach ($cpfs as $cpf) {
            $digits = pc_only_digits($cpf);

            if (strlen($digits) === 11) {
                $valid[$digits] = true;
            }
        }

        $valid = array_keys($valid);

        if (!$valid) {
            return $result;
        }

        foreach (array_chunk($valid, 250) as $chunk) {
            $this->buscarPrimeiroEmprego($chunk, $result);
            $this->buscarComidaResponsaveis($chunk, $result);
            $this->buscarComidaMembros($chunk, $result);
        }

        return $result;
    }

    private function buscarPrimeiroEmprego(
        array $cpfs,
        array &$result
    ) {
        $placeholders = implode(
            ',',
            array_fill(0, count($cpfs), '?')
        );

        $sql = "
            SELECT
                id,
                cpf,
                nome,
                status,
                revisao_status,
                revisao_cpf,
                revisao_telefone,
                revisao_nascimento,
                cpf_duplicado,
                bairro,
                created_at,
                updated_at
            FROM pe_candidatos
            WHERE cpf IN ({$placeholders})
            ORDER BY id DESC
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($cpfs);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
        } catch (Throwable $e) {
            @error_log(
                '[SEMAS][SIGAS] Consulta Primeiro Emprego falhou.'
            );
            return;
        }

        foreach ($rows as $row) {
            $cpf = pc_only_digits(
                isset($row['cpf']) ? $row['cpf'] : ''
            );

            if (strlen($cpf) !== 11) {
                continue;
            }

            $review = trim(
                (string)(
                    isset($row['revisao_status'])
                        ? $row['revisao_status']
                        : ''
                )
            );

            $hasReview =
                $review !== ''
                || !empty($row['revisao_cpf'])
                || !empty($row['revisao_telefone'])
                || !empty($row['revisao_nascimento'])
                || !empty($row['cpf_duplicado']);

            $status = trim(
                (string)(
                    isset($row['status'])
                        ? $row['status']
                        : ''
                )
            );

            if ($hasReview) {
                $category = 'revisar';

                if ($review !== '') {
                    $label = $review;
                } elseif (!empty($row['cpf_duplicado'])) {
                    $label = 'CPF duplicado';
                } elseif (!empty($row['revisao_cpf'])) {
                    $label = 'Revisar CPF';
                } elseif (!empty($row['revisao_telefone'])) {
                    $label = 'Revisar Telefone';
                } else {
                    $label = 'Revisar Nascimento';
                }
            } else {
                $lower = strtolower($status);

                if (
                    strpos($lower, 'contempl') !== false
                    || strpos($lower, 'ativ') !== false
                ) {
                    $category = 'ativo';
                } elseif (
                    strpos($lower, 'inativ') !== false
                    || strpos($lower, 'encerr') !== false
                    || strpos($lower, 'deslig') !== false
                    || strpos($lower, 'cancel') !== false
                ) {
                    $category = 'inativo';
                } else {
                    $category = 'pendente';
                }

                $label = $status !== ''
                    ? $status
                    : 'Vínculo localizado';
            }

            $item = array(
                'encontrado' => true,
                'programa' => 'Coari Meu Primeiro Emprego',
                'programa_slug' => 'primeiro_emprego',
                'status' => $label,
                'status_codigo' => $status,
                'categoria_status' => $category,
                'situacao' => 'Cadastro localizado no programa',
                'unidade' => '',
                'setor' => '',
                'bairro' => isset($row['bairro'])
                    ? (string)$row['bairro']
                    : '',
                'vinculo_id' => isset($row['id'])
                    ? (int)$row['id']
                    : 0,
                'data_inicio' => isset($row['created_at'])
                    ? $row['created_at']
                    : null,
                'data_atualizacao' => isset($row['updated_at'])
                    ? $row['updated_at']
                    : null,
                'origem' => 'pe_candidatos',
                'fonte_tabela' => 'pe_candidatos',
                'ocorrencias' => 1,
                'detalhes' => $row,
            );

            $this->addProgram(
                $result,
                $cpf,
                'primeiro_emprego',
                $item
            );
        }
    }

    private function buscarComidaResponsaveis(
        array $cpfs,
        array &$result
    ) {
        $placeholders = implode(
            ',',
            array_fill(0, count($cpfs), '?')
        );

        $sql = "
            SELECT
                p.cpf,
                p.nome,
                i.id AS vinculo_id,
                i.status,
                i.data_inscricao,
                i.criado_em,
                i.atualizado_em,
                f.bairro
            FROM comida_mesa_inscricoes i
            INNER JOIN familias f
                ON f.id = i.familia_id
            INNER JOIN pessoas p
                ON p.id = f.responsavel_pessoa_id
            WHERE p.cpf IN ({$placeholders})
            ORDER BY i.id DESC
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($cpfs);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
        } catch (Throwable $e) {
            @error_log(
                '[SEMAS][SIGAS] Consulta Comida na Mesa/responsável falhou.'
            );
            return;
        }

        foreach ($rows as $row) {
            $this->mapComida(
                $row,
                $result,
                'Responsável familiar'
            );
        }
    }

    private function buscarComidaMembros(
        array $cpfs,
        array &$result
    ) {
        $placeholders = implode(
            ',',
            array_fill(0, count($cpfs), '?')
        );

        $sql = "
            SELECT
                p.cpf,
                p.nome,
                i.id AS vinculo_id,
                i.status,
                i.data_inscricao,
                i.criado_em,
                i.atualizado_em,
                f.bairro
            FROM comida_mesa_inscricoes i
            INNER JOIN familias f
                ON f.id = i.familia_id
            INNER JOIN familia_membros fm
                ON fm.familia_id = f.id
            INNER JOIN pessoas p
                ON p.id = fm.pessoa_id
            WHERE p.cpf IN ({$placeholders})
              AND p.id <> f.responsavel_pessoa_id
            ORDER BY i.id DESC
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($cpfs);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
        } catch (Throwable $e) {
            @error_log(
                '[SEMAS][SIGAS] Consulta Comida na Mesa/membro falhou.'
            );
            return;
        }

        foreach ($rows as $row) {
            $this->mapComida(
                $row,
                $result,
                'Membro familiar'
            );
        }
    }

    private function mapComida(
        array $row,
        array &$result,
        $situacao
    ) {
        $cpf = pc_only_digits(
            isset($row['cpf']) ? $row['cpf'] : ''
        );

        if (strlen($cpf) !== 11) {
            return;
        }

        $status = strtolower(
            trim(
                (string)(
                    isset($row['status'])
                        ? $row['status']
                        : ''
                )
            )
        );

        switch ($status) {
            case 'ativa':
                $category = 'ativo';
                $label = 'Beneficiária ativa';
                break;

            case 'em_analise':
                $category = 'pendente';
                $label = 'Em análise';
                break;

            case 'lista_espera':
                $category = 'pendente';
                $label = 'Lista de espera';
                break;

            case 'suspensa':
                $category = 'restrito';
                $label = 'Suspensa';
                break;

            case 'bloqueada':
                $category = 'restrito';
                $label = 'Bloqueada';
                break;

            default:
                $category = 'pendente';
                $label = $status !== ''
                    ? $status
                    : 'Vínculo localizado';
                break;
        }

        $item = array(
            'encontrado' => true,
            'programa' => 'Coari Comida na Mesa',
            'programa_slug' => 'comida_na_mesa',
            'status' => $label,
            'status_codigo' => isset($row['status'])
                ? (string)$row['status']
                : '',
            'categoria_status' => $category,
            'situacao' => (string)$situacao,
            'unidade' => '',
            'setor' => '',
            'bairro' => isset($row['bairro'])
                ? (string)$row['bairro']
                : '',
            'vinculo_id' => isset($row['vinculo_id'])
                ? (int)$row['vinculo_id']
                : 0,
            'data_inicio' => isset($row['data_inscricao'])
                ? $row['data_inscricao']
                : (
                    isset($row['criado_em'])
                        ? $row['criado_em']
                        : null
                ),
            'data_atualizacao' => isset($row['atualizado_em'])
                ? $row['atualizado_em']
                : null,
            'origem' => 'comida_mesa_inscricoes',
            'fonte_tabela' => 'comida_mesa_inscricoes',
            'ocorrencias' => 1,
            'detalhes' => $row,
        );

        $this->addProgram(
            $result,
            $cpf,
            'comida_na_mesa',
            $item
        );
    }

    private function addProgram(
        array &$result,
        $cpf,
        $slug,
        array $item
    ) {
        if (!isset($result[$cpf])) {
            $result[$cpf] = array();
        }

        if (!isset($result[$cpf][$slug])) {
            $result[$cpf][$slug] = $item;
            return;
        }

        $current = $result[$cpf][$slug];

        $itemCount = isset($current['ocorrencias'])
            ? (int)$current['ocorrencias'] + 1
            : 2;

        if (
            $this->priority(
                isset($item['categoria_status'])
                    ? $item['categoria_status']
                    : ''
            )
            >
            $this->priority(
                isset($current['categoria_status'])
                    ? $current['categoria_status']
                    : ''
            )
        ) {
            $item['ocorrencias'] = $itemCount;
            $result[$cpf][$slug] = $item;
        } else {
            $current['ocorrencias'] = $itemCount;
            $result[$cpf][$slug] = $current;
        }
    }

    private function priority($category)
    {
        switch (strtolower(trim((string)$category))) {
            case 'revisar':
                return 5;

            case 'restrito':
                return 4;

            case 'pendente':
                return 3;

            case 'ativo':
                return 2;

            case 'inativo':
                return 1;

            default:
                return 0;
        }
    }
}
