<?php
declare(strict_types=1);

namespace Sigesp\Shared\Presentation;

/** Presentation-only content used where an operational module has no repository yet. */
final class DemoData
{
    public static function module(string $module, string $screen = 'index'): array
    {
        $catalog = [
            'responsaveis' => ['Responsáveis', 'Acompanhe vínculos, autorizações e contatos de referência.', 'Responsáveis cadastrados', 'Novo responsável'],
            'documentos' => ['Documentos', 'Central de análise, validade e conformidade documental.', 'Documentos em acompanhamento', 'Analisar documentos'],
            'modalidades' => ['Modalidades', 'Organize as práticas esportivas, categorias e vínculos.', 'Modalidades ativas', 'Nova modalidade'],
            'categorias' => ['Categorias', 'Defina faixas etárias e critérios de participação.', 'Categorias disponíveis', 'Nova categoria'],
            'equipes' => ['Equipes', 'Acompanhe comissões, atletas e calendário das equipes.', 'Equipes ativas', 'Nova equipe'],
            'treinos' => ['Treinos', 'Planeje atividades, locais e participantes.', 'Treinos programados', 'Novo treino'],
            'frequencias' => ['Frequência', 'Registre presença e acompanhe a participação.', 'Chamadas recentes', 'Registrar frequência'],
            'avaliacoes' => ['Avaliações', 'Visualize a evolução esportiva e os acompanhamentos.', 'Avaliações realizadas', 'Nova avaliação'],
            'eventos' => ['Eventos', 'Planeje eventos esportivos, programação e participantes.', 'Eventos programados', 'Novo evento'],
            'competicoes' => ['Competições', 'Organize calendários, delegações e resultados.', 'Competições ativas', 'Nova competição'],
            'inscricoes' => ['Inscrições', 'Gerencie inscrições de atletas e equipes.', 'Inscrições em acompanhamento', 'Nova inscrição'],
            'resultados' => ['Resultados', 'Registre resultados e conquistas esportivas.', 'Resultados recentes', 'Novo resultado'],
            'beneficios' => ['Benefícios', 'Acompanhe concessões, pendências e prestações de contas.', 'Benefícios concedidos', 'Novo benefício'],
            'espacos-esportivos' => ['Espaços esportivos', 'Administre locais, manutenção e disponibilidade.', 'Espaços cadastrados', 'Novo espaço'],
            'reservas' => ['Reservas', 'Visualize a agenda e evite conflitos de uso.', 'Reservas programadas', 'Nova reserva'],
            'materiais' => ['Materiais esportivos', 'Controle acervo, empréstimos e manutenções.', 'Materiais cadastrados', 'Novo material'],
            'relatorios' => ['Relatórios', 'Consulte indicadores e prepare exportações institucionais.', 'Relatórios disponíveis', 'Gerar relatório'],
            'usuarios' => ['Usuários', 'Gerencie acessos, perfis e atividade recente.', 'Usuários do sistema', 'Novo usuário'],
            'permissoes' => ['Perfis e permissões', 'Configure perfis de acesso por módulo e ação.', 'Matriz de permissões', 'Salvar permissões'],
            'auditoria' => ['Auditoria', 'Consulte o histórico de atividades e alterações.', 'Atividades registradas', 'Exportar auditoria'],
            'configuracoes' => ['Configurações', 'Ajuste as preferências institucionais do sistema.', 'Preferências do sistema', 'Salvar configurações'],
        ];
        [$title, $description, $section, $action] = $catalog[$module] ?? ['Módulo', 'Área administrativa do SIGESP.', 'Registros', 'Nova ação'];
        $cards = [
            ['◉', 'Em acompanhamento', '—', 'Dados operacionais serão exibidos aqui'],
            ['✓', 'Registros ativos', '—', 'Indicador disponível após integração'],
            ['⌁', 'Pendências', '—', 'Nenhuma operação simulada'],
            ['▥', 'Última atualização', 'Hoje', 'Interface preparada'],
        ];
        return compact('module', 'screen', 'title', 'description', 'section', 'action', 'cards');
    }
}
