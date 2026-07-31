<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Catálogo visual único do Portal SIGAS. Não concede acesso: a autorização
 * continua sendo responsabilidade das rotas e serviços no servidor.
 */
final class ModuleRegistry
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'planejamento-gestao' => self::area('planejamento-gestao', 'Planejamento e Gestão', 'diagram-3', 'planning', [
                'Painel', 'Planos de ação', 'Metas', 'Cronogramas', 'Rede de unidades', 'Equipes', 'Documentos', 'Monitoramento', 'Relatórios', 'Configurações',
            ], ['Painel' => 'administracao.php', 'Rede de unidades' => 'unidades.php', 'Equipes' => 'usuarios.php', 'Configurações' => 'configuracoes.php']),
            'vigilancia-socioassistencial' => self::area('vigilancia-socioassistencial', 'Vigilância Socioassistencial', 'bar-chart-steps', 'vigilance', [
                'Painel', 'Indicadores', 'Territórios', 'Bairros e comunidades', 'Diagnósticos', 'Vulnerabilidades', 'Busca ativa', 'Monitoramento', 'Mapas', 'Relatórios',
            ]),
            'protecao-social-basica' => self::area('protecao-social-basica', 'Proteção Social Básica', 'house-heart', 'basic', [
                'Painel', 'Pessoas e prontuários', 'Famílias', 'Atendimentos', 'Solicitações', 'Benefícios Eventuais', 'CRAS 1', 'CRAS 2', 'Cadastro Único', 'Bolsa Família', 'Criança Feliz', 'BPC na Escola', 'Centro de Convivência do Idoso', 'CAIC', 'Centro Integrado Darquilana Amorim', 'Casa do Cidadão', 'INSS Digital', 'Relatórios',
            ], [
                'Pessoas e prontuários' => 'pessoas.php', 'Famílias' => 'familias.php', 'Atendimentos' => 'atendimentos.php', 'Solicitações' => 'solicitacoes.php', 'Benefícios Eventuais' => 'beneficios.php', 'CRAS 1' => 'cras1.php', 'CRAS 2' => 'cras2.php', 'Casa do Cidadão' => 'casa.php', 'Relatórios' => 'relatorios.php',
            ]),
            'protecao-social-especial' => self::area('protecao-social-especial', 'Proteção Social Especial', 'shield-check', 'special', [
                'Painel', 'CREAS', 'Casa de Acolhimento', 'Acompanhamentos especializados', 'Violações de direitos', 'Criança e adolescente', 'Proteção à mulher', 'Pessoa idosa', 'Pessoa com deficiência', 'Direitos Humanos', 'Encaminhamentos', 'Relatórios',
            ], ['CREAS' => 'creas.php']),
            'comida-mesa' => [
                'name' => 'Coari Comida na Mesa', 'icon' => 'basket2', 'theme' => 'food', 'kind' => 'module', 'home' => 'modulo.php',
                'description' => 'Beneficiários, entregas, competências e acompanhamento do programa.',
                'items' => self::items(['Painel', 'Beneficiários', 'Nova inscrição', 'Consultar CPF', 'Registrar entrega', 'Competências', 'Polos', 'Documentos', 'Histórico', 'Relatórios'], [
                    'Painel' => 'modulo.php', 'Beneficiários' => 'modulo.php', 'Nova inscrição' => 'modulo.php?action=new', 'Consultar CPF' => 'consulta-documento.php', 'Registrar entrega' => 'consulta-documento.php', 'Competências' => 'modulo.php#competencias', 'Polos' => 'modulo.php#polos', 'Documentos' => 'modulo.php#documentos', 'Histórico' => 'modulo.php#historico', 'Relatórios' => 'modulo.php#relatorios',
                ]),
            ],
            'primeiro-emprego' => [
                'name' => 'Coari Meu Primeiro Emprego', 'icon' => 'briefcase', 'theme' => 'employment', 'kind' => 'module', 'home' => 'primeiro-emprego/index.php',
                'description' => 'Candidatos, vagas, parcerias e acompanhamento de empregabilidade.',
                'items' => self::items(['Painel', 'Candidatos', 'Novo candidato', 'Vagas e oportunidades', 'Órgãos e instituições parceiras', 'Lotações', 'Encaminhamentos', 'Documentação', 'Frequência', 'Bolsas', 'Capacitações', 'Acompanhamentos', 'Relatórios', 'Configurações'], [
                    'Painel' => 'primeiro-emprego/index.php', 'Candidatos' => 'primeiro-emprego/candidatos.php', 'Novo candidato' => 'primeiro-emprego/cadastro-candidato.php', 'Vagas e oportunidades' => 'primeiro-emprego/vagas.php', 'Órgãos e instituições parceiras' => 'primeiro-emprego/empresas.php', 'Lotações' => 'primeiro-emprego/index.php?pagina=lotacoes', 'Encaminhamentos' => 'primeiro-emprego/encaminhamentos.php', 'Documentação' => 'primeiro-emprego/index.php?pagina=documentacao', 'Frequência' => 'primeiro-emprego/index.php?pagina=frequencia', 'Bolsas' => 'primeiro-emprego/index.php?pagina=bolsas', 'Capacitações' => 'primeiro-emprego/capacitacoes.php', 'Acompanhamentos' => 'primeiro-emprego/index.php?pagina=acompanhamentos', 'Relatórios' => 'primeiro-emprego/relatorios.php', 'Configurações' => 'primeiro-emprego/index.php?pagina=configuracoes',
                ]),
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /** @return array<string, mixed> */
    private static function area(string $key, string $name, string $icon, string $theme, array $labels, array $routes = []): array
    {
        return ['name' => $name, 'icon' => $icon, 'theme' => $theme, 'kind' => 'sector', 'home' => 'setor.php?ambiente=' . $key, 'description' => 'Ambiente próprio da SEMAS, com indicadores, páginas e fluxos contextuais.', 'items' => self::items($labels, $routes, $key)];
    }

    /** @return list<array<string, string>> */
    private static function items(array $labels, array $routes = [], string $area = ''): array
    {
        return array_map(static function (string $label) use ($routes, $area): array {
            $slug = self::slug($label);
            return ['label' => $label, 'icon' => self::icon($label), 'page' => $slug, 'href' => $routes[$label] ?? ('setor.php?ambiente=' . $area . '&pagina=' . $slug)];
        }, $labels);
    }

    private static function slug(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        return trim(strtolower((string) preg_replace('/[^a-z0-9]+/', '-', $value)), '-');
    }

    private static function icon(string $label): string
    {
        return match ($label) {
            'Painel' => 'speedometer2', 'Relatórios' => 'bar-chart-line', 'Configurações' => 'gear', 'Pessoas e prontuários', 'Candidatos', 'Beneficiários' => 'people', 'Novo candidato', 'Nova inscrição' => 'person-plus', 'Famílias' => 'house-heart', 'Atendimentos', 'Acompanhamentos especializados', 'Acompanhamentos' => 'clipboard2-pulse', 'Documentos', 'Documentação' => 'folder2-open', 'Mapas', 'Territórios', 'Bairros e comunidades', 'Polos' => 'geo-alt', default => 'grid-1x2',
        };
    }
}
