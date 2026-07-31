<?php

declare(strict_types=1);

/** @return list<array<string, string>> */
function pe_demo_candidates(): array
{
    return [
        ['candidato' => 'Candidato A-104', 'cpf' => '***.482.***-**', 'idade' => '19', 'telefone' => '(97) 9****-1204', 'escolaridade' => 'Ensino médio', 'area' => 'Administrativo', 'disponibilidade' => 'Integral', 'situacao' => 'Ativo', 'atualizacao' => 'Hoje'],
        ['candidato' => 'Candidato B-207', 'cpf' => '***.971.***-**', 'idade' => '22', 'telefone' => '(97) 9****-8832', 'escolaridade' => 'Superior incompleto', 'area' => 'Comércio', 'disponibilidade' => 'Tarde', 'situacao' => 'Encaminhado', 'atualizacao' => 'Ontem'],
        ['candidato' => 'Candidato C-315', 'cpf' => '***.315.***-**', 'idade' => '18', 'telefone' => '(97) 9****-4190', 'escolaridade' => 'Ensino médio', 'area' => 'Serviços', 'disponibilidade' => 'Manhã', 'situacao' => 'Aguardando vaga', 'atualizacao' => '28 jul'],
    ];
}

/** @return list<array<string, string>> */
function pe_demo_jobs(): array
{
    return [
        ['cargo' => 'Auxiliar administrativo', 'instituicao' => 'Órgão Municipal Alfa', 'setor' => 'Atendimento', 'quantidade' => '4', 'requisitos' => 'Informática básica', 'escolaridade' => 'Ensino médio', 'carga_horaria' => '30h', 'remuneracao' => 'Bolsa referencial', 'prazo' => '02 ago', 'situacao' => 'Aberta', 'compativeis' => '18'],
        ['cargo' => 'Apoio de recepção', 'instituicao' => 'Instituição Parceira Beta', 'setor' => 'Recepção', 'quantidade' => '2', 'requisitos' => 'Boa comunicação', 'escolaridade' => 'Ensino médio', 'carga_horaria' => '20h', 'remuneracao' => 'Bolsa referencial', 'prazo' => '05 ago', 'situacao' => 'Em seleção', 'compativeis' => '11'],
        ['cargo' => 'Assistente de arquivo', 'instituicao' => 'Entidade Social Gama', 'setor' => 'Documentação', 'quantidade' => '3', 'requisitos' => 'Organização', 'escolaridade' => 'Cursando médio', 'carga_horaria' => '20h', 'remuneracao' => 'Bolsa referencial', 'prazo' => '12 ago', 'situacao' => 'Aberta', 'compativeis' => '9'],
    ];
}

/** @return list<array<string, string>> */
function pe_demo_partners(): array
{
    return [
        ['instituicao' => 'Órgão Municipal Alfa', 'tipo' => 'Órgão público', 'cnpj' => 'Não aplicável', 'responsavel' => 'Coordenação A', 'telefone' => '(97) 3***-1204', 'email' => 'contato.a@exemplo.gov.br', 'oportunidades' => '6', 'lotados' => '14', 'parceria' => 'Termo vigente', 'situacao' => 'Ativa'],
        ['instituicao' => 'Instituição Parceira Beta', 'tipo' => 'Empresa privada', 'cnpj' => '**.***.***/****-**', 'responsavel' => 'Coordenação B', 'telefone' => '(97) 3***-8832', 'email' => 'contato.b@exemplo.org', 'oportunidades' => '3', 'lotados' => '8', 'parceria' => 'Cooperação', 'situacao' => 'Ativa'],
        ['instituicao' => 'Entidade Social Gama', 'tipo' => 'Organização social', 'cnpj' => '**.***.***/****-**', 'responsavel' => 'Coordenação C', 'telefone' => '(97) 3***-4190', 'email' => 'contato.c@exemplo.org', 'oportunidades' => '2', 'lotados' => '5', 'parceria' => 'Em renovação', 'situacao' => 'Pendente'],
    ];
}

