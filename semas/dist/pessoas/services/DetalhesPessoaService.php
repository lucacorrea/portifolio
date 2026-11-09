<?php

declare(strict_types=1);

class DetalhesPessoaService
{
    private $pessoas;
    private $solicitacoes;
    private $familiares;
    private $documentos;
    private $beneficios;

    public function __construct(
        PessoasRepository $pessoas,
        SolicitacoesRepository $solicitacoes,
        FamiliaresRepository $familiares,
        DocumentosRepository $documentos,
        BeneficiosSigasService $beneficios
    ) {
        $this->pessoas = $pessoas;
        $this->solicitacoes = $solicitacoes;
        $this->familiares = $familiares;
        $this->documentos = $documentos;
        $this->beneficios = $beneficios;
    }

    public function obter($id)
    {
        $id = (int)$id;
        $pessoa = $this->pessoas->buscarPorId($id);
        if (!$pessoa) {
            return null;
        }

        /*
         * Mantém exatamente a lógica da antiga pessoasCadastradas.php:
         * a demanda informada no cadastro em solicitantes.ajuda_tipo_id /
         * solicitantes.resumo_caso é a primeira solicitação do histórico.
         */
        $solicitacoesBanco = $this->solicitacoes->listarPorPessoa($id);
        $solicitacaoInicial = $this->montarSolicitacaoInicial($pessoa);
        $solicitacoes = $this->organizarSolicitacoes($pessoa, $solicitacoesBanco, $solicitacaoInicial);

        $documentos = $this->documentos->listarPorPessoa($id);
        $solicitacoes = $this->anexarFotosSolicitacoes($solicitacoes, $documentos);

        return array(
            'solicitante' => $pessoa,
            'solicitacoes' => $solicitacoes,
            'familiares' => $this->familiares->listarPorPessoa($id),
            'documentos' => $documentos,
            'beneficios_sigas' => $this->beneficios->porCpf(isset($pessoa['cpf']) ? $pessoa['cpf'] : ''),
            'sigas_disponivel' => $this->beneficios->disponivel(),
            'links' => array(
                'folha_socioeconomica' => $this->linkFolhaSocioeconomica($pessoa),
                'editar' => 'editarSolicitante.php?id=' . $id,
            ),
        );
    }

    private function montarSolicitacaoInicial(array $pessoa)
    {
        $ajudaTipoId = isset($pessoa['ajuda_tipo_id']) ? (int)$pessoa['ajuda_tipo_id'] : 0;
        $resumo = trim((string)(isset($pessoa['resumo_caso']) ? $pessoa['resumo_caso'] : ''));

        if ($ajudaTipoId <= 0 && $resumo === '') {
            return null;
        }

        return array(
            'id' => 0,
            'solicitante_id' => isset($pessoa['id']) ? (int)$pessoa['id'] : 0,
            'ajuda_tipo_id' => $ajudaTipoId > 0 ? $ajudaTipoId : null,
            'ajuda_nome' => isset($pessoa['ajuda_tipo_nome']) ? $pessoa['ajuda_tipo_nome'] : null,
            'ajuda_categoria' => isset($pessoa['ajuda_tipo_categoria']) ? $pessoa['ajuda_tipo_categoria'] : null,
            'resumo_caso' => $resumo !== '' ? $resumo : null,
            'data_solicitacao' => isset($pessoa['created_at']) ? $pessoa['created_at'] : null,
            'status' => 'Cadastro',
            'created_by' => isset($pessoa['responsavel_cadastro']) ? $pessoa['responsavel_cadastro'] : null,
            'origem' => 'cadastro',
            'entregas_count' => 0,
            'data_entrega' => null,
            'hora_entrega' => null,
            'foto_solicitacao' => null,
        );
    }

