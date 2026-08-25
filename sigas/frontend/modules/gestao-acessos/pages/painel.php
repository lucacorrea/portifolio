<?php

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/program-pages.php';
ob_start();
?>
<section class="content-card mt-3"><div class="card-heading"><div><div class="card-kicker">Modelo de autorização</div><h2>Setor define módulos; perfil define ações</h2><p>Administrador e suporte têm visão global. Demais usuários recebem módulos conforme o setor e ações conforme o perfil, com exceções individuais justificadas.</p></div><span class="status-badge status-success"><i class="bi bi-shield-check"></i>Princípio do menor privilégio</span></div><div class="program-flow-banner"><div class="program-flow-step"><small>1</small><strong>Usuário</strong><span>Identidade única</span></div><div class="program-flow-step"><small>2</small><strong>Setor</strong><span>Quais módulos aparecem</span></div><div class="program-flow-step"><small>3</small><strong>Cargo</strong><span>Função organizacional</span></div><div class="program-flow-step"><small>4</small><strong>Perfil</strong><span>Quais ações pode executar</span></div><div class="program-flow-step"><small>5</small><strong>Auditoria</strong><span>Quem alterou o quê</span></div></div></section>
<?php
$pageCustomContent = (string) ob_get_clean();
return sigas_frontend_page([
 'title' => 'Governança e Acessos',
 'description' => 'Administração central de usuários, cargos, setores, perfis, permissões, módulos e auditoria.',
 'actions' => [
    [
        'label' => 'Revisar acessos',
        'icon' => 'shield-check',
        'primary' => true,
        'href' => 'setor.php?ambiente=gestao-acessos&pagina=matriz-acesso'
    ]
],
 'stats' => [
    [
        'label' => 'Usuários ativos',
        'value' => '86',
        'detail' => 'Contas operacionais',
        'icon' => 'people'
    ],
    [
        'label' => 'Setores',
        'value' => '7',
        'detail' => 'Unidades configuradas',
        'icon' => 'diagram-3'
    ],
    [
        'label' => 'Perfis',
        'value' => '6',
        'detail' => 'Níveis de acesso',
        'icon' => 'person-gear'
    ],
    [
        'label' => 'Permissões',
        'value' => '40+',
        'detail' => 'Ações controladas por perfil',
        'icon' => 'key'
    ]
],
 'filters' => [

],
 'blocks' => [
    [
        'type' => 'table',
        'kicker' => 'Governança',
        'title' => 'Governança e Acessos',
        'description' => 'Estrutura visual preparada para gestão centralizada de acesso e segurança.',
        'columns' => [
            [
                'key' => 'item',
                'label' => 'Área'
            ],
            [
                'key' => 'regra',
                'label' => 'Regra principal'
            ],
            [
                'key' => 'responsavel',
                'label' => 'Responsável'
            ],
            [
                'key' => 'situacao',
                'label' => 'Situação'
            ]
        ],
        'rows' => [
            [
                'item' => 'Usuários',
                'regra' => 'Conta + setor + perfil',
                'responsavel' => 'Administração',
                'situacao' => 'Controlado'
            ],
            [
                'item' => 'Módulos por setor',
                'regra' => 'Matriz de visibilidade',
                'responsavel' => 'Administrador',
                'situacao' => 'Configurável'
            ],
            [
                'item' => 'Permissões',
                'regra' => 'Ação por perfil',
                'responsavel' => 'Administrador',
                'situacao' => 'Granular'
            ],
            [
                'item' => 'Auditoria',
                'regra' => 'Histórico imutável',
                'responsavel' => 'Sistema',
                'situacao' => 'Obrigatório'
            ]
        ],
        'primary' => 'item'
    ],
    [
        'type' => 'timeline',
        'kicker' => 'Segurança',
        'title' => 'Fluxo de governança',
        'items' => [
            [
                'date' => '1',
                'title' => 'Cadastro do usuário',
                'text' => 'Conta criada sem acesso operacional até aprovação.'
            ],
            [
                'date' => '2',
                'title' => 'Setor e cargo',
                'text' => 'Vínculo organizacional e função exercida.'
            ],
            [
                'date' => '3',
                'title' => 'Perfil de acesso',
                'text' => 'Nível define as ações permitidas dentro dos módulos.'
            ],
            [
                'date' => '4',
                'title' => 'Matriz de módulos',
                'text' => 'Setor define quais módulos ficam disponíveis.'
            ],
            [
                'date' => '5',
                'title' => 'Exceções controladas',
                'text' => 'Permissões individuais somente quando justificadas.'
            ],
            [
                'date' => '6',
                'title' => 'Auditoria',
                'text' => 'Toda alteração relevante deve manter rastreabilidade.'
            ]
        ]
    ]
],
 'demo' => true,
 'show_states' => true,
]);