/** @return list<array<string, string>> */
function pe_demo_placements(): array
{
    return [
        ['participante' => 'Participante PE-041', 'instituicao' => 'Órgão Municipal Alfa', 'setor' => 'Atendimento', 'funcao' => 'Auxiliar', 'inicio' => '15 jul', 'jornada' => '30h', 'responsavel' => 'Supervisão A', 'situacao' => 'Ativa'],
        ['participante' => 'Participante PE-052', 'instituicao' => 'Instituição Parceira Beta', 'setor' => 'Recepção', 'funcao' => 'Apoio', 'inicio' => '10 jul', 'jornada' => '20h', 'responsavel' => 'Supervisão B', 'situacao' => 'Em adaptação'],
        ['participante' => 'Participante PE-063', 'instituicao' => 'Entidade Social Gama', 'setor' => 'Arquivo', 'funcao' => 'Assistente', 'inicio' => '02 jul', 'jornada' => '20h', 'responsavel' => 'Supervisão C', 'situacao' => 'Ativa'],
    ];
}

/** @return list<array<string, string>> */
function pe_demo_referrals(): array
{
    return [
        ['candidato' => 'Candidato A-104', 'oportunidade' => 'Auxiliar administrativo', 'instituicao' => 'Órgão Municipal Alfa', 'data' => '30 jul', 'responsavel' => 'Equipe SIGAS', 'retorno' => 'Entrevista marcada', 'situacao' => 'Em andamento'],
        ['candidato' => 'Candidato B-207', 'oportunidade' => 'Apoio de recepção', 'instituicao' => 'Instituição Parceira Beta', 'data' => '29 jul', 'responsavel' => 'Equipe SIGAS', 'retorno' => 'Aguardando instituição', 'situacao' => 'Pendente'],
        ['candidato' => 'Candidato C-315', 'oportunidade' => 'Assistente de arquivo', 'instituicao' => 'Entidade Social Gama', 'data' => '28 jul', 'responsavel' => 'Equipe SIGAS', 'retorno' => 'Análise de perfil', 'situacao' => 'Encaminhado'],
    ];
}

/** @return list<array<string, string>> */
function pe_demo_documents(): array
{
    return [
        ['participante' => 'Participante PE-041', 'exigidos' => '6', 'entregues' => '6', 'pendencias' => '0', 'validade' => 'Dez/2026', 'situacao' => 'Regular'],
        ['participante' => 'Participante PE-052', 'exigidos' => '6', 'entregues' => '5', 'pendencias' => 'Comprovante escolar', 'validade' => 'Set/2026', 'situacao' => 'Pendente'],
        ['participante' => 'Participante PE-063', 'exigidos' => '6', 'entregues' => '4', 'pendencias' => '2 documentos', 'validade' => 'Ago/2026', 'situacao' => 'Revisar'],
    ];
}

/** @return list<array<string, string>> */
function pe_demo_attendance(): array
{
    return [
        ['competencia' => 'Jul/2026', 'participante' => 'Participante PE-041', 'instituicao' => 'Órgão Municipal Alfa', 'previstos' => '22', 'presencas' => '21', 'faltas' => '1', 'percentual' => '95%', 'situacao' => 'Regular'],
        ['competencia' => 'Jul/2026', 'participante' => 'Participante PE-052', 'instituicao' => 'Instituição Parceira Beta', 'previstos' => '22', 'presencas' => '18', 'faltas' => '4', 'percentual' => '82%', 'situacao' => 'Atenção'],
        ['competencia' => 'Jul/2026', 'participante' => 'Participante PE-063', 'instituicao' => 'Entidade Social Gama', 'previstos' => '22', 'presencas' => '20', 'faltas' => '2', 'percentual' => '91%', 'situacao' => 'Regular'],
    ];
}

/** @return list<array<string, string>> */
function pe_demo_grants(): array
{
    return [
        ['competencia' => 'Jul/2026', 'participante' => 'Participante PE-041', 'instituicao' => 'Órgão Municipal Alfa', 'valor' => 'R$ 800,00', 'frequencia' => '95%', 'documentacao' => 'Regular', 'pagamento' => 'Em processamento', 'data' => '05 ago'],
        ['competencia' => 'Jul/2026', 'participante' => 'Participante PE-052', 'instituicao' => 'Instituição Parceira Beta', 'valor' => 'R$ 800,00', 'frequencia' => '82%', 'documentacao' => 'Pendente', 'pagamento' => 'Em análise', 'data' => '—'],
        ['competencia' => 'Jul/2026', 'participante' => 'Participante PE-063', 'instituicao' => 'Entidade Social Gama', 'valor' => 'R$ 800,00', 'frequencia' => '91%', 'documentacao' => 'Regular', 'pagamento' => 'Programado', 'data' => '05 ago'],
    ];
}

