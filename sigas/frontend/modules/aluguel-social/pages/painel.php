<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
ob_start();
?>
<section class="content-card mt-3"><div class="card-heading"><div><div class="card-kicker">Decisão assistida</div><h2>Histórico antes da concessão</h2><p>Consulta resumida de programas e benefícios anteriores para apoiar a análise, sem bloquear automaticamente a concessão.</p></div></div><div class="program-history-list"><div class="program-history-row"><strong>SIGAS</strong><span>Comida na Mesa — Ativo</span><span>Atual</span></div><div class="program-history-row"><strong>ANEXO</strong><span>Pecúnia — Entregue</span><span>27/03/2026</span></div><div class="program-history-row"><strong>Protegido</strong><span>Existe vínculo com acesso restrito</span><span><i class="bi bi-lock"></i></span></div></div></section>
<?php
$pageCustomContent = (string) ob_get_clean();
return sigas_frontend_page([
    'title' => 'Aluguel Social',
    'description' => 'Gestão da moradia temporária: solicitação, vistoria, parecer, concessão, pagamentos e reavaliação.',
    'actions' => [
    [
        'label' => 'Nova solicitação',
        'icon' => 'house-add',
        'primary' => true,
        'href' => 'setor.php?ambiente=aluguel-social&pagina=solicitacoes'
    ],
    [
        'label' => 'Pesquisar pessoa',
        'icon' => 'search',
        'href' => 'setor.php?ambiente=aluguel-social&pagina=beneficiarios'
    ]
],
    'stats' => [
    [
        'label' => 'Concessões ativas',
        'value' => '87',
        'detail' => 'Beneficiários em pagamento',
        'icon' => 'house-check'
    ],
    [
        'label' => 'Em análise',
        'value' => '24',
        'detail' => 'Solicitações em fluxo',
        'icon' => 'clipboard2-search'
    ],
    [
        'label' => 'Vistorias pendentes',
        'value' => '11',
        'detail' => 'Demandam agenda técnica',
        'icon' => 'house-gear'
    ],
    [
        'label' => 'Reavaliações',
        'value' => '9',
        'detail' => 'Vencem nos próximos 30 dias',
        'icon' => 'arrow-repeat'
    ]
],
    'filters' => [
    [
        'label' => 'Etapa',
        'options' => [
            'Solicitação',
            'Vistoria',
            'Parecer',
            'Concessão',
            'Pagamento',
            'Reavaliação'
        ]
    ],
    [
        'label' => 'Situação',
        'options' => [
            'Ativo',
            'Prioridade',
            'Pendente',
            'Encerrado'
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
                'key' => 'beneficiario',
                'label' => 'Beneficiário'
            ],
            [
                'key' => 'motivo',
                'label' => 'Motivo'
            ],
            [
                'key' => 'etapa',
                'label' => 'Etapa'
            ],
            [
                'key' => 'prazo',
                'label' => 'Prazo'
            ],
            [
                'key' => 'valor',
                'label' => 'Valor'
            ],
            [
                'key' => 'situacao',
                'label' => 'Situação'
            ]
        ],
        'rows' => [
            [
                'beneficiario' => 'Família AS-0184',
                'motivo' => 'Risco estrutural',
                'etapa' => 'Vistoria',
                'prazo' => '23/08/2026',
                'valor' => '—',
                'situacao' => 'Prioridade'
            ],
            [
                'beneficiario' => 'Família AS-0162',
                'motivo' => 'Desabrigamento',
                'etapa' => 'Concessão',
                'prazo' => '31/12/2026',
                'valor' => 'R$ 650,00',
                'situacao' => 'Ativo'
            ],
            [
                'beneficiario' => 'Família AS-0148',
                'motivo' => 'Sinistro',
                'etapa' => 'Reavaliação',
                'prazo' => '28/08/2026',
                'valor' => 'R$ 600,00',
                'situacao' => 'Renovar/encerrar'
            ]
        ],
        'primary' => 'beneficiario'
    ],
    [
        'type' => 'timeline',
        'kicker' => 'Fluxo',
        'title' => 'Fluxo operacional',
        'items' => [
            [
                'date' => '1',
                'title' => 'Solicitação',
                'text' => 'Cadastro da demanda, composição familiar, motivo e documentação.'
            ],
            [
                'date' => '2',
                'title' => 'Vistoria',
                'text' => 'Verificação do imóvel/situação de risco e relatório técnico-social.'
            ],
            [
                'date' => '3',
                'title' => 'Parecer',
                'text' => 'Análise, critérios e decisão de deferimento ou indeferimento.'
            ],
            [
                'date' => '4',
                'title' => 'Concessão',
                'text' => 'Imóvel, proprietário, período, valor e termo de concessão.'
            ],
            [
                'date' => '5',
                'title' => 'Pagamentos',
                'text' => 'Controle mensal, pendências e comprovação.'
            ],
            [
                'date' => '6',
                'title' => 'Reavaliação',
                'text' => 'Renovação, suspensão ou encerramento fundamentado.'
            ]
        ]
    ]
],
    'demo' => true,
    'show_states' => true,
]);
