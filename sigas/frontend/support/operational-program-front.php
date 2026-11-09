<?php

declare(strict_types=1);

/**
 * Enriquece as views existentes sem duplicar dezenas de arquivos. Os dados abaixo
 * são exclusivamente de composição visual enquanto o backend de domínio de cada
 * programa não estiver conectado; o banner de demonstração permanece visível.
 *
 * @param array<string,mixed> $definition
 * @return array<string,mixed>
 */
function sigas_operational_front_enhance(string $moduleKey, string $pageKey, array $definition): array
{
    $catalog = sigas_operational_front_catalog();
    $page = $catalog[$moduleKey][$pageKey] ?? [];

    if (($definition['stats'] ?? []) === [] && isset($page['stats']) && is_array($page['stats'])) {
        $definition['stats'] = $page['stats'];
    }

    if (($definition['filters'] ?? []) === [] && isset($page['filters']) && is_array($page['filters'])) {
        $definition['filters'] = $page['filters'];
    }

    if (isset($page['action']) && is_array($page['action']) && isset($definition['actions']) && is_array($definition['actions'])) {
        foreach ($definition['actions'] as &$action) {
            if (!is_array($action) || trim((string) ($action['href'] ?? '')) !== '') {
                continue;
            }

            $label = mb_strtolower(trim((string) ($action['label'] ?? '')));
            if ($label !== 'nova ação') {
                continue;
            }

            $action['label'] = (string) ($page['action']['label'] ?? $action['label']);
            $action['icon'] = (string) ($page['action']['icon'] ?? $action['icon'] ?? 'plus-circle');
        }
        unset($action);
    }

    $definition['show_states'] = false;

    return $definition;
}