    private function organizarSolicitacoes(array $pessoa, array $solicitacoesBanco, $solicitacaoInicial)
    {
        $cpf = preg_replace('/\D+/', '', (string)(isset($pessoa['cpf']) ? $pessoa['cpf'] : ''));
        $cpfValido = is_string($cpf) && strlen($cpf) === 11;
        $indiceInicialReal = null;

        if ($solicitacaoInicial !== null) {
            foreach ($solicitacoesBanco as $i => $solicitacao) {
                $origem = $this->normalizarTexto(isset($solicitacao['origem']) ? $solicitacao['origem'] : '');
                if ($origem !== '' && strpos($origem, 'cadastro') !== false && $this->solicitacoesCompativeis($solicitacaoInicial, $solicitacao)) {
                    $indiceInicialReal = $i;
                    break;
                }
            }

            if ($indiceInicialReal === null) {
                foreach ($solicitacoesBanco as $i => $solicitacao) {
                    if ($this->solicitacoesEquivalentes($solicitacaoInicial, $solicitacao)) {
                        $indiceInicialReal = $i;
                        break;
                    }
                }
            }
        }

        $resultado = array();

        if ($indiceInicialReal !== null) {
            $registroInicial = $solicitacoesBanco[$indiceInicialReal];
            $registroInicial['eh_inicial'] = true;
            $registroInicial['tipo_solicitacao'] = 'inicial';
            $registroInicial['atribuir_beneficio_url'] = $this->linkAtribuirBeneficioSolicitacao($cpfValido ? $cpf : '', $registroInicial);
            $resultado[] = $registroInicial;
        } elseif ($solicitacaoInicial !== null) {
            $solicitacaoInicial['eh_inicial'] = true;
            $solicitacaoInicial['tipo_solicitacao'] = 'inicial';
            $solicitacaoInicial['atribuir_beneficio_url'] = '';
            $resultado[] = $solicitacaoInicial;
        }

        foreach ($solicitacoesBanco as $i => $solicitacao) {
            if ($indiceInicialReal !== null && $i === $indiceInicialReal) {
                continue;
            }
            $solicitacao['eh_inicial'] = false;
            $solicitacao['tipo_solicitacao'] = 'adicional';
            $solicitacao['atribuir_beneficio_url'] = $this->linkAtribuirBeneficioSolicitacao($cpfValido ? $cpf : '', $solicitacao);
            $resultado[] = $solicitacao;
        }

        return $resultado;
    }

    private function solicitacoesCompativeis(array $inicial, array $registro)
    {
        $tipoInicial = isset($inicial['ajuda_tipo_id']) ? (int)$inicial['ajuda_tipo_id'] : 0;
        $tipoRegistro = isset($registro['ajuda_tipo_id']) ? (int)$registro['ajuda_tipo_id'] : 0;
        if ($tipoInicial > 0 && $tipoRegistro > 0 && $tipoInicial !== $tipoRegistro) {
            return false;
        }

        $resumoInicial = $this->normalizarTexto(isset($inicial['resumo_caso']) ? $inicial['resumo_caso'] : '');
        $resumoRegistro = $this->normalizarTexto(isset($registro['resumo_caso']) ? $registro['resumo_caso'] : '');
        if ($resumoInicial !== '' && $resumoRegistro !== '' && $resumoInicial !== $resumoRegistro) {
            return false;
        }

        return true;
    }

    private function solicitacoesEquivalentes(array $inicial, array $registro)
    {
        if (!$this->solicitacoesCompativeis($inicial, $registro)) {
            return false;
        }

        $resumoInicial = $this->normalizarTexto(isset($inicial['resumo_caso']) ? $inicial['resumo_caso'] : '');
        $resumoRegistro = $this->normalizarTexto(isset($registro['resumo_caso']) ? $registro['resumo_caso'] : '');
        if ($resumoInicial === '' || $resumoRegistro === '' || $resumoInicial !== $resumoRegistro) {
            return false;
        }

        $dataInicial = isset($inicial['data_solicitacao']) ? strtotime((string)$inicial['data_solicitacao']) : false;
        $dataRegistro = isset($registro['data_solicitacao']) ? strtotime((string)$registro['data_solicitacao']) : false;
        if ($dataInicial !== false && $dataRegistro !== false) {
            return abs($dataInicial - $dataRegistro) <= 300;
        }

        return true;
    }

