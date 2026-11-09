<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Fonte única da navegação visual dos módulos independentes do SIGAS.
 *
 * Setores como CRAS 1, CRAS 2 e CREAS pertencem à governança/tra trajetória
 * da pessoa e não são módulos de navegação.
 */
final class ModuleRegistry
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'kit-maternidade' => self::environment(
                'kit-maternidade',
                'Kit Maternidade',
                'gift',
                'kit',
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

        return [
            'key' => $key,
            'name' => $name,
            'kind' => 'module',
            'icon' => $icon,
            'theme' => $theme,
            'description' => 'Ambiente independente com fluxos e navegação próprios.',
            'home_page' => $first,
            'home' => $pages[$first]['href'],
            'pages' => $pages,
            'menu' => 'frontend/modules/' . $key . '/menu.php',
            'items' => array_values($pages),
            'assets' => [
                'css' => 'assets/css/modules/' . $key . '.css',
                'js' => 'assets/js/modules/' . $key . '.js',
            ],
        ];
    }
}
