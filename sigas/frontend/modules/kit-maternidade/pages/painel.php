<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
ob_start();
?>
<section class="content-card mt-3"><div class="card-heading"><div><div class="card-kicker">Cadastro central</div><h2>Histórico socioassistencial e reaproveitamento de dados</h2><p>Antes de criar um novo cadastro, o módulo consulta a pessoa no SIGAS e o histórico resumido do ANEXO.</p></div><span class="status-badge status-info"><i class="bi bi-diagram-3"></i>Integração controlada</span></div><div class="program-summary-grid"><div class="program-history-card"><h3>Exemplo de visão consolidada</h3><p>Somente informações necessárias para avaliação; cada sistema continua proprietário dos próprios registros.</p><div class="program-history-list"><div class="program-history-row"><strong>SIGAS</strong><span>Comida na Mesa — Ativo</span><span>Desde 02/2026</span></div><div class="program-history-row"><strong>ANEXO</strong><span>Cesta básica — Entregue</span><span>15/05/2026</span></div><div class="program-history-row"><strong>SIGAS</strong><span>Aluguel Social — Não possui</span><span>—</span></div></div></div><div class="program-callout"><i class="bi bi-person-down"></i><div><strong>Puxar dados para agilizar</strong><span>Nome, CPF, NIS, telefone, endereço e bairro podem ser trazidos para conferência sem copiar pareceres ou dados protegidos.</span><button class="btn btn-light btn-sm mt-2" type="button" data-demo-action="Abrir conferência de dados">Puxar dados</button></div></div></div></section>
<?php
$pageCustomContent = (string) ob_get_clean();
return sigas_frontend_page([
    'title' => 'Kit Maternidade',
    'description' => 'Jornada completa da gestante: cadastro, acompanhamento, avaliação, entrega e encerramento pós-parto.',
    'actions' => [
    [
        'label' => 'Nova gestante',
        'icon' => 'person-plus',
        'primary' => true,
        'href' => 'setor.php?ambiente=kit-maternidade&pagina=cadastro'
    ],
    [
        'label' => 'Pesquisar pessoa',
        'icon' => 'search',
        'href' => 'setor.php?ambiente=kit-maternidade&pagina=beneficiarias'
    ]
],
    'stats' => [
    [
        'label' => 'Gestantes ativas',
        'value' => '245',
        'detail' => 'Em algum estágio do fluxo',
        'icon' => 'person-hearts'
    ],
    [
        'label' => 'Em acompanhamento',
        'value' => '172',
        'detail' => 'Visitas e reuniões ativas',
        'icon' => 'clipboard2-pulse'
    ],
    [
        'label' => 'Aguardando kit',
        'value' => '21',
        'detail' => 'Aprovadas para entrega',
        'icon' => 'gift'
    ],
    [
        'label' => 'Parto sem kit',
        'value' => '3',
        'detail' => 'Exigem justificativa e decisão',
        'icon' => 'exclamation-triangle'
    ]
],
    'filters' => [
    [
        'label' => 'Etapa',
        'options' => [
            'Cadastro',
            'Triagem',
            'Em acompanhamento',
            'Em avaliação',
            'Apta',
            'Aguardando Kit',
            'Pós-parto'
        ]
    ],
    [
        'label' => 'Território',
        'options' => [
            'CRAS 1',
            'CRAS 2',
            'Rural'
        ]
    ]
],
    'blocks' => [
    [
        'type' => 'table',
        'kicker' => 'Operação',
        'title' => 'Fila prioritária',
        'description' => 'Acompanhamento organizado no padrão operacional do SIGAS.',
        'columns' => [
            [
                'key' => 'beneficiaria',
                'label' => 'Beneficiária'
            ],
            [
                'key' => 'gestacao',
                'label' => 'Gestação'
            ],
            [
                'key' => 'etapa',
                'label' => 'Etapa'
            ],
            [
                'key' => 'proxima',
                'label' => 'Próxima ação'
            ],
            [
                'key' => 'situacao',
                'label' => 'Situação'
            ]
        ],
        'rows' => [
            [
                'beneficiaria' => 'Ana P. Souza',
                'gestacao' => '31 semanas · 8º mês',
                'etapa' => 'Aguardando Kit',
                'proxima' => 'Entrega até 28/08',
                'situacao' => 'Prioridade'
            ],
            [
                'beneficiaria' => 'Maria L. Costa',
                'gestacao' => '24 semanas · 6º mês',
                'etapa' => 'Em acompanhamento',
                'proxima' => 'Reunião 26/08',
                'situacao' => 'Regular'
            ],
            [
                'beneficiaria' => 'Raimunda S. Lima',
                'gestacao' => '38 semanas · 9º mês',
                'etapa' => 'Apta',
                'proxima' => 'Definir entrega',
                'situacao' => 'Parto próximo'
            ],
            [
                'beneficiaria' => 'Jéssica R. Alves',
                'gestacao' => 'Pós-parto',
                'etapa' => 'Pós-parto',
                'proxima' => 'Justificar não entrega',
                'situacao' => 'Sem kit'
            ]
        ],
        'primary' => 'beneficiaria'
    ],
    [
        'type' => 'timeline',
        'kicker' => 'Fluxo',
        'title' => 'Fluxo operacional',
        'items' => [
            [
                'date' => '1',
                'title' => 'Cadastro',
                'text' => 'Identificação da gestante, dados da gestação, DPP e referência territorial.'
            ],
            [
                'date' => '2',
                'title' => 'Triagem',
                'text' => 'Conferência documental e critérios iniciais do programa.'
            ],
            [
                'date' => '3',
                'title' => 'Acompanhamento',
                'text' => 'Visitas, reuniões, presença e pendências durante a gestação.'
            ],
            [
                'date' => '4',
                'title' => 'Avaliação',
                'text' => 'Parecer e decisão de aptidão para contemplação.'
            ],
            [
                'date' => '5',
                'title' => 'Entrega',
                'text' => 'Reserva, lote, termo e registro da entrega do kit.'
            ],
            [
                'date' => '6',
                'title' => 'Pós-parto',
                'text' => 'Registro do nascimento e encerramento com justificativa quando necessário.'
            ]
        ]
    ]
],
    'demo' => true,
    'show_states' => true,
]);
