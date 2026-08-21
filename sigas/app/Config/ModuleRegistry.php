<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Fonte única da navegação visual. O registro não concede autorização.
 */
final class ModuleRegistry
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'planejamento-gestao' => self::environment(
                'planejamento-gestao',
                'Planejamento e Gestão',
                'diagram-3',
                'planning',
                'sector',
                [
                    'painel' => ['Painel', 'speedometer2'],
                    'planos-acao' => ['Planos de ação', 'clipboard2-check'],
                    'metas' => ['Metas', 'bullseye'],
                    'cronogramas' => ['Cronogramas', 'calendar3'],
                    'rede-unidades' => ['Rede de unidades', 'buildings'],
                    'equipes' => ['Equipes', 'people'],
                    'documentos' => ['Documentos', 'folder2-open'],
                    'monitoramento' => ['Monitoramento', 'activity'],
                    'relatorios' => ['Relatórios', 'bar-chart-line'],
                    'configuracoes' => ['Configurações', 'gear'],
                ],
                ['painel', 'planos-acao', 'metas', 'cronogramas']
            ),
            'vigilancia-socioassistencial' => self::environment(
                'vigilancia-socioassistencial',
                'Vigilância Socioassistencial',
                'bar-chart-steps',
                'vigilance',
                'sector',
                [
                    'painel' => ['Painel', 'speedometer2'],
                    'indicadores' => ['Indicadores', 'graph-up-arrow'],
                    'territorios' => ['Territórios', 'map'],
                    'bairros-comunidades' => ['Bairros e comunidades', 'geo-alt'],
                    'diagnosticos' => ['Diagnósticos', 'clipboard2-data'],
                    'vulnerabilidades' => ['Vulnerabilidades', 'exclamation-diamond'],
                    'busca-ativa' => ['Busca ativa', 'search'],
                    'monitoramento' => ['Monitoramento', 'activity'],
                    'mapas' => ['Mapas', 'pin-map'],
                    'relatorios' => ['Relatórios', 'bar-chart-line'],
                ],
                ['painel', 'indicadores', 'territorios', 'busca-ativa']
            ),
            'protecao-social-basica' => self::environment(
                'protecao-social-basica',
                'Proteção Social Básica',
                'house-heart',
                'basic',
                'sector',
                [
                    'painel' => ['Painel', 'speedometer2'],
                    'pessoas-prontuarios' => ['Pessoas e prontuários', 'people', 'pessoas.php'],
                    'familias' => ['Famílias', 'house-heart', 'familias.php'],
                    'atendimentos' => ['Atendimentos', 'clipboard2-pulse', 'atendimentos.php'],
                    'solicitacoes' => ['Solicitações', 'inboxes', 'solicitacoes.php'],
                    'beneficios-eventuais' => ['Benefícios Eventuais', 'gift', 'beneficios.php'],
                    'cras-1' => ['CRAS 1', 'geo-alt', 'cras1.php'],
                    'cras-2' => ['CRAS 2', 'geo-alt', 'cras2.php'],
                    'cadastro-unico' => ['Cadastro Único', 'person-vcard'],
                    'bolsa-familia' => ['Bolsa Família', 'wallet2'],
                    'crianca-feliz' => ['Criança Feliz', 'emoji-smile'],
                    'bpc-escola' => ['BPC na Escola', 'mortarboard'],
                    'centro-convivencia-idoso' => ['Centro de Convivência do Idoso', 'person-hearts'],
                    'caic' => ['Centro de Atenção Integral à Criança — CAIC', 'building'],
                    'darquilana-amorim' => ['Centro Integrado Darquilana Amorim', 'buildings'],
                    'casa-cidadao' => ['Casa do Cidadão', 'house-door', 'casa.php'],
                    'inss-digital' => ['INSS Digital', 'laptop'],
                    'relatorios' => ['Relatórios', 'bar-chart-line', 'relatorios.php'],
                ],
                ['painel', 'pessoas-prontuarios', 'familias', 'atendimentos']
            ),
            'protecao-social-especial' => self::environment(
                'protecao-social-especial',
                'Proteção Social Especial',
                'shield-check',
                'special',
                'sector',
                [
                    'painel' => ['Painel', 'speedometer2'],
                    'creas' => ['CREAS', 'shield-check', 'creas.php'],
                    'casa-acolhimento' => ['Casa de Acolhimento', 'house-lock'],
                    'acompanhamentos-especializados' => ['Acompanhamentos especializados', 'clipboard2-pulse'],
                    'violacoes-direitos' => ['Violações de direitos', 'exclamation-octagon'],
                    'crianca-adolescente' => ['Criança e adolescente', 'emoji-smile'],
                    'protecao-mulher' => ['Proteção à mulher', 'gender-female'],
                    'pessoa-idosa' => ['Pessoa idosa', 'person-hearts'],
                    'pessoa-deficiencia' => ['Pessoa com deficiência', 'universal-access'],
                    'direitos-humanos' => ['Direitos Humanos', 'people'],
                    'encaminhamentos' => ['Encaminhamentos', 'send'],
                    'relatorios' => ['Relatórios', 'bar-chart-line'],
                ],
                ['painel', 'creas', 'acompanhamentos-especializados', 'encaminhamentos']
            ),
            'kit-maternidade' => self::environment(
                'kit-maternidade',
                'Kit Maternidade',
                'gift',
                'kit',
                'module',
                [
                    'painel' => ['Painel', 'speedometer2'],
                    'beneficiarias' => ['Beneficiárias', 'person-hearts'],
                    'cadastro' => ['Cadastro e triagem', 'person-plus'],
                    'visitas' => ['Visitas', 'house-check'],
                    'reunioes' => ['Reuniões e atividades', 'people'],
                    'avaliacao' => ['Avaliação', 'clipboard2-check'],
                    'entregas' => ['Entregas', 'gift'],
                    'pos-parto' => ['Pós-parto', 'heart-pulse'],
                    'relatorios' => ['Relatórios', 'bar-chart-line'],
                ],
                ['painel', 'beneficiarias', 'visitas', 'entregas']
            ),
            'aluguel-social' => self::environment(
                'aluguel-social',
                'Aluguel Social',
                'house-check',
                'housing',
                'module',
                [
                    'painel' => ['Painel', 'speedometer2'],
                    'beneficiarios' => ['Beneficiários', 'people'],
                    'solicitacoes' => ['Solicitações', 'inboxes'],
                    'vistorias' => ['Vistorias', 'house-gear'],
                    'pareceres' => ['Pareceres', 'clipboard2-check'],
                    'concessoes' => ['Concessões', 'key'],
                    'pagamentos' => ['Pagamentos', 'wallet2'],
                    'reavaliacoes' => ['Reavaliações', 'arrow-repeat'],
                    'relatorios' => ['Relatórios', 'bar-chart-line'],
                ],
                ['painel', 'beneficiarios', 'solicitacoes', 'pagamentos']
            ),
            'beneficios-eventuais' => self::environment(
                'beneficios-eventuais',
                'Benefícios Eventuais',
                'gift-fill',
                'benefits',
                'module',
                [
                    'painel' => ['Painel', 'speedometer2'],
                    'solicitacoes' => ['Solicitações', 'inboxes'],
                    'triagem' => ['Triagem', 'clipboard2-pulse'],
                    'analises' => ['Análises e pareceres', 'clipboard2-check'],
                    'concessoes' => ['Concessões', 'check2-circle'],
                    'entregas' => ['Entregas', 'box-seam'],
                    'tipos' => ['Tipos e regras', 'sliders'],
                    'relatorios' => ['Relatórios', 'bar-chart-line'],
                ],
                ['painel', 'solicitacoes', 'concessoes', 'entregas']
            ),
            'gestao-acessos' => self::environment(
                'gestao-acessos',
                'Governança e Acessos',
                'shield-lock',
                'governance',
                'module',
                [
                    'painel' => ['Painel', 'speedometer2'],
                    'usuarios' => ['Usuários', 'people'],
                    'cargos' => ['Cargos', 'person-badge'],
                    'perfis' => ['Perfis e níveis', 'person-gear'],
                    'permissoes' => ['Permissões', 'key'],
                    'setores' => ['Setores', 'diagram-3'],
                    'matriz-acesso' => ['Matriz de acesso', 'grid-3x3-gap'],
                    'auditoria' => ['Auditoria', 'journal-text'],
                    'sessoes' => ['Sessões', 'activity'],
                ],
                ['painel', 'usuarios', 'matriz-acesso', 'auditoria']
            ),
            'comida-mesa' => self::environment(
                'comida-mesa',
                'Coari Comida na Mesa',
                'basket2',
                'food',
                'module',
                [
                    'painel' => ['Painel', 'speedometer2', 'comida-mesa/index.php'],
                    'beneficiarios' => ['Beneficiários', 'people', 'comida-mesa/beneficiarios.php'],
                    'nova-inscricao' => ['Nova inscrição', 'person-plus', 'comida-mesa/nova-inscricao.php'],
                    'importar-beneficiarios' => ['Importar beneficiários', 'file-earmark-spreadsheet', 'comida-mesa/importar-beneficiarios.php'],
                    'consulta-cpf' => ['Consultar CPF', 'person-bounding-box', 'comida-mesa/consulta-cpf.php'],
                    'registrar-entrega' => ['Registrar entrega', 'box-seam', 'comida-mesa/registrar-entrega.php'],
                    'competencias' => ['Competências', 'calendar3', 'comida-mesa/competencias.php'],
                    'polos' => ['Polos', 'geo-alt', 'comida-mesa/polos.php'],
                    'documentos' => ['Documentos', 'folder2-open', 'comida-mesa/documentos.php'],
                    'historico' => ['Histórico', 'clock-history', 'comida-mesa/historico.php'],
                    'relatorios' => ['Relatórios', 'bar-chart-line', 'comida-mesa/relatorios.php'],
                ],
                ['painel', 'beneficiarios', 'nova-inscricao', 'registrar-entrega']
            ),
            'primeiro-emprego' => self::environment(
                'primeiro-emprego',
                'Coari Meu Primeiro Emprego',
                'briefcase',
                'employment',
                'module',
                [
                    'painel' => ['Painel', 'speedometer2', 'primeiro-emprego/index.php'],
                    'candidatos' => ['Candidatos', 'people', 'primeiro-emprego/candidatos.php'],
                    'novo-candidato' => ['Novo candidato', 'person-plus', 'primeiro-emprego/cadastro-candidato.php'],
                    'importar-candidatos' => ['Importar candidatos', 'file-earmark-spreadsheet', 'primeiro-emprego/importar-candidatos.php'],
                    'vagas' => ['Vagas e oportunidades', 'briefcase', 'primeiro-emprego/vagas.php'],
                    'parceiros' => ['Órgãos e instituições parceiras', 'buildings', 'primeiro-emprego/parceiros.php'],
                    'lotacoes' => ['Lotações', 'diagram-3', 'primeiro-emprego/lotacoes.php'],
                    'encaminhamentos' => ['Encaminhamentos', 'send', 'primeiro-emprego/encaminhamentos.php'],
                    'documentacao' => ['Documentação', 'folder2-open', 'primeiro-emprego/documentacao.php'],
                    'frequencia' => ['Frequência', 'calendar-check', 'primeiro-emprego/frequencia.php'],
                    'bolsas' => ['Bolsas', 'wallet2', 'primeiro-emprego/bolsas.php'],
                    'capacitacoes' => ['Capacitações', 'mortarboard', 'primeiro-emprego/capacitacoes.php'],
                    'acompanhamentos' => ['Acompanhamentos', 'clipboard2-pulse', 'primeiro-emprego/acompanhamentos.php'],
                    'relatorios' => ['Relatórios', 'bar-chart-line', 'primeiro-emprego/relatorios.php'],
                    'configuracoes' => ['Configurações', 'gear', 'primeiro-emprego/configuracoes.php'],
                ],
                ['painel', 'candidatos', 'novo-candidato', 'vagas']
            ),
        ];
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /** @return array<string, mixed>|null */
    public static function findPage(string $environment, string $page): ?array
    {
        return self::find($environment)['pages'][$page] ?? null;
    }

    /**
     * @param array<string, array{0: string, 1: string, 2?: string}> $specs
     * @param list<string> $mobilePages
     * @return array<string, mixed>
     */
    private static function environment(
        string $key,
        string $name,
        string $icon,
        string $theme,
        string $kind,
        array $specs,
        array $mobilePages
    ): array {
        $pages = [];

        foreach ($specs as $pageKey => $spec) {
            $publicHref = $spec[2] ?? null;
            $href = $publicHref ?? 'setor.php?ambiente=' . rawurlencode($key) . '&pagina=' . rawurlencode($pageKey);
            $pages[$pageKey] = [
                'key' => $pageKey,
                'label' => $spec[0],
                'icon' => $spec[1],
                'page' => $pageKey,
                'href' => $href,
                'target' => $publicHref === null ? 'view' : 'public',
                'view' => $publicHref === null ? $key . '/pages/' . $pageKey . '.php' : null,
                'mobile' => in_array($pageKey, $mobilePages, true),
            ];
        }

        $first = array_key_first($pages);
        $description = $kind === 'module'
            ? 'Ambiente independente com fluxos e navegação próprios.'
            : 'Ambiente próprio da SEMAS, com indicadores e páginas contextuais.';

        return [
            'key' => $key,
            'name' => $name,
            'kind' => $kind,
            'icon' => $icon,
            'theme' => $theme,
            'description' => $description,
            'home_page' => $first,
            'home' => $pages[$first]['href'],
            'pages' => $pages,
            'menu' => 'frontend/modules/' . $key . '/menu.php',
            // Compatibilidade temporária com os consumidores existentes.
            'items' => array_values($pages),
            'assets' => [
                'css' => 'assets/css/modules/' . $key . '.css',
                'js' => 'assets/js/modules/' . $key . '.js',
            ],
        ];
    }
}
