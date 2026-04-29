<?php
/**
 * config_integration.php - Configurações da Integração TJAM Projudi (MNI 2.2.2)
 */

return [
    'tjam' => [
        // Substitua pelos seus dados reais fornecidos pelo tribunal
        'id_consultante' => 'SEU_ID_AQUI', 
        'codigo_secreto' => 'SEU_CODIGO_SECRETO_AQUI',
        
        'wsdl' => [
            '1g' => 'https://projudi.tjam.jus.br/projudi/webservices/projudiIntercomunicacaoWebService222?wsdl',
            '2g' => 'https://projudi.tjam.jus.br/projudi/webservices/projudiIntercomunicacaoWebService2G222?wsdl'
        ],
        
        'namespaces' => [
            'tic' => 'http://www.cnj.jus.br/servico-intercomunicacao-2.2.2/',
            'inter' => 'http://www.cnj.jus.br/intercomunicacao-2.2.2'
        ]
    ]
];
