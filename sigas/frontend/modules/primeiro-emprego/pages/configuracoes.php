<?php

declare(strict_types=1);

$pageDefinition = [
    'title' => 'Configurações',
    'description' => 'Parâmetros exclusivamente visuais preparados para uma integração futura.',
    'blocks' => [
        [
            'type' => 'settings',
            'title' => 'Perfis e oportunidades',
            'fields' => [
                ['label' => 'Áreas profissionais', 'value' => 'Administrativo, Comércio, Serviços'],
                ['label' => 'Escolaridades', 'value' => 'Ensino médio, Superior'],
                ['label' => 'Tipos de instituição', 'value' => 'Órgão público, Empresa, Organização social'],
                ['label' => 'Tipos de oportunidade', 'value' => 'Bolsa, Aprendizagem, Experiência'],
            ],
        ],
        [
            'type' => 'settings',
            'title' => 'Operação do programa',
            'fields' => [
                ['label' => 'Situações', 'value' => 'Ativo, Pendente, Concluído'],
                ['label' => 'Documentos exigidos', 'value' => 'Identificação, escolaridade, residência'],
                ['label' => 'Competências', 'value' => 'Mensal'],
                ['label' => 'Valores referenciais', 'value' => 'Definição futura'],
                ['label' => 'Modelos de mensagens', 'value' => 'Convocação, pendência, acompanhamento'],
            ],
        ],
    ],
    'modal' => ['title' => 'Configuração visual'],
];