/** @return array<string,mixed> */
function sigas_operational_front_catalog(): array
{
    $stat = static fn (string $label, string $value, string $detail, string $icon): array => [
        'label' => $label,
        'value' => $value,
        'detail' => $detail,
        'icon' => $icon,
    ];

    $filter = static fn (string $label, array $options): array => [
        'label' => $label,
        'options' => $options,
    ];

    return [
        'kit-maternidade' => [
            'beneficiarias' => [
                'stats' => [
                    $stat('Em acompanhamento', '42', 'Gestantes com fluxo ativo', 'person-hearts'),
                    $stat('Aptas', '18', 'Liberadas para contemplação', 'check2-circle'),
                    $stat('Com pendência', '7', 'Exigem ação da equipe', 'exclamation-circle'),
                    $stat('DPP em 30 dias', '9', 'Prioridade de acompanhamento', 'calendar-heart'),
                ],
                'filters' => [
                    $filter('Etapa', ['Triagem', 'Acompanhamento', 'Avaliação', 'Aguardando Kit', 'Pós-parto']),
                    $filter('Situação', ['Regular', 'Pendente', 'Prioridade', 'Concluída']),
                ],
            ],
            'cadastro' => [
                'action' => ['label' => 'Iniciar cadastro', 'icon' => 'person-plus'],
                'stats' => [
                    $stat('Triagens hoje', '6', 'Entradas em conferência', 'clipboard2-pulse'),
                    $stat('Novos cadastros', '3', 'Sem registro anterior', 'person-plus'),
                    $stat('Vínculos encontrados', '2', 'Pessoa localizada no SIGAS', 'link-45deg'),
                    $stat('Com pendência', '1', 'Necessita complemento', 'exclamation-triangle'),
                ],
                'filters' => [
                    $filter('Origem', ['SIGAS', 'ANEXO', 'Novo']),
                    $filter('Resultado', ['Vincular', 'Conferir dados', 'Completar cadastro', 'Pendente']),
                ],
            ],
            'visitas' => [
                'action' => ['label' => 'Registrar visita', 'icon' => 'house-check'],
                'stats' => [
                    $stat('Agendadas', '12', 'Próximos 7 dias', 'calendar3'),
                    $stat('Realizadas', '38', 'No mês atual', 'house-check'),
                    $stat('Reagendadas', '4', 'Aguardam nova data', 'calendar2-week'),
                    $stat('Não localizadas', '3', 'Demandam busca ativa', 'geo-alt'),
                ],
                'filters' => [
                    $filter('Resultado', ['Realizada', 'Reagendada', 'Não localizada', 'Recusada']),
                    $filter('Próxima ação', ['Nova visita', 'Reunião', 'Avaliação', 'Sem pendência']),
                ],
            ],
            'reunioes' => [
                'action' => ['label' => 'Registrar atividade', 'icon' => 'people'],
                'stats' => [
                    $stat('Atividades no mês', '8', 'Reuniões e orientações', 'people'),
                    $stat('Participações', '64', 'Presenças registradas', 'person-check'),
                    $stat('Ausências', '11', 'Com e sem justificativa', 'person-x'),
                    $stat('Próxima atividade', '14/09', 'Agenda do programa', 'calendar-event'),
                ],
                'filters' => [
                    $filter('Participação', ['Presente', 'Ausente', 'Ausência justificada']),
                    $filter('Tipo', ['Reunião', 'Orientação', 'Oficina', 'Atividade coletiva']),
                ],
            ],
            'avaliacao' => [
                'action' => ['label' => 'Registrar avaliação', 'icon' => 'clipboard2-check'],
                'stats' => [
                    $stat('Aguardando análise', '10', 'Fila de avaliação', 'hourglass-split'),
                    $stat('Aptas', '18', 'Parecer favorável', 'check-circle'),
                    $stat('Pendentes', '5', 'Complementação necessária', 'exclamation-diamond'),
                    $stat('Não aptas', '3', 'Decisão fundamentada', 'x-circle'),
                ],
                'filters' => [
                    $filter('Resultado', ['Apta', 'Pendente', 'Não apta']),
                    $filter('Pendência', ['Documentação', 'Acompanhamento', 'Critério', 'Sem pendência']),
                ],
            ],
            'entregas' => [
                'action' => ['label' => 'Registrar entrega', 'icon' => 'gift'],
                'stats' => [
                    $stat('Aguardando entrega', '14', 'Kits já autorizados', 'gift'),
                    $stat('Entregues no mês', '21', 'Termos registrados', 'box2-heart'),
                    $stat('Reservados', '8', 'Com lote definido', 'box-seam'),
                    $stat('Prioridade', '4', 'DPP próxima', 'exclamation-circle'),
                ],
                'filters' => [
                    $filter('Situação', ['Aguardando', 'Reservado', 'Entregue', 'Ocorrência']),
                    $filter('Comprovante', ['Com termo', 'Pendente de termo', 'Com ocorrência']),
                ],
            ],
            'pos-parto' => [
                'action' => ['label' => 'Registrar pós-parto', 'icon' => 'heart-pulse'],
                'stats' => [
                    $stat('Pós-parto ativo', '9', 'Acompanhamentos abertos', 'heart-pulse'),
                    $stat('Nascimentos no mês', '12', 'Registros informados', 'balloon-heart'),
                    $stat('Concluídos', '19', 'Fluxos encerrados', 'check2-all'),
                    $stat('Sem entrega', '2', 'Exigem justificativa', 'exclamation-triangle'),
                ],
                'filters' => [
                    $filter('Kit', ['Entregue antes do parto', 'Entregue após o parto', 'Não entregue']),
                    $filter('Fluxo', ['Ativo', 'Concluído', 'Encerrado com justificativa']),
                ],
            ],
            'relatorios' => [
                'filters' => [
                    $filter('Período', ['Mês atual', 'Últimos 3 meses', 'Ano atual', 'Personalizado']),
                    $filter('Abrangência', ['Geral', 'Por território', 'Por etapa', 'Prioridades']),
                ],
            ],
        ],
        'aluguel-social' => [
            'beneficiarios' => [
                'stats' => [
                    $stat('Ativos', '87', 'Concessões vigentes', 'house-check'),
                    $stat('Reavaliação', '9', 'Vencem em até 30 dias', 'arrow-repeat'),
                    $stat('Com pendência', '6', 'Administrativa ou financeira', 'exclamation-circle'),
                    $stat('Encerrados no mês', '4', 'Benefícios finalizados', 'house-x'),
                ],
                'filters' => [
                    $filter('Situação', ['Ativo', 'Reavaliação', 'Pendente', 'Suspenso', 'Encerrado']),
                    $filter('Vigência', ['Até 30 dias', '31 a 90 dias', 'Mais de 90 dias']),
                ],
            ],
            'solicitacoes' => [
                'action' => ['label' => 'Nova solicitação', 'icon' => 'house-add'],
                'stats' => [
                    $stat('Novas', '8', 'Entradas nesta semana', 'house-add'),
                    $stat('Em triagem', '12', 'Conferência inicial', 'clipboard2-pulse'),
                    $stat('Prioritárias', '5', 'Risco ou desabrigamento', 'exclamation-triangle'),
                    $stat('Para vistoria', '11', 'Aguardam agenda técnica', 'house-gear'),
                ],
                'filters' => [
                    $filter('Motivo', ['Desabrigamento', 'Risco estrutural', 'Sinistro', 'Violência/risco', 'Outro']),
                    $filter('Prioridade', ['Normal', 'Prioritária', 'Urgente']),
                ],
            ],
            'vistorias' => [
                'action' => ['label' => 'Registrar vistoria', 'icon' => 'house-gear'],
                'stats' => [
                    $stat('Agendadas', '11', 'Fila atual', 'calendar3'),
                    $stat('Realizadas', '17', 'No mês', 'house-check'),
                    $stat('Risco confirmado', '8', 'Seguem para parecer', 'exclamation-diamond'),
                    $stat('Nova avaliação', '3', 'Necessitam retorno', 'arrow-repeat'),
                ],
                'filters' => [
                    $filter('Resultado', ['Risco confirmado', 'Risco não confirmado', 'Nova avaliação']),
                    $filter('Agenda', ['Hoje', 'Próximos 7 dias', 'Atrasada', 'Concluída']),
                ],
            ],
            'pareceres' => [
                'action' => ['label' => 'Registrar parecer', 'icon' => 'clipboard2-check'],
                'stats' => [
                    $stat('Aguardando parecer', '7', 'Processos aptos à análise', 'hourglass-split'),
                    $stat('Deferidos', '13', 'No mês atual', 'check-circle'),
                    $stat('Indeferidos', '4', 'Com fundamentação', 'x-circle'),
                    $stat('Pendência', '3', 'Retornaram para complemento', 'arrow-return-left'),
                ],
                'filters' => [
                    $filter('Decisão', ['Aguardando', 'Deferido', 'Indeferido', 'Pendente']),
                    $filter('Prioridade', ['Normal', 'Prioritária', 'Urgente']),
                ],
            ],
            'concessoes' => [
                'action' => ['label' => 'Nova concessão', 'icon' => 'key'],
                'stats' => [
                    $stat('Concessões ativas', '87', 'Benefícios vigentes', 'key'),
                    $stat('Iniciadas no mês', '10', 'Novos termos', 'file-earmark-check'),
                    $stat('Vencem em 30 dias', '9', 'Exigem reavaliação', 'calendar-x'),
                    $stat('Valor mensal', 'R$ 51,8 mil', 'Total demonstrativo', 'cash-stack'),
                ],
                'filters' => [
                    $filter('Situação', ['Ativa', 'Aguardando início', 'Próxima do fim', 'Suspensa', 'Encerrada']),
                    $filter('Valor', ['Até R$ 500', 'R$ 501 a R$ 650', 'Acima de R$ 650']),
                ],
            ],
            'pagamentos' => [
                'action' => ['label' => 'Registrar pagamento', 'icon' => 'wallet2'],
                'stats' => [
                    $stat('Competências abertas', '87', 'Mês atual', 'calendar2-check'),
                    $stat('Pagas', '72', 'Confirmadas', 'check2-circle'),
                    $stat('Pendentes', '11', 'Aguardam providência', 'hourglass'),
                    $stat('Suspensas', '4', 'Com bloqueio financeiro', 'pause-circle'),
                ],
                'filters' => [
                    $filter('Situação', ['Previsto', 'Autorizado', 'Pago', 'Pendente', 'Suspenso']),
                    $filter('Competência', ['Mês atual', 'Mês anterior', 'Em atraso']),
                ],
            ],
            'reavaliacoes' => [
                'action' => ['label' => 'Registrar reavaliação', 'icon' => 'arrow-repeat'],
                'stats' => [
                    $stat('A vencer', '9', 'Próximos 30 dias', 'calendar-x'),
                    $stat('Renovadas', '6', 'No mês atual', 'arrow-repeat'),
                    $stat('Encerradas', '4', 'Após reavaliação', 'x-circle'),
                    $stat('Pendentes', '3', 'Aguardam documentação', 'exclamation-circle'),
                ],
                'filters' => [
                    $filter('Resultado', ['Aguardando', 'Renovar', 'Manter', 'Suspender', 'Encerrar']),
                    $filter('Prazo', ['Vencida', 'Até 15 dias', '16 a 30 dias', 'Mais de 30 dias']),
                ],
            ],
            'relatorios' => [
                'filters' => [
                    $filter('Período', ['Mês atual', 'Trimestre', 'Ano atual', 'Personalizado']),
                    $filter('Visão', ['Concessões', 'Pagamentos', 'Vistorias', 'Reavaliações']),
                ],
            ],
        ],
        'beneficios-eventuais' => [
            'solicitacoes' => [
                'action' => ['label' => 'Nova solicitação', 'icon' => 'inboxes'],
                'stats' => [
                    $stat('Novas hoje', '9', 'Demandas registradas', 'inbox'),
                    $stat('Em triagem', '14', 'Aguardam conferência', 'clipboard2-pulse'),
                    $stat('Prioritárias', '5', 'Atendimento prioritário', 'exclamation-triangle'),
                    $stat('Concluídas no mês', '63', 'Fluxos finalizados', 'check2-all'),
                ],
                'filters' => [
                    $filter('Benefício', ['Auxílio natalidade', 'Auxílio funeral', 'Cesta/alimento', 'Passagem', 'Documento', 'Outro']),
                    $filter('Prioridade', ['Normal', 'Prioritária', 'Urgente']),
                ],
            ],
            'triagem' => [
                'action' => ['label' => 'Registrar triagem', 'icon' => 'clipboard2-pulse'],
                'stats' => [
                    $stat('Na fila', '14', 'Aguardam conferência', 'hourglass-split'),
                    $stat('Completas', '31', 'No mês atual', 'check-circle'),
                    $stat('Pendentes', '7', 'Documentação/cadastro', 'exclamation-circle'),
                    $stat('Para análise', '18', 'Triagem concluída', 'arrow-right-circle'),
                ],
                'filters' => [
                    $filter('Cadastro', ['Conferido', 'Com pendência', 'Não localizado']),
                    $filter('Documentação', ['Completa', 'Incompleta', 'Dispensada conforme regra']),
                ],
            ],
            'analises' => [
                'action' => ['label' => 'Registrar análise', 'icon' => 'clipboard2-check'],
                'stats' => [
                    $stat('Aguardando análise', '18', 'Fila atual', 'hourglass-split'),
                    $stat('Deferidos', '29', 'No mês', 'check-circle'),
                    $stat('Indeferidos', '6', 'Com parecer', 'x-circle'),
                    $stat('Retornados', '4', 'Para pendência', 'arrow-return-left'),
                ],
                'filters' => [
                    $filter('Decisão', ['Aguardando', 'Deferido', 'Indeferido', 'Retornado']),
                    $filter('Benefício', ['Natalidade', 'Funeral', 'Alimento', 'Passagem', 'Documento', 'Outro']),
                ],
            ],
            'concessoes' => [
                'action' => ['label' => 'Nova concessão', 'icon' => 'check2-circle'],
                'stats' => [
                    $stat('Autorizadas', '22', 'Aguardam efetivação', 'check2-circle'),
                    $stat('Efetivadas', '47', 'No mês', 'box-seam'),
                    $stat('Vencem hoje', '3', 'Autorizações com prazo', 'clock-history'),
                    $stat('Com ocorrência', '2', 'Exigem análise', 'exclamation-circle'),
                ],
                'filters' => [
                    $filter('Situação', ['Autorizada', 'Efetivada', 'Vencida', 'Cancelada']),
                    $filter('Tipo', ['Natalidade', 'Funeral', 'Alimento', 'Passagem', 'Documento', 'Outro']),
                ],
            ],
            'entregas' => [
                'action' => ['label' => 'Registrar entrega', 'icon' => 'box-seam'],
                'stats' => [
                    $stat('Aguardando', '22', 'Benefícios autorizados', 'box-seam'),
                    $stat('Entregues hoje', '8', 'Efetivações registradas', 'check2-square'),
                    $stat('No mês', '47', 'Total de entregas', 'calendar-check'),
                    $stat('Ocorrências', '2', 'Não entrega/recusa', 'exclamation-triangle'),
                ],
                'filters' => [
                    $filter('Situação', ['Aguardando', 'Entregue', 'Não entregue', 'Recusado']),
                    $filter('Comprovante', ['Completo', 'Pendente', 'Com ocorrência']),
                ],
            ],
            'tipos' => [
                'action' => ['label' => 'Novo tipo de benefício', 'icon' => 'sliders'],
                'stats' => [
                    $stat('Tipos ativos', '6', 'Regras operacionais', 'sliders'),
                    $stat('Com documento', '5', 'Exigem comprovação', 'file-earmark-check'),
                    $stat('Eventuais', '4', 'Sem periodicidade fixa', 'calendar2-event'),
                    $stat('Inativos', '1', 'Mantido para histórico', 'archive'),
                ],
                'filters' => [
                    $filter('Situação', ['Ativo', 'Inativo']),
                    $filter('Periodicidade', ['Única', 'Mensal', 'Trimestral', 'Eventual']),
                ],
            ],
            'relatorios' => [
                'filters' => [
                    $filter('Período', ['Hoje', 'Mês atual', 'Trimestre', 'Ano atual', 'Personalizado']),
                    $filter('Visão', ['Solicitações', 'Concessões', 'Entregas', 'Tipos de benefício']),
                ],
            ],
        ],
    ];
}
