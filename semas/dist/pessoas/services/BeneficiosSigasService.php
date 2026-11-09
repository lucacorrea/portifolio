<?php

declare(strict_types=1);

class BeneficiosSigasService
{
    private $repository;

    public function __construct(BeneficiosSigasRepository $repository)
    {
        $this->repository = $repository;
    }

    public function disponivel()
    {
        return $this->repository->disponivel();
    }

    public function porCpf($cpf)
    {
        return $this->normalizar($this->repository->porCpf($cpf));
    }

    public function porCpfs(array $cpfs)
    {
        $raw = $this->repository->porCpfs($cpfs);
        $out = array();

        foreach ($cpfs as $cpf) {
            $digits = pc_only_digits($cpf);
            if (strlen($digits) !== 11) {
                continue;
            }
            $out[$digits] = $this->normalizar(isset($raw[$digits]) ? $raw[$digits] : array());
        }

        return $out;
    }

    public function atendeFiltro(array $beneficios, $programa, $situacao, $quantidade)
    {
        $encontrados = array();
        foreach ($beneficios as $key => $beneficio) {
            if ($key === '_meta') {
                continue;
            }
            if (!empty($beneficio['encontrado'])) {
                $encontrados[$key] = $beneficio;
            }
        }

        if ($programa !== '') {
            if ($programa === 'nenhum') {
                if (count($encontrados) > 0) {
                    return false;
                }
            } elseif (empty($encontrados[$programa])) {
                return false;
            }
        }

        if ($quantidade !== '') {
            $count = count($encontrados);
            if ($quantidade === '0' && $count !== 0) return false;
            if ($quantidade === '1' && $count !== 1) return false;
            if ($quantidade === '2' && $count !== 2) return false;
            if ($quantidade === '3+' && $count < 3) return false;
        }

        if ($situacao !== '') {
            $match = false;
            foreach ($encontrados as $item) {
                $category = strtolower(trim((string)(isset($item['categoria_status']) ? $item['categoria_status'] : '')));

                if ($situacao === 'ativo' && $category === 'ativo') $match = true;
                if ($situacao === 'pendente' && $category === 'pendente') $match = true;
                if ($situacao === 'revisar' && in_array($category, array('revisar', 'restrito'), true)) $match = true;
                if ($situacao === 'encerrado' && $category === 'inativo') $match = true;
            }
            if (!$match) {
                return false;
            }
        }

        return true;
    }

    private function normalizar(array $programas)
    {
        $count = 0;
        $categories = array();
        $names = array();

        foreach ($programas as $key => $item) {
            if ($key === '_meta' || empty($item['encontrado'])) {
                continue;
            }
            $count++;
            $categories[] = strtolower(trim((string)(isset($item['categoria_status']) ? $item['categoria_status'] : '')));
            $name = trim((string)(isset($item['programa']) ? $item['programa'] : ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        $tone = 'muted';
        if (in_array('revisar', $categories, true) || in_array('restrito', $categories, true)) {
            $tone = 'danger';
        } elseif (in_array('pendente', $categories, true)) {
            $tone = 'warning';
        } elseif (in_array('ativo', $categories, true)) {
            $tone = 'success';
        } elseif ($count > 0) {
            $tone = 'info';
        }

        $programas['_meta'] = array(
            'quantidade' => $count,
            'possui_vinculo' => $count > 0,
            'tom' => $tone,
            'programas' => array_values(array_unique($names)),
        );

        return $programas;
    }
}
