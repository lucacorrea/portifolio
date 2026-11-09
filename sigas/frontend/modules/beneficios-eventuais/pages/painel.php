<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
ob_start();
?>
<section class="content-card mt-3"><div class="card-heading"><div><div class="card-kicker">Triagem inteligente</div><h2>Histórico de concessões</h2><p>A triagem pode consultar entregas do ANEXO e vínculos do SIGAS pelo CPF, sempre exibindo a origem.</p></div></div><div class="program-status-strip"><span class="program-status-pill">Cadastro em outro sistema <b>Sim</b></span><span class="program-status-pill">Benefício ativo <b>1</b></span><span class="program-status-pill">Entregas anteriores <b>2</b></span></div></section>
<?php
$pageCustomContent = (string) ob_get_clean();
return sigas_frontend_page([
    'title' => 'Benefícios Eventuais',
    'description' => 'Gestão das solicitações eventuais com triagem, análise, decisão, entrega e encerramento rastreável.',
    'actions' => [
    [
        'label' => 'Nova solicitação',
        'icon' => 'plus-circle',
        'primary' => true,
        'href' => 'setor.php?ambiente=beneficios-eventuais&pagina=solicitacoes'
    ],
    [
        'label' => 'Pesquisar histórico',
        'icon' => 'clock-history',
        'href' => 'setor.php?ambiente=beneficios-eventuais&pagina=triagem'
    ]
],
    'stats' => [
    [
        'label' => 'Solicitações abertas',
        'value' => '154',
        'detail' => 'Em algum estágio do fluxo',
        'icon' => 'inboxes'
    ],
    [
        'label' => 'Em análise',
        'value' => '48',
        'detail' => 'Aguardam parecer/decisão',
        'icon' => 'clipboard2-search'
    ],
    [
        'label' => 'Deferidas',
        'value' => '63',
        'detail' => 'Aguardam ou concluíram entrega',
        'icon' => 'check2-circle'
    ],
    [
        'label' => 'Prioridades',
        'value' => '17',
        'detail' => 'Demandas urgentes',
        'icon' => 'exclamation-diamond'
    ]
],
    'filters' => [
    [
        'label' => 'Tipo',
        'options' => [
            'Cesta emergencial',
            'Ajuda humanitária',
            'Auxílio eventual',
            'Outro'
        ]
    ],
    [
        'label' => 'Etapa',
        'options' => [
            'Solicitação',
            'Triagem',
            'Análise',
            'Decisão',
            'Entrega'
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
                'key' => 'protocolo',
                'label' => 'Protocolo'
            ],
            [
                'key' => 'pessoa',
                'label' => 'Pessoa'
            ],
            [
                'key' => 'tipo',
                'label' => 'Tipo'
            ],
            [
                'key' => 'etapa',
                'label' => 'Etapa'
            ],
            [
                'key' => 'prioridade',
                'label' => 'Prioridade'
            ],
            [
                'key' => 'situacao',
                'label' => 'Situação'
            ]
        ],
        'rows' => [
            [
                'protocolo' => 'BE-2026-0814',
                'pessoa' => 'Pessoa A',
                'tipo' => 'Cesta emergencial',
                'etapa' => 'Análise',
                'prioridade' => 'Alta',
                'situacao' => 'Em andamento'
            ],
            [
                'protocolo' => 'BE-2026-0813',
                'pessoa' => 'Pessoa B',
                'tipo' => 'Auxílio eventual',
                'etapa' => 'Entrega',
                'prioridade' => 'Normal',
                'situacao' => 'Deferido'
            ],
            [
                'protocolo' => 'BE-2026-0812',
                'pessoa' => 'Pessoa C',
                'tipo' => 'Ajuda humanitária',
                'etapa' => 'Triagem',
                'prioridade' => 'Alta',
                'situacao' => 'Pendência'
            ]
        ],
        'primary' => 'protocolo'
    ],
    [
        'type' => 'timeline',
        'kicker' => 'Fluxo',
        'title' => 'Fluxo operacional',
        'items' => [
            [
                'date' => '1',
                'title' => 'Solicitação',
                'text' => 'Registro da necessidade e identificação da pessoa/família.'
            ],
            [
                'date' => '2',
                'title' => 'Triagem',
                'text' => 'Documentos, histórico de benefícios e urgência.'
            ],
            [
                'date' => '3',
                'title' => 'Análise',
                'text' => 'Critérios, parecer e definição do benefício aplicável.'
            ],
            [
                'date' => '4',
                'title' => 'Decisão',
                'text' => 'Deferimento, indeferimento ou pendência justificada.'
            ],
            [
                'date' => '5',
                'title' => 'Entrega',
                'text' => 'Registro da concessão, quantidade/valor e responsável.'
            ],
            [
                'date' => '6',
                'title' => 'Encerramento',
                'text' => 'Comprovação, histórico e conclusão do atendimento.'
            ]
        ]
    ]
],
    'demo' => true,
    'show_states' => true,
]);