/** @return list<array<string, string>> */
function pe_demo_trainings(): array
{
    return [
        ['curso' => 'Oficina de currículo', 'instituicao' => 'Casa do Cidadão', 'turma' => 'Turma 03', 'carga_horaria' => '8h', 'inscritos' => '28', 'concluintes' => '—', 'periodo' => '29–31 jul', 'certificado' => 'Previsto', 'situacao' => 'Em andamento'],
        ['curso' => 'Atendimento ao público', 'instituicao' => 'Instituição Formadora Beta', 'turma' => 'Turma 01', 'carga_horaria' => '24h', 'inscritos' => '32', 'concluintes' => '29', 'periodo' => '01–12 jul', 'certificado' => 'Disponível', 'situacao' => 'Concluída'],
        ['curso' => 'Informática básica', 'instituicao' => 'Instituição Formadora Gama', 'turma' => 'Turma 02', 'carga_horaria' => '40h', 'inscritos' => '25', 'concluintes' => '—', 'periodo' => '05–20 ago', 'certificado' => 'Previsto', 'situacao' => 'Inscrições abertas'],
    ];
}

/** @return list<array<string, string>> */
function pe_demo_followups(): array
{
    return [
        ['participante' => 'Participante PE-041', 'instituicao' => 'Órgão Municipal Alfa', 'responsavel' => 'Técnico A', 'data' => '30 jul', 'tipo' => 'Contato mensal', 'resumo' => 'Adaptação satisfatória', 'proxima_acao' => 'Visita em 15 ago', 'situacao' => 'Regular'],
        ['participante' => 'Participante PE-052', 'instituicao' => 'Instituição Parceira Beta', 'responsavel' => 'Técnico B', 'data' => '29 jul', 'tipo' => 'Orientação', 'resumo' => 'Frequência requer atenção', 'proxima_acao' => 'Reunião em 02 ago', 'situacao' => 'Atenção'],
        ['participante' => 'Participante PE-063', 'instituicao' => 'Entidade Social Gama', 'responsavel' => 'Técnico C', 'data' => '28 jul', 'tipo' => 'Avaliação', 'resumo' => 'Metas iniciais cumpridas', 'proxima_acao' => 'Retorno em 20 ago', 'situacao' => 'Regular'],
    ];
}

/** @return list<array<string, string>> */
function pe_demo_reports(): array
{
    return [
        ['relatorio' => 'Participantes ativos', 'categoria' => 'Participantes', 'periodo' => 'Jul/2026', 'gerado_em' => '30 jul', 'situacao' => 'Disponível'],
        ['relatorio' => 'Frequência consolidada', 'categoria' => 'Frequência', 'periodo' => 'Jul/2026', 'gerado_em' => '29 jul', 'situacao' => 'Disponível'],
        ['relatorio' => 'Bolsas por competência', 'categoria' => 'Bolsas', 'periodo' => 'Jul/2026', 'gerado_em' => '28 jul', 'situacao' => 'Em revisão'],
    ];
}

/** @return list<array<string, string>> */
function pe_demo_movements(): array
{
    return [
        ['date' => 'Hoje, 09:20', 'title' => 'Encaminhamento atualizado', 'text' => 'O protocolo PE-104 avançou para entrevista.'],
        ['date' => 'Ontem, 16:10', 'title' => 'Frequência recebida', 'text' => 'A competência de julho foi conferida visualmente.'],
        ['date' => '28 jul', 'title' => 'Capacitação programada', 'text' => 'Nova turma de informática adicionada à agenda.'],
    ];
}

