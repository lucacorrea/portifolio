<?php
declare(strict_types=1);

namespace Sigesp\Demo;

final class DemoData
{
    public static function page(string $module): array
    {
        $definitions = self::definitions();
        $definition = $definitions[$module] ?? $definitions['dashboard'];
        $records = self::records($module);
        $columns = $definition['columns'];
        return [
            'module' => $module,
            'title' => $definition['title'],
            'description' => $definition['description'],
            'action' => $definition['action'],
            'actionPath' => $definition['actionPath'],
            'stats' => [
                ['label' => 'Total', 'value' => count($records), 'tone' => 'primary'],
                ['label' => 'Ativos ou regulares', 'value' => self::countStatus($records, ['ativo', 'ativa', 'aprovado', 'regular', 'confirmada', 'disponível', 'válida']), 'tone' => 'success'],
                ['label' => 'Em acompanhamento', 'value' => self::countStatus($records, ['pendente', 'em análise', 'agendado', 'agendada', 'em andamento']), 'tone' => 'warning'],
                ['label' => 'Atualização', 'value' => 'Hoje', 'tone' => 'info'],
            ],
            'columns' => $columns,
            'records' => $records,
            'filters' => [
                ['key' => 'status', 'label' => 'Situação', 'type' => 'select', 'options' => ['Todos', 'Ativo', 'Pendente', 'Concluído', 'Inativo']],
                ['key' => 'grupo', 'label' => 'Categoria', 'type' => 'select', 'options' => ['Todas', 'Principal', 'Base', 'Administrativo']],
                ['key' => 'periodo', 'label' => 'Período', 'type' => 'select', 'options' => ['Agosto de 2026', 'Julho de 2026', 'Últimos 90 dias']],
            ],
            'highlights' => array_map(static function (array $record) use ($module): array {
                return [
                    'title' => (string) ($record['nome'] ?? $record['atleta'] ?? $record['equipe'] ?? $record['item'] ?? 'Registro demonstrativo'),
                    'detail' => (string) ($record['status'] ?? $record['modalidade'] ?? $record['tipo'] ?? 'Disponível para visualização'),
                    'action' => 'Ver módulo',
                    'path' => '/' . ($module === 'carteiras-digitais' ? 'carteiras' : $module),
                ];
            }, array_slice($records, 0, 3)),
        ];
    }

    private static function records(string $module): array
    {
        if ($module === 'atletas') return AtletasDemoData::all();
        $sets = PeopleDemoData::datasets()
            + SportsDemoData::datasets()
            + EventsDemoData::datasets()
            + ResourcesDemoData::datasets()
            + SystemDemoData::datasets();
        $aliases = ['espacos-esportivos' => 'espacos', 'carteiras-digitais' => 'carteiras'];
        return $sets[$aliases[$module] ?? $module] ?? [];
    }

    private static function countStatus(array $records, array $accepted): int
    {
        return count(array_filter($records, static function (array $record) use ($accepted): bool {
            return in_array(mb_strtolower((string) ($record['status'] ?? '')), $accepted, true);
        }));
    }