    private function normalizarTexto($valor)
    {
        $texto = trim((string)$valor);
        if ($texto === '') {
            return '';
        }
        if (function_exists('mb_strtolower')) {
            $texto = mb_strtolower($texto, 'UTF-8');
        } else {
            $texto = strtolower($texto);
        }
        $texto = preg_replace('/\s+/u', ' ', $texto);
        return is_string($texto) ? $texto : '';
    }

    private function linkAtribuirBeneficioSolicitacao($cpf, array $solicitacao)
    {
        $id = isset($solicitacao['id']) ? (int)$solicitacao['id'] : 0;
        if ($id <= 0 || !is_string($cpf) || strlen($cpf) !== 11) {
            return '';
        }

        return 'atribuirBeneficio.php?cpf=' . rawurlencode($cpf) . '&solicitacao_id=' . $id;
    }

    private function linkFolhaSocioeconomica(array $pessoa)
    {
        $cpf = preg_replace('/\D+/', '', (string)(isset($pessoa['cpf']) ? $pessoa['cpf'] : ''));
        if ($cpf === null || strlen($cpf) !== 11) {
            return '';
        }
        return 'imprimirSocioeconomico.php?cpf=' . rawurlencode($cpf);
    }

    private function anexarFotosSolicitacoes(array $solicitacoes, array $documentos)
    {
        foreach ($solicitacoes as &$solicitacao) {
            if (!isset($solicitacao['foto_solicitacao'])) {
                $solicitacao['foto_solicitacao'] = null;
            }

            $solicitacaoId = isset($solicitacao['id']) ? (int)$solicitacao['id'] : 0;
            if ($solicitacaoId <= 0) {
                continue;
            }

            $dataSolicitacao = trim((string)(isset($solicitacao['data_solicitacao']) ? $solicitacao['data_solicitacao'] : ''));
            foreach ($documentos as $documento) {
                if (!$this->documentoEhImagem($documento)) {
                    continue;
                }

                $documentSolicitacaoId = isset($documento['solicitacao_id']) ? (int)$documento['solicitacao_id'] : 0;
                $arquivo = str_replace('\\', '/', (string)(isset($documento['arquivo_path']) ? $documento['arquivo_path'] : ''));
                $original = (string)(isset($documento['original_name']) ? $documento['original_name'] : '');
                $createdAt = trim((string)(isset($documento['created_at']) ? $documento['created_at'] : ''));

                $matches = $documentSolicitacaoId === $solicitacaoId
                    || strpos(basename($arquivo), 'solicitacao_' . $solicitacaoId . '_foto_') === 0
                    || stripos($original, 'Foto da solicitação #' . $solicitacaoId . '.') === 0
                    || ($dataSolicitacao !== '' && $createdAt === $dataSolicitacao);

                if ($matches) {
                    $url = isset($documento['arquivo_url']) ? trim((string)$documento['arquivo_url']) : '';
                    if ($url === '') {
                        $url = pc_photo_url($arquivo, '');
                    }
                    if ($url !== '') {
                        $solicitacao['foto_solicitacao'] = $url;
                        break;
                    }
                }
            }
        }
        unset($solicitacao);

        return $solicitacoes;
    }

    private function documentoEhImagem(array $documento)
    {
        $mime = strtolower(trim((string)(isset($documento['mime_type']) ? $documento['mime_type'] : '')));
        if (in_array($mime, array('image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'), true)) {
            return true;
        }

        return (bool)preg_match('/\.(jpe?g|png|webp|gif)$/i', (string)(isset($documento['arquivo_path']) ? $documento['arquivo_path'] : ''));
    }
}
