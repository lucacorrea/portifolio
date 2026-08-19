<?php

declare(strict_types=1);

use App\DTO\ComidaMesaFilter;

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/frontend/modules/comida-mesa/lib/bootstrap.php';
require_once dirname(__DIR__) . '/frontend/modules/comida-mesa/lib/repository.php';
require_once dirname(__DIR__) . '/frontend/modules/comida-mesa/lib/excel.php';

cm_require('comida_mesa.visualizar');

$app = cm_app();
$repository = $app['repository'];
$service = $app['service'];
$moduleRepository = new ComidaMesaModuleRepository(cm_db());
$type = trim((string) ($_GET['tipo'] ?? 'beneficiarios'));
$allowed = ['beneficiarios', 'relatorio', 'competencias', 'polos', 'documentos', 'historico'];

if (!in_array($type, $allowed, true)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Tipo de exportação inválido.');
}

try {
    $excel = new ComidaMesaExcelExporter();
    $timestamp = date('Ymd-His');

    if ($type === 'beneficiarios') {
        $requested = $service->buildFilter($_GET);
        $competence = $service->resolveCompetence($requested->competenceId);
        $filter = new ComidaMesaFilter(
            $requested->search,
            $competence === null ? null : (int) $competence['id'],
            $requested->programStatus,
            $requested->deliveryStatus,
            $requested->zone,
            $requested->district,
            $requested->community,
            $requested->poleId,
            1,
        );
        $rows = $repository->exportRegistrations($filter);
        $competenceLabel = $competence
            ? cm_month_label((int) $competence['mes'], (int) $competence['ano'])
            : 'Sem competência selecionada';

        $data = [];
        foreach ($rows as $row) {
            $delivery = $service->deliveryStatusForRow($row, $competence);
            $data[] = [
                (string) ($row['familia_codigo'] ?? ''),
                (string) ($row['responsavel_nome'] ?? ''),
                cm_format_cpf($row['cpf'] ?? ''),
                (string) ($row['nis'] ?? ''),
                (string) ($row['rg'] ?? ''),
                cm_date($row['data_nascimento'] ?? null),
                (string) ($row['telefone'] ?? ''),
                (string) ($row['email'] ?? ''),
                (string) ($row['zona'] ?? ''),
                (string) ($row['bairro'] ?? ''),
                (string) ($row['comunidade'] ?? ''),
                trim(implode(', ', array_filter([
                    $row['logradouro'] ?? null,
                    $row['numero'] ?? null,
                    $row['complemento'] ?? null,
                ], static fn ($v): bool => trim((string) $v) !== ''))),
                (string) ($row['cep'] ?? ''),
                (int) ($row['quantidade_membros'] ?? 0),
                (float) ($row['renda_familiar'] ?? 0),
                (string) ($row['polo_nome'] ?? 'Sem polo'),
                $service->programStatusLabel((string) ($row['inscricao_status'] ?? '')),
                ucfirst((string) ($row['prioridade'] ?? 'normal')),
                cm_date($row['data_inscricao'] ?? null),
                (string) ($delivery['label'] ?? 'Não disponível'),
                cm_date($delivery['delivered_at'] ?? null, true),
                (string) ($row['recebedor_nome'] ?? ''),
                (string) ($row['entrega_operador_nome'] ?? ''),
                (string) ($row['motivo_suspensao'] ?? ''),
                (string) ($row['observacao'] ?? ''),
                cm_date($row['atualizado_em'] ?? $row['data_inscricao'] ?? null, true),
            ];
        }

        $subtitle = 'Competência: ' . $competenceLabel
            . ' | Registros: ' . number_format(count($data), 0, ',', '.')
            . ' | Gerado em ' . date('d/m/Y H:i');

        $excel->addSheet(
            'Beneficiários',
            'COARI COMIDA NA MESA — BENEFICIÁRIOS',
            $subtitle,
            [
                'Código', 'Responsável familiar', 'CPF', 'NIS', 'RG', 'Nascimento', 'Telefone', 'E-mail',
                'Zona', 'Bairro', 'Comunidade', 'Endereço', 'CEP', 'Membros', 'Renda familiar', 'Polo',
                'Situação no programa', 'Prioridade', 'Data da inscrição', 'Situação da entrega', 'Data da entrega',
                'Recebedor', 'Operador da entrega', 'Motivo de suspensão', 'Observação', 'Atualização',
            ],
            $data,
            [
                'text','text','text','text','text','text','text','text','text','text','text','text','text','number','currency','text',
                'text','text','text','text','text','text','text','text','text','text',
            ]
        )->download('comida-na-mesa-beneficiarios-' . $timestamp . '.xlsx');
    }

    if ($type === 'competencias') {
        $filters = [
            'ano' => trim((string) ($_GET['ano'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];
        $rows = $moduleRepository->competences($filters);
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                cm_month_label((int) $row['mes'], (int) $row['ano']),
                cm_date($row['inicio_entregas'] ?? null),
                cm_date($row['fim_entregas'] ?? null),
                cm_competence_label((string) $row['status']),
                (int) $row['entregas'],
                (int) $row['canceladas'],
                (int) $row['polos_com_entrega'],
                (string) ($row['observacao'] ?? ''),
                cm_date($row['criado_em'] ?? null, true),
            ];
        }
        $excel->addSheet(
            'Competências',
            'COARI COMIDA NA MESA — COMPETÊNCIAS',
            'Registros: ' . count($data) . ' | Gerado em ' . date('d/m/Y H:i'),
            ['Competência','Início','Fim','Situação','Entregas','Canceladas','Polos atendidos','Observação','Cadastro'],
            $data,
            ['text','text','text','text','number','number','number','text','text']
        )->download('comida-na-mesa-competencias-' . $timestamp . '.xlsx');
    }

    if ($type === 'polos') {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'ativo' => isset($_GET['ativo']) ? trim((string) $_GET['ativo']) : '',
        ];
        $rows = $moduleRepository->poles($filters);
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                (string) $row['nome'],
                (string) $row['slug'],
                (string) ($row['endereco'] ?? ''),
                (int) $row['familias'],
                (int) $row['beneficiarias_ativas'],
                (int) $row['ativo'] === 1 ? 'Ativo' : 'Inativo',
                cm_date($row['atualizado_em'] ?? $row['criado_em'] ?? null, true),
            ];
        }
        $excel->addSheet(
            'Polos',
            'COARI COMIDA NA MESA — POLOS',
            'Registros: ' . count($data) . ' | Gerado em ' . date('d/m/Y H:i'),
            ['Polo','Slug','Endereço/localização','Famílias vinculadas','Beneficiárias ativas','Situação','Atualização'],
            $data,
            ['text','text','text','number','number','text','text']
        )->download('comida-na-mesa-polos-' . $timestamp . '.xlsx');
    }

    if ($type === 'documentos') {
        cm_require('comida_mesa.documentos_visualizar');
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'tipo' => trim((string) ($_GET['tipo'] ?? '')),
            'polo_id' => trim((string) ($_GET['polo_id'] ?? '')),
        ];
        $rows = $moduleRepository->documents($filters, 10000);
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                (string) $row['familia_codigo'],
                (string) $row['responsavel_nome'],
                cm_format_cpf($row['cpf'] ?? ''),
                (string) $row['tipo'],
                (string) $row['nome_original'],
                (string) ($row['descricao'] ?? ''),
                (string) ($row['mime_type'] ?? ''),
                (int) ($row['tamanho'] ?? 0),
                (string) ($row['polo_nome'] ?? 'Sem polo'),
                (string) ($row['enviado_por_nome'] ?? 'Não informado'),
                cm_date($row['criado_em'] ?? null, true),
            ];
        }
        $excel->addSheet(
            'Documentos',
            'COARI COMIDA NA MESA — DOCUMENTOS',
            'Registros: ' . count($data) . ' | Gerado em ' . date('d/m/Y H:i'),
            ['Família','Responsável','CPF','Tipo','Arquivo','Descrição','MIME','Tamanho (bytes)','Polo','Enviado por','Data/hora'],
            $data,
            ['text','text','text','text','text','text','text','number','text','text','text']
        )->download('comida-na-mesa-documentos-' . $timestamp . '.xlsx');
    }

    if ($type === 'historico') {
        cm_require('comida_mesa.historico_visualizar');
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'acao' => trim((string) ($_GET['acao'] ?? '')),
            'data_inicio' => trim((string) ($_GET['data_inicio'] ?? '')),
            'data_fim' => trim((string) ($_GET['data_fim'] ?? '')),
        ];
        $rows = $moduleRepository->histories($filters, 10000);
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                cm_date($row['criado_em'] ?? null, true),
                (string) $row['familia_codigo'],
                (string) $row['responsavel_nome'],
                (string) $row['acao'],
                (string) ($row['descricao'] ?? ''),
                (string) ($row['usuario_nome'] ?? 'Sistema'),
            ];
        }
        $excel->addSheet(
            'Histórico',
            'COARI COMIDA NA MESA — HISTÓRICO',
            'Registros: ' . count($data) . ' | Gerado em ' . date('d/m/Y H:i'),
            ['Data/hora','Família','Responsável','Ação','Descrição','Operador'],
            $data,
            ['text','text','text','text','text','text']
        )->download('comida-na-mesa-historico-' . $timestamp . '.xlsx');
    }

    if ($type === 'relatorio') {
        $selectedCompetenceId = isset($_GET['competencia_id']) && preg_match('/^\d+$/', (string) $_GET['competencia_id']) === 1
            ? (int) $_GET['competencia_id']
            : null;
        if ($selectedCompetenceId === null) {
            $default = $moduleRepository->defaultCompetence();
            $selectedCompetenceId = $default ? (int) $default['id'] : null;
        }
        $selectedCompetence = $selectedCompetenceId !== null ? $repository->findCompetenceById($selectedCompetenceId) : null;
        $competenceLabel = $selectedCompetence
            ? cm_month_label((int) $selectedCompetence['mes'], (int) $selectedCompetence['ano'])
            : 'Sem competência';
        $report = $moduleRepository->reportOverview($selectedCompetenceId);
        $stats = $report['stats'];

        $summaryRows = [
            ['Competência', $competenceLabel],
            ['Inscrições', (int) ($stats['inscricoes'] ?? 0)],
            ['Beneficiárias ativas', (int) ($stats['ativas'] ?? 0)],
            ['Em análise', (int) ($stats['em_analise'] ?? 0)],
            ['Lista de espera', (int) ($stats['lista_espera'] ?? 0)],
            ['Restrições', (int) ($stats['restricoes'] ?? 0)],
            ['Entregas', (int) ($stats['entregas'] ?? 0)],
            ['Aguardando retirada', (int) ($stats['aguardando'] ?? 0)],
            ['Execução (%)', (float) ($stats['execucao_percentual'] ?? 0)],
            ['Polos ativos', (int) ($stats['polos_ativos'] ?? 0)],
            ['Documentos', (int) ($stats['documentos'] ?? 0)],
            ['Eventos de histórico', (int) ($stats['eventos'] ?? 0)],
        ];
        $excel->addSheet(
            'Resumo',
            'COARI COMIDA NA MESA — RELATÓRIO GERENCIAL',
            'Competência: ' . $competenceLabel . ' | Gerado em ' . date('d/m/Y H:i'),
            ['Indicador','Valor'],
            $summaryRows,
            ['text','text']
        );

        $poleRows = [];
        foreach ($report['poles'] as $row) {
            $ativas = (int) $row['ativas'];
            $entregas = (int) $row['entregas'];
            $coverage = $ativas > 0 ? round(($entregas / $ativas) * 100, 2) : 0;
            $poleRows[] = [
                (string) $row['nome'],
                (int) $row['familias'],
                $ativas,
                $entregas,
                $coverage,
                (int) $row['ativo'] === 1 ? 'Ativo' : 'Inativo',
            ];
        }
        $excel->addSheet('Polos', 'EXECUÇÃO POR POLO', 'Competência: ' . $competenceLabel, ['Polo','Famílias','Beneficiárias ativas','Entregas','Cobertura (%)','Situação'], $poleRows, ['text','number','number','number','percent','text']);

        $programRows = array_map(static fn (array $r): array => [(string) $r['label'], (int) $r['value']], $report['status']);
        $excel->addSheet('Situação Programa', 'SITUAÇÃO DAS INSCRIÇÕES', 'Base consolidada do programa', ['Situação','Quantidade'], $programRows, ['text','number']);

        $deliveryRows = array_map(static fn (array $r): array => [(string) $r['label'], (int) $r['value']], $report['delivery']);
        $excel->addSheet('Entregas', 'SITUAÇÃO DAS ENTREGAS', 'Competência: ' . $competenceLabel, ['Situação','Quantidade'], $deliveryRows, ['text','number']);

        $monthlyRows = array_map(static fn (array $r): array => [(string) $r['label'], (int) $r['value']], $report['monthly']);
        $excel->addSheet('Evolução', 'ENTREGAS POR COMPETÊNCIA', 'Histórico das últimas competências', ['Competência','Entregas'], $monthlyRows, ['text','number']);

        $districtRows = array_map(static fn (array $r): array => [(string) $r['label'], (int) $r['value']], $report['districts']);
        $excel->addSheet('Bairros', 'DISTRIBUIÇÃO POR BAIRRO', 'Principais bairros da base', ['Bairro','Famílias'], $districtRows, ['text','number']);

        $zoneRows = array_map(static fn (array $r): array => [(string) $r['label'], (int) $r['value']], $report['zones']);
        $excel->addSheet('Zonas', 'DISTRIBUIÇÃO POR ZONA', 'Famílias por zona', ['Zona','Famílias'], $zoneRows, ['text','number']);

        $excel->download('comida-na-mesa-relatorio-' . $timestamp . '.xlsx');
    }
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Não foi possível gerar o arquivo Excel. Tente novamente ou contate o suporte.');
}