/** @return list<array{label: string, value: string, detail: string, icon: string}> */
function pe_demo_stats(string $page): array
{
    $stats = [
        'painel' => [['Candidatos', '1.248', '+42 no mês', 'people'], ['Ativos', '786', '63% da base', 'person-check'], ['Oportunidades', '86', '12 novas', 'briefcase'], ['Órgãos parceiros', '24', '3 em renovação', 'buildings'], ['Lotações', '174', '5 recentes', 'diagram-3'], ['Pendências', '31', 'Requerem atenção', 'exclamation-circle']],
        'candidatos' => [['Cadastrados', '1.248', 'Base demonstrativa', 'people'], ['Ativos', '786', 'Perfis disponíveis', 'person-check'], ['Encaminhados', '174', 'No mês', 'send'], ['Aguardando vaga', '163', 'Revisar perfil', 'hourglass']],
        'vagas' => [['Oportunidades', '86', 'Total demonstrativo', 'briefcase'], ['Abertas', '42', 'Recebendo candidatos', 'door-open'], ['Em seleção', '27', 'Etapa em andamento', 'funnel'], ['Compatibilidades', '138', 'Perfis sugeridos', 'person-check']],
        'parceiros' => [['Instituições', '24', 'Rede demonstrativa', 'buildings'], ['Ativas', '20', 'Parcerias vigentes', 'check-circle'], ['Oportunidades', '86', 'Ofertadas no período', 'briefcase'], ['Participantes', '174', 'Em lotação', 'people']],
        'lotacoes' => [['Lotações ativas', '174', 'Total demonstrativo', 'diagram-3'], ['Novas no mês', '18', 'Movimentações recentes', 'plus-circle'], ['Em adaptação', '12', 'Acompanhamento inicial', 'activity'], ['Instituições', '24', 'Locais parceiros', 'buildings']],
        'encaminhamentos' => [['Encaminhados', '174', 'No mês', 'send'], ['Com retorno', '96', 'Retorno registrado', 'reply'], ['Pendentes', '31', 'Aguardando resposta', 'hourglass'], ['Entrevistas', '47', 'Próximos 7 dias', 'calendar-event']],
        'documentacao' => [['Participantes', '174', 'Em acompanhamento', 'people'], ['Regulares', '148', 'Documentação completa', 'folder-check'], ['Pendências', '21', 'Exigem providência', 'folder-x'], ['A vencer', '5', 'Próximos 30 dias', 'calendar-x']],
        'frequencia' => [['Participantes', '174', 'Competência atual', 'people'], ['Média geral', '92%', 'Frequência demonstrativa', 'percent'], ['Pendentes', '14', 'Sem fechamento', 'clock'], ['Atenção', '9', 'Abaixo do referencial', 'exclamation-triangle']],
        'bolsas' => [['Competência', 'Jul/2026', 'Período demonstrativo', 'calendar3'], ['Em processamento', '148', 'Sem pagamento real', 'hourglass-split'], ['Em análise', '21', 'Pendência documental', 'search'], ['Programadas', '5', 'Previsão visual', 'wallet2']],
        'capacitacoes' => [['Capacitações', '18', 'No semestre', 'mortarboard'], ['Turmas ativas', '7', 'Em andamento', 'people'], ['Inscritos', '286', 'Total demonstrativo', 'person-plus'], ['Concluintes', '193', 'Com participação', 'award']],
        'acompanhamentos' => [['Participantes', '174', 'Em acompanhamento', 'people'], ['Regulares', '139', 'Sem alerta', 'check-circle'], ['Atenção', '24', 'Com próxima ação', 'exclamation-circle'], ['Visitas previstas', '11', 'Próximos 15 dias', 'calendar-event']],
        'relatorios' => [['Relatórios', '28', 'Gerados no mês', 'bar-chart-line'], ['Disponíveis', '24', 'Consulta visual', 'file-check'], ['Em revisão', '4', 'Aguardando conferência', 'file-earmark-text'], ['Categorias', '7', 'Áreas monitoradas', 'collection']],
    ][$page] ?? [];

    return array_map(
        static fn (array $stat): array => ['label' => $stat[0], 'value' => $stat[1], 'detail' => $stat[2], 'icon' => $stat[3]],
        $stats
    );
}