    private static function definitions(): array
    {
        return [
            'dashboard' => self::definition('Dashboard', 'Indicadores da gestão esportiva municipal.', 'Cadastrar atleta', '/atletas/novo', ['nome' => 'Indicador', 'status' => 'Situação']),
            'atletas' => self::definition('Atletas', 'Cadastros, vínculos e evolução esportiva.', 'Novo atleta', '/atletas/novo', ['codigo' => 'Código', 'nome' => 'Atleta', 'modalidade' => 'Modalidade', 'categoria' => 'Categoria', 'documentos_status' => 'Documentos', 'frequencia' => 'Frequência', 'status' => 'Situação']),
            'responsaveis' => self::definition('Responsáveis', 'Vínculos, autorizações e contatos.', 'Novo responsável', '/responsaveis/novo', ['nome' => 'Responsável', 'parentesco' => 'Parentesco', 'atletas' => 'Atletas', 'telefone' => 'Telefone', 'status' => 'Situação']),
            'documentos' => self::definition('Documentos', 'Análise, validade e conformidade documental.', 'Analisar documentos', '/documentos/analise', ['atleta' => 'Atleta', 'tipo' => 'Documento', 'validade' => 'Validade', 'enviado_em' => 'Envio', 'status' => 'Situação']),
            'carteiras-digitais' => self::definition('Carteiras digitais', 'Identificação esportiva demonstrativa.', 'Visualizar carteiras', '/carteiras', ['codigo' => 'Carteira', 'atleta' => 'Atleta', 'modalidade' => 'Modalidade', 'validade' => 'Validade', 'status' => 'Situação']),
            'modalidades' => self::definition('Modalidades', 'Práticas esportivas e documentos obrigatórios.', 'Nova modalidade', '/modalidades/novo', ['nome' => 'Modalidade', 'atletas' => 'Atletas', 'equipes' => 'Equipes', 'categorias' => 'Categorias', 'treinadores' => 'Treinadores', 'status' => 'Situação']),
            'categorias' => self::definition('Categorias', 'Faixas etárias e critérios de participação.', 'Nova categoria', '/categorias/novo', ['nome' => 'Categoria', 'modalidade' => 'Modalidade', 'idade_minima' => 'Idade mínima', 'idade_maxima' => 'Idade máxima', 'sexo' => 'Sexo', 'atletas' => 'Atletas', 'status' => 'Situação']),
            'equipes' => self::definition('Equipes', 'Elencos, comissão e calendário.', 'Nova equipe', '/equipes/novo', ['nome' => 'Equipe', 'modalidade' => 'Modalidade', 'categoria' => 'Categoria', 'treinador' => 'Treinador', 'atletas' => 'Atletas', 'status' => 'Situação']),
            'treinos' => self::definition('Treinos', 'Agenda, locais e participantes.', 'Novo treino', '/treinos/novo', ['equipe' => 'Equipe', 'treinador' => 'Treinador', 'local' => 'Local', 'data' => 'Data', 'horario' => 'Horário', 'participantes' => 'Participantes', 'status' => 'Situação']),
            'frequencias' => self::definition('Frequências', 'Chamadas e participação nos treinos.', 'Registrar frequência', '/frequencias/registrar', ['atleta' => 'Atleta', 'equipe' => 'Equipe', 'data' => 'Data', 'presenca' => 'Presença', 'percentual' => 'Percentual', 'status' => 'Situação']),
            'avaliacoes' => self::definition('Avaliações', 'Indicadores físicos e evolução.', 'Nova avaliação', '/avaliacoes/novo', ['atleta' => 'Atleta', 'data' => 'Data', 'peso' => 'Peso', 'altura' => 'Altura', 'imc' => 'IMC', 'resistencia' => 'Resistência', 'status' => 'Situação']),
            'eventos' => self::definition('Eventos', 'Programação esportiva municipal.', 'Novo evento', '/eventos/novo', ['nome' => 'Evento', 'data' => 'Data', 'local' => 'Local', 'modalidade' => 'Modalidade', 'participantes' => 'Participantes', 'status' => 'Situação']),
            'competicoes' => self::definition('Competições', 'Confrontos, classificação e resultados.', 'Nova competição', '/competicoes/novo', ['nome' => 'Competição', 'modalidade' => 'Modalidade', 'inicio' => 'Início', 'equipes' => 'Equipes', 'fase' => 'Fase', 'status' => 'Situação']),
            'inscricoes' => self::definition('Inscrições', 'Participação de atletas e equipes.', 'Nova inscrição', '/inscricoes/novo', ['atleta' => 'Atleta', 'evento' => 'Evento', 'documentacao' => 'Documentação', 'inscrito_em' => 'Inscrição', 'status' => 'Situação']),
            'resultados' => self::definition('Resultados', 'Colocações, medalhas e pontuações.', 'Novo resultado', '/resultados/novo', ['competicao' => 'Competição', 'atleta' => 'Atleta', 'equipe' => 'Equipe', 'colocacao' => 'Colocação', 'medalha' => 'Medalha', 'pontuacao' => 'Pontos', 'status' => 'Situação']),
            'beneficios' => self::definition('Benefícios', 'Concessões e prestações de contas.', 'Conceder benefício', '/beneficios/novo', ['atleta' => 'Atleta', 'tipo' => 'Benefício', 'evento' => 'Evento', 'valor' => 'Valor', 'prestacao' => 'Prestação', 'status' => 'Situação']),
            'espacos-esportivos' => self::definition('Espaços esportivos', 'Disponibilidade, estrutura e manutenção.', 'Novo espaço', '/espacos-esportivos/novo', ['nome' => 'Espaço', 'bairro' => 'Bairro', 'capacidade' => 'Capacidade', 'estrutura' => 'Estrutura', 'disponibilidade' => 'Disponibilidade', 'status' => 'Situação']),
            'reservas' => self::definition('Reservas', 'Agenda de utilização dos espaços.', 'Nova reserva', '/reservas/novo', ['espaco' => 'Espaço', 'data' => 'Data', 'horario' => 'Horário', 'finalidade' => 'Finalidade', 'responsavel' => 'Responsável', 'status' => 'Situação']),
            'materiais' => self::definition('Materiais esportivos', 'Estoque, empréstimos e manutenção.', 'Novo material', '/materiais/novo', ['item' => 'Item', 'categoria' => 'Categoria', 'estoque' => 'Estoque', 'disponivel' => 'Disponível', 'emprestado' => 'Emprestado', 'manutencao' => 'Manutenção', 'status' => 'Situação']),
            'relatorios' => self::definition('Relatórios', 'Indicadores e exportações simuladas.', 'Gerar relatório', '/relatorios/visualizar', ['nome' => 'Relatório', 'periodo' => 'Período', 'registros' => 'Registros', 'formato' => 'Visualização', 'status' => 'Situação']),
            'usuarios' => self::definition('Usuários', 'Perfis e atividade de acesso.', 'Novo usuário', '/usuarios/novo', ['nome' => 'Usuário', 'perfil' => 'Perfil', 'email' => 'E-mail', 'ultimo_acesso' => 'Último acesso', 'status' => 'Situação']),
            'permissoes' => self::definition('Perfis e permissões', 'Matriz visual de acessos.', 'Simular alterações', '/permissoes', ['perfil' => 'Perfil', 'modulo' => 'Módulo', 'permissoes' => 'Permissões', 'status' => 'Situação']),
            'auditoria' => self::definition('Auditoria', 'Histórico demonstrativo de atividades.', 'Exportar auditoria', '/auditoria', ['usuario' => 'Usuário', 'acao' => 'Ação', 'modulo' => 'Módulo', 'data' => 'Data', 'ip' => 'IP fictício', 'status' => 'Situação']),
            'configuracoes' => self::definition('Configurações', 'Preferências institucionais do ambiente demo.', 'Salvar configurações', '/configuracoes', ['secao' => 'Seção', 'configuracao' => 'Configuração', 'valor' => 'Valor', 'status' => 'Situação']),
        ];
    }

    private static function definition(string $title, string $description, string $action, string $actionPath, array $columns): array
    {
        return compact('title', 'description', 'action', 'actionPath', 'columns');
    }
}
