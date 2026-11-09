<?php

declare(strict_types=1);

class PessoasService
{
    private $repository;
    private $beneficios;

    public function __construct(PessoasRepository $repository, BeneficiosSigasService $beneficios)
    {
        $this->repository = $repository;
        $this->beneficios = $beneficios;
    }

    public function listar(array $filters)
    {
        $rows = $this->repository->listarBase($filters);
        $needsBenefitFilter = $this->beneficios->disponivel() && (
            !empty($filters['programa']) || !empty($filters['beneficio_situacao']) || !empty($filters['beneficio_quantidade'])
        );

        $beneficiosMap = array();
        if ($needsBenefitFilter) {
            $cpfs = array();
            foreach ($rows as $row) {
                $cpf = pc_only_digits(isset($row['cpf']) ? $row['cpf'] : '');
                if (strlen($cpf) === 11) $cpfs[] = $cpf;
            }
            $beneficiosMap = $this->beneficios->porCpfs(array_values(array_unique($cpfs)));
            $filtered = array();
            foreach ($rows as $row) {
                $cpf = pc_only_digits(isset($row['cpf']) ? $row['cpf'] : '');
                $beneficios = isset($beneficiosMap[$cpf]) ? $beneficiosMap[$cpf] : array('_meta' => array('quantidade' => 0));
                if ($this->beneficios->atendeFiltro($beneficios, $filters['programa'], $filters['beneficio_situacao'], $filters['beneficio_quantidade'])) {
                    $filtered[] = $row;
                }
            }
            $rows = $filtered;
        }

        $total = count($rows);
        $perPage = isset($filters['per_page']) ? max(10, min(100, (int)$filters['per_page'])) : 20;
        $pages = max(1, (int)ceil($total / $perPage));
        $page = isset($filters['page']) ? max(1, min((int)$filters['page'], $pages)) : 1;
        $offset = ($page - 1) * $perPage;
        $pageRows = array_slice($rows, $offset, $perPage);

        if ($this->beneficios->disponivel()) {
            $missing = array();
            foreach ($pageRows as $row) {
                $cpf = pc_only_digits(isset($row['cpf']) ? $row['cpf'] : '');
                if (strlen($cpf) === 11 && !isset($beneficiosMap[$cpf])) $missing[] = $cpf;
            }
            if ($missing) {
                $beneficiosMap = array_replace($beneficiosMap, $this->beneficios->porCpfs(array_values(array_unique($missing))));
            }
        }

        foreach ($pageRows as &$row) {
            $cpf = pc_only_digits(isset($row['cpf']) ? $row['cpf'] : '');
            $row['beneficios_sigas'] = isset($beneficiosMap[$cpf]) ? $beneficiosMap[$cpf] : array('_meta' => array('quantidade' => 0));
        }
        unset($row);

        return array(
            'rows' => $pageRows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
            'sigas_disponivel' => $this->beneficios->disponivel(),
        );
    }

    public function bairros()
    {
        return $this->repository->bairros();
    }

    public function ajudasTipos()
    {
        return $this->repository->ajudasTipos();
    }

    public function indicadoresLocais()
    {
        return $this->repository->estatisticasLocais();
    }
}
