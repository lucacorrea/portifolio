<?php

declare(strict_types=1);

/** @return array<string, mixed> */
function sigas_vigilancia_demo_data(): array
{
    return [
        'indicadores' => [
            ['indicador' => 'Famílias em acompanhamento', 'categoria' => 'Cobertura', 'territorio' => 'Municipal', 'periodo' => 'Jul/2026', 'valor' => '3.842', 'comparacao' => '+4,8%', 'tendencia' => 'Crescente', 'fonte' => 'SIGAS demonstrativo', 'atualizacao' => '30/07'],
            ['indicador' => 'Cadastros com atualização pendente', 'categoria' => 'Cadastro', 'territorio' => 'Zona urbana', 'periodo' => 'Jul/2026', 'valor' => '612', 'comparacao' => '-3,2%', 'tendencia' => 'Decrescente', 'fonte' => 'Base sintética', 'atualizacao' => '29/07'],
            ['indicador' => 'Cobertura de visitas', 'categoria' => 'Serviços', 'territorio' => 'Território Norte', 'periodo' => '2º trimestre', 'valor' => '78%', 'comparacao' => '+6 p.p.', 'tendencia' => 'Crescente', 'fonte' => 'Base sintética', 'atualizacao' => '28/07'],
            ['indicador' => 'Demandas reprimidas', 'categoria' => 'Demanda', 'territorio' => 'Território Sul', 'periodo' => 'Jul/2026', 'valor' => '146', 'comparacao' => '+2,1%', 'tendencia' => 'Estável', 'fonte' => 'SIGAS demonstrativo', 'atualizacao' => '30/07'],
        ],
        'territorios' => [
            ['territorio' => 'Norte Urbano', 'bairros' => '7', 'comunidades' => '3', 'populacao' => '18,4 mil', 'familias' => '1.126', 'referencias' => 'CRAS 1', 'vulnerabilidade' => 'Alta', 'situacao' => 'Prioritário'],
            ['territorio' => 'Sul Urbano', 'bairros' => '6', 'comunidades' => '2', 'populacao' => '16,8 mil', 'familias' => '984', 'referencias' => 'CRAS 2', 'vulnerabilidade' => 'Média', 'situacao' => 'Monitorado'],
            ['territorio' => 'Rural e Ribeirinho', 'bairros' => '—', 'comunidades' => '18', 'populacao' => '9,7 mil', 'familias' => '716', 'referencias' => 'Equipe volante', 'vulnerabilidade' => 'Alta', 'situacao' => 'Prioritário'],
        ],
        'bairros' => [
            ['nome' => 'Comunidade Horizonte', 'zona' => 'Urbana', 'territorio' => 'Norte Urbano', 'familias' => '286', 'atendimentos' => '114', 'programas' => '4', 'unidade' => 'CRAS 1', 'indicador' => 'Atenção'],
            ['nome' => 'Bairro das Águas', 'zona' => 'Urbana', 'territorio' => 'Sul Urbano', 'familias' => '241', 'atendimentos' => '92', 'programas' => '3', 'unidade' => 'CRAS 2', 'indicador' => 'Estável'],
            ['nome' => 'Comunidade Rio Verde', 'zona' => 'Rural', 'territorio' => 'Rural e Ribeirinho', 'familias' => '87', 'atendimentos' => '46', 'programas' => '2', 'unidade' => 'Equipe volante', 'indicador' => 'Prioritário'],
        ],
        'diagnosticos' => [
            ['titulo' => 'Diagnóstico socioterritorial municipal', 'territorio' => 'Municipal', 'periodo' => '2026', 'responsavel' => 'Núcleo de Vigilância', 'versao' => '1.4', 'situacao' => 'Em revisão', 'data' => '29/07/2026'],
            ['titulo' => 'Leitura territorial da zona rural', 'territorio' => 'Rural e Ribeirinho', 'periodo' => '1º semestre', 'responsavel' => 'Equipe Técnica B', 'versao' => '1.0', 'situacao' => 'Publicado', 'data' => '20/07/2026'],
            ['titulo' => 'Perfil de demandas dos CRAS', 'territorio' => 'Zona urbana', 'periodo' => '2º trimestre', 'responsavel' => 'Equipe de Indicadores', 'versao' => '0.9', 'situacao' => 'Validação', 'data' => '30/07/2026'],
        ],
        'vulnerabilidades' => [
            ['tipo' => 'Insegurança alimentar', 'territorio' => 'Norte Urbano', 'quantidade' => '428 famílias', 'prioridade' => 'Alta', 'tendencia' => 'Crescente', 'acoes' => 'Busca ativa e encaminhamento', 'status' => 'Em resposta'],
            ['tipo' => 'Isolamento territorial', 'territorio' => 'Rural e Ribeirinho', 'quantidade' => '12 comunidades', 'prioridade' => 'Alta', 'tendencia' => 'Estável', 'acoes' => 'Equipe volante', 'status' => 'Monitorada'],
            ['tipo' => 'Desatualização cadastral', 'territorio' => 'Sul Urbano', 'quantidade' => '196 famílias', 'prioridade' => 'Média', 'tendencia' => 'Decrescente', 'acoes' => 'Mutirão cadastral', 'status' => 'Em redução'],
        ],
        'buscas' => [
            ['acao' => 'Atualização cadastral territorial', 'territorio' => 'Norte Urbano', 'publico' => 'Famílias com cadastro pendente', 'periodo' => '01–15/08', 'equipe' => 'Equipe de Busca A', 'registros' => '186', 'pendencias' => '42', 'situacao' => 'Programada'],
            ['acao' => 'Cobertura de comunidades ribeirinhas', 'territorio' => 'Rural e Ribeirinho', 'publico' => 'Famílias sem acompanhamento', 'periodo' => '05–22/08', 'equipe' => 'Equipe Volante', 'registros' => '94', 'pendencias' => '31', 'situacao' => 'Em campo'],
            ['acao' => 'Primeira infância', 'territorio' => 'Sul Urbano', 'publico' => 'Gestantes e crianças', 'periodo' => 'Jul/2026', 'equipe' => 'Equipe de Busca B', 'registros' => '128', 'pendencias' => '9', 'situacao' => 'Concluída'],
        ],
        'monitoramento' => [
            ['servico' => 'PAIF', 'cobertura' => '82%', 'capacidade' => '88%', 'demandas' => '346', 'atendimentos' => '312', 'fila' => '34', 'tendencia' => 'Estável', 'alerta' => 'Acompanhar'],
            ['servico' => 'Serviço de Convivência', 'cobertura' => '68%', 'capacidade' => '91%', 'demandas' => '284', 'atendimentos' => '238', 'fila' => '46', 'tendencia' => 'Crescente', 'alerta' => 'Capacidade'],
            ['servico' => 'Equipe volante', 'cobertura' => '61%', 'capacidade' => '76%', 'demandas' => '128', 'atendimentos' => '97', 'fila' => '31', 'tendencia' => 'Crescente', 'alerta' => 'Território'],
        ],
        'relatorios' => [
            ['relatorio' => 'Painel de indicadores territoriais', 'tipo' => 'Indicadores', 'recorte' => 'Municipal', 'periodo' => 'Jul/2026', 'atualizacao' => '30/07/2026', 'situacao' => 'Disponível'],
            ['relatorio' => 'Vulnerabilidades prioritárias', 'tipo' => 'Vulnerabilidade', 'recorte' => 'Territórios', 'periodo' => '2º trimestre', 'atualizacao' => '27/07/2026', 'situacao' => 'Disponível'],
            ['relatorio' => 'Cobertura da busca ativa', 'tipo' => 'Busca ativa', 'recorte' => 'Urbano e rural', 'periodo' => 'Jul/2026', 'atualizacao' => 'Em preparação', 'situacao' => 'Processando'],
        ],
    ];
}
