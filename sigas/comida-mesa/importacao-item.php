<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/frontend/modules/comida-mesa/lib/bootstrap.php';
require_once dirname(__DIR__) . '/frontend/modules/comida-mesa/lib/import.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$respond = static function (int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

try {
    if (!cm_can('comida_mesa.importar') && !cm_can('comida_mesa.cadastrar')) {
        $respond(403, ['ok' => false, 'message' => 'Acesso negado.']);
    }

    $itemId = max(0, (int) ($_GET['item_id'] ?? 0));
    $importId = max(0, (int) ($_GET['import_id'] ?? 0));
    if ($itemId < 1) {
        $respond(422, ['ok' => false, 'message' => 'Item da importação não informado.']);
    }

    $pdo = cm_db();
    $sql = 'SELECT
        item.*,
        imp.arquivo_nome,
        imp.criado_em AS importado_em,
        imp.status AS importacao_status,
        decisor.nome AS decisor_nome,
        p.nome AS pessoa_nome_oficial,
        p.cpf AS pessoa_cpf_oficial,
        p.nis AS pessoa_nis_oficial,
        p.rg AS pessoa_rg_oficial,
        p.data_nascimento AS pessoa_nascimento_oficial,
        p.telefone AS pessoa_telefone_oficial,
        p.email AS pessoa_email_oficial,
        f.codigo AS familia_codigo,
        f.zona AS familia_zona,
        f.logradouro AS familia_logradouro,
        f.numero AS familia_numero,
        f.complemento AS familia_complemento,
        f.bairro AS familia_bairro,
        f.comunidade AS familia_comunidade,
        f.ponto_referencia AS familia_referencia,
        f.cep AS familia_cep,
        f.quantidade_membros AS familia_membros,
        f.renda_familiar AS familia_renda,
        i.status AS inscricao_status,
        i.prioridade AS inscricao_prioridade,
        i.data_inscricao AS inscricao_data,
        i.data_aprovacao AS inscricao_aprovacao,
        i.observacao AS inscricao_observacao,
        polo.nome AS polo_nome
    FROM comida_mesa_importacao_itens item
    INNER JOIN comida_mesa_importacoes imp ON imp.id = item.importacao_id
    LEFT JOIN usuarios decisor ON decisor.id = item.decidido_por
    LEFT JOIN pessoas p ON p.id = item.pessoa_id
    LEFT JOIN familias f ON f.id = item.familia_id
    LEFT JOIN comida_mesa_inscricoes i ON i.id = item.inscricao_id
    LEFT JOIN comida_mesa_polos polo ON polo.id = i.polo_id
    WHERE item.id = :item_id';

    $params = ['item_id' => $itemId];
    if ($importId > 0) {
        $sql .= ' AND item.importacao_id = :import_id';
        $params['import_id'] = $importId;
    }
    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        $respond(404, ['ok' => false, 'message' => 'Registro da importação não localizado.']);
    }

    $decoded = cm_import_decode_item($row);
    $source = is_array($decoded['dados_origem'] ?? null) ? $decoded['dados_origem'] : [];
    $cross = is_array($decoded['cruzamento'] ?? null) ? $decoded['cruzamento'] : null;

    $cpfDigits = preg_replace('/\D+/', '', (string) ($row['cpf_validado'] ?: $row['cpf_informado'])) ?: '';
    $cpfOfficialDigits = preg_replace('/\D+/', '', (string) ($row['pessoa_cpf_oficial'] ?? '')) ?: '';

    $payload = [
        'id' => (int) $row['id'],
        'importacao' => [
            'id' => (int) $row['importacao_id'],
            'arquivo' => (string) $row['arquivo_nome'],
            'status' => (string) $row['importacao_status'],
            'linha' => (int) $row['linha'],
            'ordem' => (string) ($source['ordem_origem'] ?? ''),
            'importado_em' => (string) ($row['importado_em'] ?? ''),
        ],
        'identificacao' => [
            'nome' => (string) $row['nome'],
            'cpf_informado' => (string) ($row['cpf_informado'] ?? ''),
            'cpf_validado' => strlen($cpfDigits) === 11 ? $cpfDigits : '',
            'telefone_informado' => (string) ($row['telefone_informado'] ?? ''),
            'nis' => (string) ($source['nis'] ?? ''),
            'rg' => (string) ($source['rg'] ?? ''),
            'data_nascimento' => (string) ($source['data_nascimento'] ?? ''),
            'conjuge' => (string) ($source['conjuge_origem'] ?? ''),
            'email' => (string) ($source['email'] ?? ''),
        ],
        'endereco' => [
            'zona' => (string) ($source['zona'] ?? ''),
            'logradouro' => (string) ($source['logradouro'] ?? $source['endereco'] ?? ''),
            'numero' => (string) ($source['numero'] ?? ''),
            'complemento' => (string) ($source['complemento'] ?? ''),
            'bairro' => (string) ($source['bairro'] ?? ''),
            'comunidade' => (string) ($source['comunidade'] ?? ''),
            'referencia' => (string) ($source['ponto_referencia'] ?? ''),
            'cep' => (string) ($source['cep'] ?? ''),
            'local_origem' => (string) ($source['local_origem'] ?? $row['polo_informado'] ?? ''),
        ],
        'programa' => [
            'situacao' => (string) $row['situacao_programa'],
            'classificacao' => (string) ($row['classificacao'] ?? ''),
            'motivos' => (string) ($row['motivos'] ?? ''),
            'efetivacao_status' => (string) ($row['efetivacao_status'] ?? ''),
            'efetivacao_motivo' => (string) ($row['efetivacao_motivo'] ?? ''),
            'decidido_em' => (string) ($row['decidido_em'] ?? ''),
            'decisor' => (string) ($row['decisor_nome'] ?? ''),
            'polo_informado' => (string) ($row['polo_informado'] ?? ''),
        ],
        'vinculo_oficial' => [
            'pessoa_id' => $row['pessoa_id'] !== null ? (int) $row['pessoa_id'] : null,
            'familia_id' => $row['familia_id'] !== null ? (int) $row['familia_id'] : null,
            'inscricao_id' => $row['inscricao_id'] !== null ? (int) $row['inscricao_id'] : null,
            'nome' => (string) ($row['pessoa_nome_oficial'] ?? ''),
            'cpf' => strlen($cpfOfficialDigits) === 11 ? $cpfOfficialDigits : '',
            'nis' => (string) ($row['pessoa_nis_oficial'] ?? ''),
            'rg' => (string) ($row['pessoa_rg_oficial'] ?? ''),
            'data_nascimento' => (string) ($row['pessoa_nascimento_oficial'] ?? ''),
            'telefone' => (string) ($row['pessoa_telefone_oficial'] ?? ''),
            'email' => (string) ($row['pessoa_email_oficial'] ?? ''),
            'familia_codigo' => (string) ($row['familia_codigo'] ?? ''),
            'zona' => (string) ($row['familia_zona'] ?? ''),
            'logradouro' => (string) ($row['familia_logradouro'] ?? ''),
            'numero' => (string) ($row['familia_numero'] ?? ''),
            'complemento' => (string) ($row['familia_complemento'] ?? ''),
            'bairro' => (string) ($row['familia_bairro'] ?? ''),
            'comunidade' => (string) ($row['familia_comunidade'] ?? ''),
            'referencia' => (string) ($row['familia_referencia'] ?? ''),
            'cep' => (string) ($row['familia_cep'] ?? ''),
            'membros' => $row['familia_membros'] !== null ? (int) $row['familia_membros'] : null,
            'renda' => $row['familia_renda'] !== null ? (float) $row['familia_renda'] : null,
            'inscricao_status' => (string) ($row['inscricao_status'] ?? ''),
            'prioridade' => (string) ($row['inscricao_prioridade'] ?? ''),
            'data_inscricao' => (string) ($row['inscricao_data'] ?? ''),
            'data_aprovacao' => (string) ($row['inscricao_aprovacao'] ?? ''),
            'observacao' => (string) ($row['inscricao_observacao'] ?? ''),
            'polo' => (string) ($row['polo_nome'] ?? ''),
        ],
        'cruzamento' => $cross,
    ];

    $respond(200, ['ok' => true, 'item' => $payload]);
} catch (Throwable $e) {
    $respond(500, ['ok' => false, 'message' => 'Não foi possível carregar os dados completos deste registro.']);
}
