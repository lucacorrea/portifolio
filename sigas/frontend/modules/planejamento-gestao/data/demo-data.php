<?php

declare(strict_types=1);

/** @return array<string, mixed> */
function sigas_planejamento_demo_data(): array
{
    return [
        'planos' => [
            ['plano' => 'Plano Municipal de Assistência Social', 'setor' => 'Gestão', 'periodo' => '2026–2029', 'responsavel' => 'Coordenação de Planejamento', 'metas' => '24', 'execucao' => '68%', 'situacao' => 'Em execução', 'prazo' => 'Dez/2029'],
            ['plano' => 'Qualificação da Rede Socioassistencial', 'setor' => 'Proteção Básica', 'periodo' => '2026', 'responsavel' => 'Núcleo de Gestão', 'metas' => '12', 'execucao' => '54%', 'situacao' => 'Em execução', 'prazo' => 'Dez/2026'],
            ['plano' => 'Fortalecimento da Vigilância', 'setor' => 'Vigilância', 'periodo' => '2026–2027', 'responsavel' => 'Equipe de Indicadores', 'metas' => '9', 'execucao' => '81%', 'situacao' => 'Atenção', 'prazo' => 'Jun/2027'],
        ],
        'metas' => [
            ['meta' => 'Atualizar diagnóstico socioterritorial', 'plano' => 'Plano Municipal', 'responsavel' => 'Vigilância', 'prazo' => '30/09/2026', 'progresso' => '72%', 'prioridade' => 'Alta', 'status' => 'Em andamento', 'atualizacao' => 'Hoje'],
            ['meta' => 'Capacitar equipes de referência', 'plano' => 'Qualificação da Rede', 'responsavel' => 'Gestão do Trabalho', 'prazo' => '15/10/2026', 'progresso' => '58%', 'prioridade' => 'Média', 'status' => 'Em andamento', 'atualizacao' => 'Ontem'],
            ['meta' => 'Revisar fluxos intersetoriais', 'plano' => 'Plano Municipal', 'responsavel' => 'Planejamento', 'prazo' => '20/08/2026', 'progresso' => '100%', 'prioridade' => 'Alta', 'status' => 'Concluída', 'atualizacao' => '28/07'],
            ['meta' => 'Publicar boletim de monitoramento', 'plano' => 'Fortalecimento da Vigilância', 'responsavel' => 'Indicadores', 'prazo' => '05/08/2026', 'progresso' => '44%', 'prioridade' => 'Crítica', 'status' => 'Atrasada', 'atualizacao' => '25/07'],
        ],
        'cronograma' => [
            ['date' => '05 AGO', 'title' => 'Reunião de monitoramento trimestral', 'text' => 'Revisão dos resultados e pactuação de providências com as coordenações.'],
            ['date' => '12 AGO', 'title' => 'Entrega dos relatórios setoriais', 'text' => 'Prazo visual para consolidação dos relatórios de execução.'],
            ['date' => '21 AGO', 'title' => 'Oficina de revisão de metas', 'text' => 'Encontro técnico com responsáveis pelos planos de ação.'],
            ['date' => '02 SET', 'title' => 'Apresentação do painel gerencial', 'text' => 'Síntese demonstrativa dos indicadores para a gestão.'],
        ],
        'unidades' => [
            ['unidade' => 'SEMAS Sede', 'tipo' => 'Gestão', 'responsavel' => 'Coordenação Administrativa', 'endereco' => 'Área central — Coari', 'telefone' => '(97) 3***-**10', 'servidores' => '28', 'servicos' => 'Gestão e apoio', 'situacao' => 'Ativa', 'atualizacao' => 'Hoje'],
            ['unidade' => 'CRAS Território Norte', 'tipo' => 'CRAS', 'responsavel' => 'Coordenação de Unidade', 'endereco' => 'Território Norte', 'telefone' => '(97) 3***-**21', 'servidores' => '16', 'servicos' => 'PAIF e convivência', 'situacao' => 'Ativa', 'atualizacao' => 'Ontem'],
            ['unidade' => 'CREAS Municipal', 'tipo' => 'CREAS', 'responsavel' => 'Coordenação de Unidade', 'endereco' => 'Área urbana — Coari', 'telefone' => '(97) 3***-**32', 'servidores' => '14', 'servicos' => 'PAEFI e medidas', 'situacao' => 'Atenção', 'atualizacao' => '29/07'],
        ],
        'equipes' => [
            ['servidor' => 'Profissional 001', 'cargo' => 'Assistente social', 'setor' => 'Proteção Básica', 'unidade' => 'CRAS Norte', 'contato' => 'Ramal 112', 'situacao' => 'Em atividade', 'carga' => '30h'],
            ['servidor' => 'Profissional 002', 'cargo' => 'Psicólogo', 'setor' => 'Proteção Especial', 'unidade' => 'CREAS', 'contato' => 'Ramal 128', 'situacao' => 'Em atividade', 'carga' => '30h'],
            ['servidor' => 'Profissional 003', 'cargo' => 'Analista de dados', 'setor' => 'Vigilância', 'unidade' => 'SEMAS Sede', 'contato' => 'Ramal 104', 'situacao' => 'Capacitação', 'carga' => '40h'],
        ],
        'documentos' => [
            ['documento' => 'Plano Municipal 2026–2029', 'categoria' => 'Plano', 'setor' => 'Gestão', 'data' => '18/07/2026', 'versao' => '2.1', 'situacao' => 'Publicado'],
            ['documento' => 'Ata da Comissão de Monitoramento', 'categoria' => 'Ata', 'setor' => 'Planejamento', 'data' => '25/07/2026', 'versao' => '1.0', 'situacao' => 'Em revisão'],
            ['documento' => 'Relatório de execução semestral', 'categoria' => 'Relatório', 'setor' => 'Todos', 'data' => '29/07/2026', 'versao' => '0.8', 'situacao' => 'Pendente'],
            ['documento' => 'Portaria de composição técnica', 'categoria' => 'Portaria', 'setor' => 'Gestão do Trabalho', 'data' => '30/06/2026', 'versao' => '1.0', 'situacao' => 'Vigente'],
        ],
        'monitoramento' => [
            ['programa' => 'Proteção e Atendimento Integral à Família', 'execucao' => '76%', 'metas' => '8 de 10', 'alertas' => '1', 'pendencias' => '2', 'evolucao' => '+7 p.p.', 'situacao' => 'Adequada'],
            ['programa' => 'Serviço de Convivência', 'execucao' => '63%', 'metas' => '5 de 9', 'alertas' => '2', 'pendencias' => '4', 'evolucao' => '+2 p.p.', 'situacao' => 'Atenção'],
            ['programa' => 'Acompanhamento Especializado', 'execucao' => '71%', 'metas' => '6 de 8', 'alertas' => '1', 'pendencias' => '3', 'evolucao' => '+4 p.p.', 'situacao' => 'Adequada'],
        ],
        'relatorios' => [
            ['relatorio' => 'Execução dos planos de ação', 'tipo' => 'Gerencial', 'periodo' => 'Jul/2026', 'setor' => 'Todos', 'gerado' => '30/07/2026', 'situacao' => 'Disponível'],
            ['relatorio' => 'Metas por unidade', 'tipo' => 'Monitoramento', 'periodo' => '2º trimestre', 'setor' => 'Proteção Básica', 'gerado' => '25/07/2026', 'situacao' => 'Disponível'],
            ['relatorio' => 'Pendências documentais', 'tipo' => 'Conformidade', 'periodo' => 'Jul/2026', 'setor' => 'Gestão', 'gerado' => 'Em preparação', 'situacao' => 'Processando'],
        ],
    ];
}
