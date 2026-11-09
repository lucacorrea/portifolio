<?php

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
header('Content-Type: application/json; charset=utf-8');
if ($id <= 0) {
    echo pc_json(array('ok' => false, 'error' => 'ID inválido.'));
    exit;
}
try {
    $data = $detalhesPessoaService->obter($id);
    if (!$data) {
        echo pc_json(array('ok' => false, 'error' => 'Pessoa não encontrada.'));
        exit;
    }

    if (!empty($data['solicitacoes']) && is_array($data['solicitacoes'])) {
        $canAssignBenefit = auth_can_assign_beneficio();
        $canViewAssignment = auth_can_view_beneficio_atribuicao();

        foreach ($data['solicitacoes'] as &$solicitacao) {
            if (!is_array($solicitacao)) {
                continue;
            }

            if (!$canAssignBenefit) {
                $solicitacao['atribuir_beneficio_url'] = '';
            }

            if (!$canViewAssignment) {
                // Não expõe valores, responsável, observação nem caminho de foto.
                $solicitacao['atribuicoes'] = array();
                continue;
            }

            // A foto de uma atribuição passa por endpoint protegido; o caminho físico
            // gravado no banco nunca é enviado ao navegador nesta tela.
            if (!empty($solicitacao['atribuicoes']) && is_array($solicitacao['atribuicoes'])) {
                $cpfPessoa = preg_replace('/\D+/', '', (string)($data['solicitante']['cpf'] ?? '')) ?? '';
                $solicitacaoId = isset($solicitacao['id']) ? (int)$solicitacao['id'] : 0;

                foreach ($solicitacao['atribuicoes'] as &$atribuicao) {
                    if (!is_array($atribuicao)) {
                        continue;
                    }
                    $entregaId = isset($atribuicao['id']) ? (int)$atribuicao['id'] : 0;
                    $temFoto = trim((string)($atribuicao['foto_path'] ?? '')) !== '';
                    $atribuicao['foto_path'] = ($entregaId > 0 && $temFoto)
                        ? 'verFotoAtribuicao.php?id=' . $entregaId
                        : '';

                    // A URL de edição só é entregue ao navegador quando o usuário
                    // realmente possui permissão para gerenciar benefícios (Secretário).
                    $atribuicao['editar_url'] = ($canAssignBenefit && $entregaId > 0 && $solicitacaoId > 0)
                        ? 'atribuirBeneficio.php?cpf=' . rawurlencode($cpfPessoa)
                            . '&solicitacao_id=' . $solicitacaoId
                            . '&entrega_id=' . $entregaId
                        : '';
                }
                unset($atribuicao);
            }
        }
        unset($solicitacao);
    }

    if (!isset($data['links']) || !is_array($data['links'])) {
        $data['links'] = array();
    }
    if (!auth_can_edit_solicitante()) {
        $data['links']['editar'] = '';
    }
    if (!auth_can_print_socioeconomico()) {
        $data['links']['folha_socioeconomica'] = '';
    }

    echo pc_json(array_merge(array('ok' => true), $data));
} catch (Throwable $e) {
    http_response_code(500);
    echo pc_json(array('ok' => false, 'error' => 'Falha ao carregar os detalhes.'));
}
exit;
