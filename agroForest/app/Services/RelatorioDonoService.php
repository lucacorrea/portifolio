<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use PDO;
use Throwable;

class RelatorioDonoService
{
    private PDO $pdo;

    private array $tableCache = [];
    private array $columnCache = [];

    private array $statusConcluidos = [
        'concluido',
        'concluído',
        'finalizado',
        'finalizada',
        'aprovado',
        'aprovada',
        'encerrado',
        'encerrada'
    ];

    private array $statusPendentes = [
        'pendente',
        'aberto',
        'aberta',
        'em andamento',
        'em_andamento',
        'em análise',
        'em analise',
        'aguardando',
        'aguardando análise',
        'aguardando analise'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function gerar(string $periodoSelecionado = 'trimestre'): array
    {
        $periodo = $this->resolverPeriodo($periodoSelecionado);

        $protocolos = $this->buscarProtocolos($periodo);
        $orcamentos = $this->buscarOrcamentos($periodo);
        $usuarios = $this->buscarUsuarios();

        $graficos = [
            'evolucao' => $this->graficoEvolucaoProtocolos(),
            'status' => $this->graficoStatusProtocolos($protocolos),
        ];

        $areas = $this->montarResumoAreas($protocolos, $orcamentos, $usuarios);
        $ranking = $this->montarRanking($areas);
        $alertas = $this->montarAlertas($protocolos, $orcamentos, $usuarios);
        $movimentos = $this->buscarUltimosMovimentos();

        return [
            'periodo' => $periodo,
            'indicadores' => [
                'protocolos_total' => $protocolos['total'],
                'protocolos_concluidos' => $protocolos['concluidos'],
                'protocolos_pendentes' => $protocolos['pendentes'],
                'protocolos_atrasados' => $protocolos['atrasados'],
                'protocolos_trend' => $protocolos['trend'],

                'orcamentos_total' => $orcamentos['total'],
                'orcamentos_finalizados' => $orcamentos['finalizados'],
                'orcamentos_pendentes' => $orcamentos['pendentes'],
                'orcamentos_trend' => $orcamentos['trend'],

                'valor_total_orcamentos' => $orcamentos['valor_total'],
                'ticket_medio' => $orcamentos['ticket_medio'],

                'usuarios_total' => $usuarios['total'],
                'usuarios_ativos' => $usuarios['ativos'],
                'usuarios_inativos' => $usuarios['inativos'],
                'usuarios_ativos_percentual' => $usuarios['percentual_ativos'],
            ],
            'graficos' => $graficos,
            'areas' => $areas,
            'ranking' => $ranking,
            'alertas' => $alertas,
            'movimentos' => $movimentos,
        ];
    }

    public static function relatorioVazio(string $periodoSelecionado = 'trimestre'): array
    {
        $hoje = new DateTimeImmutable('now');

        return [
            'periodo' => [
                'valor' => $periodoSelecionado,
                'label' => 'Período selecionado',
                'inicio' => $hoje->modify('first day of this month')->setTime(0, 0, 0),
                'fim' => $hoje->setTime(23, 59, 59),
            ],
            'indicadores' => [
                'protocolos_total' => 0,
                'protocolos_concluidos' => 0,
                'protocolos_pendentes' => 0,
                'protocolos_atrasados' => 0,
                'protocolos_trend' => 0,

                'orcamentos_total' => 0,
                'orcamentos_finalizados' => 0,
                'orcamentos_pendentes' => 0,
                'orcamentos_trend' => 0,

                'valor_total_orcamentos' => 0,
                'ticket_medio' => 0,

                'usuarios_total' => 0,
                'usuarios_ativos' => 0,
                'usuarios_inativos' => 0,
                'usuarios_ativos_percentual' => 0,
            ],
            'graficos' => [
                'evolucao' => [
                    'labels' => [],
                    'abertos' => [],
                    'concluidos' => [],
                    'pendentes' => [],
                ],
                'status' => [
                    'labels' => ['Concluídos', 'Em análise', 'Pendentes'],
                    'valores' => [0, 0, 0],
                ],
            ],
            'areas' => [],
            'ranking' => [],
            'alertas' => [
                [
                    'tipo' => 'warning',
                    'titulo' => 'Relatório sem dados',
                    'descricao' => 'Não foi possível carregar os indicadores do banco neste momento.'
                ]
            ],
            'movimentos' => [],
        ];
    }

    private function resolverPeriodo(string $periodoSelecionado): array
    {
        $hoje = new DateTimeImmutable('now');
        $fim = $hoje->setTime(23, 59, 59);

        switch ($periodoSelecionado) {
            case 'mes':
                $inicio = $hoje->modify('first day of this month')->setTime(0, 0, 0);
                $label = 'Este mês';
                break;

            case 'semestre':
                $inicio = $hoje->modify('first day of this month')->modify('-5 months')->setTime(0, 0, 0);
                $label = 'Último semestre';
                break;

            case 'ano':
                $inicio = $hoje->setDate((int)$hoje->format('Y'), 1, 1)->setTime(0, 0, 0);
                $label = 'Este ano';
                break;

            case 'trimestre':
            default:
                $inicio = $hoje->modify('first day of this month')->modify('-2 months')->setTime(0, 0, 0);
                $label = 'Último trimestre';
                $periodoSelecionado = 'trimestre';
                break;
        }

        return [
            'valor' => $periodoSelecionado,
            'label' => $label,
            'inicio' => $inicio,
            'fim' => $fim,
        ];
    }

    private function buscarProtocolos(array $periodo): array
    {
        $table = $this->firstExistingTable(['protocolos', 'protocolo']);

        if (!$table) {
            return [
                'total' => 0,
                'concluidos' => 0,
                'pendentes' => 0,
                'atrasados' => 0,
                'trend' => 0,
            ];
        }

        $dateCol = $this->firstExistingColumn($table, [
            'criado_em',
            'created_at',
            'data_criacao',
            'data_abertura',
            'data_cadastro',
        ]);

        $updateCol = $this->firstExistingColumn($table, [
            'atualizado_em',
            'updated_at',
            'data_atualizacao',
            'data_movimentacao',
            'criado_em',
            'created_at',
            'data_criacao',
            'data_abertura',
        ]);

        $statusCol = $this->firstExistingColumn($table, ['status', 'situacao']);

        [$wherePeriodo, $paramsPeriodo] = $this->wherePeriodo($dateCol, $periodo['inicio'], $periodo['fim'], 'p');

        $total = $this->contar($table, $wherePeriodo, $paramsPeriodo);

        [$whereConcluidos, $paramsConcluidos] = $this->whereStatusIn($statusCol, $this->statusConcluidos, 'c');
        $concluidos = $this->contar(
            $table,
            array_merge($wherePeriodo, $whereConcluidos),
            array_merge($paramsPeriodo, $paramsConcluidos)
        );

        [$wherePendentes, $paramsPendentes] = $this->whereStatusIn($statusCol, $this->statusPendentes, 'pe');
        $pendentes = $this->contar(
            $table,
            array_merge($wherePeriodo, $wherePendentes),
            array_merge($paramsPeriodo, $paramsPendentes)
        );

        if ($pendentes === 0 && $total > 0) {
            $pendentes = max(0, $total - $concluidos);
        }

        $atrasados = $this->contarAtrasados($table, $updateCol, $statusCol);

        $periodoAnterior = $this->periodoAnterior($periodo);
        [$whereAnterior, $paramsAnterior] = $this->wherePeriodo($dateCol, $periodoAnterior['inicio'], $periodoAnterior['fim'], 'pa');
        $totalAnterior = $this->contar($table, $whereAnterior, $paramsAnterior);

        return [
            'total' => $total,
            'concluidos' => $concluidos,
            'pendentes' => $pendentes,
            'atrasados' => $atrasados,
            'trend' => $this->calcularVariacao($total, $totalAnterior),
        ];
    }

    private function buscarOrcamentos(array $periodo): array
    {
        $table = $this->firstExistingTable(['orcamentos', 'orcamento']);

        if (!$table) {
            return [
                'total' => 0,
                'finalizados' => 0,
                'pendentes' => 0,
                'valor_total' => 0.0,
                'ticket_medio' => 0.0,
                'trend' => 0,
            ];
        }

        $dateCol = $this->firstExistingColumn($table, [
            'finalizado_em',
            'data_finalizacao',
            'criado_em',
            'created_at',
            'data_criacao',
            'data_orcamento',
        ]);

        $statusCol = $this->firstExistingColumn($table, ['status', 'situacao']);
        $valorCol = $this->firstExistingColumn($table, [
            'valor_total',
            'total',
            'valor',
            'valor_final',
            'total_geral',
        ]);

        [$wherePeriodo, $paramsPeriodo] = $this->wherePeriodo($dateCol, $periodo['inicio'], $periodo['fim'], 'o');

        $total = $this->contar($table, $wherePeriodo, $paramsPeriodo);

        [$whereFinalizados, $paramsFinalizados] = $this->whereStatusIn($statusCol, $this->statusConcluidos, 'of');

        $finalizados = $this->contar(
            $table,
            array_merge($wherePeriodo, $whereFinalizados),
            array_merge($paramsPeriodo, $paramsFinalizados)
        );

        if (!$statusCol) {
            $finalizados = $total;
        }

        $pendentes = max(0, $total - $finalizados);

        $valorTotal = 0.0;

        if ($valorCol) {
            $valorTotal = $this->somar(
                $table,
                $valorCol,
                array_merge($wherePeriodo, $whereFinalizados),
                array_merge($paramsPeriodo, $paramsFinalizados)
            );

            if (!$statusCol) {
                $valorTotal = $this->somar($table, $valorCol, $wherePeriodo, $paramsPeriodo);
            }
        }

        $ticketMedio = $finalizados > 0 ? $valorTotal / $finalizados : 0.0;

        $periodoAnterior = $this->periodoAnterior($periodo);
        [$whereAnterior, $paramsAnterior] = $this->wherePeriodo($dateCol, $periodoAnterior['inicio'], $periodoAnterior['fim'], 'oa');

        $totalAnterior = $this->contar($table, $whereAnterior, $paramsAnterior);

        return [
            'total' => $total,
            'finalizados' => $finalizados,
            'pendentes' => $pendentes,
            'valor_total' => $valorTotal,
            'ticket_medio' => $ticketMedio,
            'trend' => $this->calcularVariacao($total, $totalAnterior),
        ];
    }

    private function buscarUsuarios(): array
    {
        $table = $this->firstExistingTable(['usuarios', 'users']);

        if (!$table) {
            return [
                'total' => 0,
                'ativos' => 0,
                'inativos' => 0,
                'percentual_ativos' => 0,
            ];
        }

        $total = $this->contar($table);

        $ativoCol = $this->firstExistingColumn($table, ['ativo', 'active']);
        $statusCol = $this->firstExistingColumn($table, ['status', 'situacao']);

        if ($ativoCol) {
            $ativos = $this->contar($table, [$this->esc($ativoCol) . ' = :ativo'], [':ativo' => 1]);
        } elseif ($statusCol) {
            [$whereAtivos, $paramsAtivos] = $this->whereStatusIn($statusCol, ['ativo', 'active', 'habilitado'], 'ua');
            $ativos = $this->contar($table, $whereAtivos, $paramsAtivos);
        } else {
            $ativos = $total;
        }

        $inativos = max(0, $total - $ativos);

        return [
            'total' => $total,
            'ativos' => $ativos,
            'inativos' => $inativos,
            'percentual_ativos' => $this->percentual($ativos, $total),
        ];
    }

    private function graficoEvolucaoProtocolos(): array
    {
        $table = $this->firstExistingTable(['protocolos', 'protocolo']);

        $labels = [];
        $abertos = [];
        $concluidos = [];
        $pendentes = [];

        $hoje = new DateTimeImmutable('now');
        $inicioBase = $hoje->modify('first day of this month')->modify('-5 months')->setTime(0, 0, 0);

        if (!$table) {
            for ($i = 0; $i < 6; $i++) {
                $mes = $inicioBase->modify("+{$i} months");
                $labels[] = $this->labelMes($mes);
                $abertos[] = 0;
                $concluidos[] = 0;
                $pendentes[] = 0;
            }

            return compact('labels', 'abertos', 'concluidos', 'pendentes');
        }

        $dateCol = $this->firstExistingColumn($table, [
            'criado_em',
            'created_at',
            'data_criacao',
            'data_abertura',
            'data_cadastro',
        ]);

        $statusCol = $this->firstExistingColumn($table, ['status', 'situacao']);

        for ($i = 0; $i < 6; $i++) {
            $inicio = $inicioBase->modify("+{$i} months");
            $fim = $inicio->modify('last day of this month')->setTime(23, 59, 59);

            $labels[] = $this->labelMes($inicio);

            [$wherePeriodo, $paramsPeriodo] = $this->wherePeriodo($dateCol, $inicio, $fim, 'g' . $i);

            $totalMes = $this->contar($table, $wherePeriodo, $paramsPeriodo);

            [$whereConcluidos, $paramsConcluidos] = $this->whereStatusIn($statusCol, $this->statusConcluidos, 'gc' . $i);
            $concluidosMes = $this->contar(
                $table,
                array_merge($wherePeriodo, $whereConcluidos),
                array_merge($paramsPeriodo, $paramsConcluidos)
            );

            [$wherePendentes, $paramsPendentes] = $this->whereStatusIn($statusCol, $this->statusPendentes, 'gp' . $i);
            $pendentesMes = $this->contar(
                $table,
                array_merge($wherePeriodo, $wherePendentes),
                array_merge($paramsPeriodo, $paramsPendentes)
            );

            if ($pendentesMes === 0 && $totalMes > 0) {
                $pendentesMes = max(0, $totalMes - $concluidosMes);
            }

            $abertos[] = $totalMes;
            $concluidos[] = $concluidosMes;
            $pendentes[] = $pendentesMes;
        }

        return compact('labels', 'abertos', 'concluidos', 'pendentes');
    }

    private function graficoStatusProtocolos(array $protocolos): array
    {
        $concluidos = (int)$protocolos['concluidos'];
        $pendentes = (int)$protocolos['pendentes'];
        $total = (int)$protocolos['total'];

        $emAnalise = max(0, $total - $concluidos - $pendentes);

        return [
            'labels' => ['Concluídos', 'Em análise', 'Pendentes'],
            'valores' => [$concluidos, $emAnalise, $pendentes],
        ];
    }

    private function montarResumoAreas(array $protocolos, array $orcamentos, array $usuarios): array
    {
        $eficienciaRecepcao = $this->percentual($protocolos['concluidos'], $protocolos['total']);
        $eficienciaAdministrativo = $this->percentual($orcamentos['finalizados'], $orcamentos['total']);
        $eficienciaGestao = $this->percentual($usuarios['ativos'], $usuarios['total']);

        return [
            [
                'area' => 'Recepção',
                'descricao' => 'Entrada e triagem',
                'volume' => $protocolos['total'] . ' protocolos',
                'concluidos' => $protocolos['concluidos'] . ' encaminhados/concluídos',
                'pendencias' => (string)$protocolos['pendentes'],
                'eficiencia' => $eficienciaRecepcao,
                'status' => $this->statusPorEficiencia($eficienciaRecepcao),
                'leitura' => $this->textoPorEficiencia($eficienciaRecepcao),
            ],
            [
                'area' => 'Administrativo',
                'descricao' => 'Análise e orçamento',
                'volume' => $orcamentos['total'] . ' orçamentos',
                'concluidos' => $orcamentos['finalizados'] . ' finalizados',
                'pendencias' => (string)$orcamentos['pendentes'],
                'eficiencia' => $eficienciaAdministrativo,
                'status' => $this->statusPorEficiencia($eficienciaAdministrativo),
                'leitura' => $this->textoPorEficiencia($eficienciaAdministrativo),
            ],
            [
                'area' => 'Gestão',
                'descricao' => 'Usuários e permissões',
                'volume' => $usuarios['total'] . ' usuários',
                'concluidos' => $usuarios['ativos'] . ' ativos',
                'pendencias' => $usuarios['inativos'] . ' revisões',
                'eficiencia' => $eficienciaGestao,
                'status' => $this->statusPorEficiencia($eficienciaGestao),
                'leitura' => $usuarios['inativos'] > 0 ? 'Acompanhar acessos' : 'Equipe regular',
            ],
        ];
    }

    private function montarRanking(array $areas): array
    {
        usort($areas, function (array $a, array $b): int {
            return $b['eficiencia'] <=> $a['eficiencia'];
        });

        return $areas;
    }

    private function montarAlertas(array $protocolos, array $orcamentos, array $usuarios): array
    {
        $alertas = [];

        if ($protocolos['atrasados'] > 0) {
            $alertas[] = [
                'tipo' => 'danger',
                'titulo' => $protocolos['atrasados'] . ' protocolos atrasados',
                'descricao' => 'Existem protocolos sem movimentação há mais de 7 dias. Revise os gargalos operacionais.'
            ];
        }

        if ($protocolos['pendentes'] > 0) {
            $alertas[] = [
                'tipo' => 'warning',
                'titulo' => $protocolos['pendentes'] . ' pendências abertas',
                'descricao' => 'Acompanhe os protocolos pendentes para evitar acúmulo e atraso no atendimento.'
            ];
        }

        if ($usuarios['inativos'] > 0) {
            $alertas[] = [
                'tipo' => 'warning',
                'titulo' => $usuarios['inativos'] . ' usuários precisam de revisão',
                'descricao' => 'Verifique usuários inativos, permissões antigas ou acessos que não deveriam continuar ativos.'
            ];
        }

        if ($orcamentos['valor_total'] > 0) {
            $alertas[] = [
                'tipo' => 'success',
                'titulo' => 'R$ ' . number_format($orcamentos['valor_total'], 2, ',', '.') . ' em orçamentos',
                'descricao' => 'O período possui movimentação financeira registrada em orçamentos finalizados.'
            ];
        }

        if (empty($alertas)) {
            $alertas[] = [
                'tipo' => 'info',
                'titulo' => 'Operação sem alertas críticos',
                'descricao' => 'Não foram encontrados gargalos relevantes para o período selecionado.'
            ];
        }

        return $alertas;
    }

    private function buscarUltimosMovimentos(): array
    {
        $table = $this->firstExistingTable(['protocolos', 'protocolo']);

        if (!$table) {
            return [];
        }

        $dateCol = $this->firstExistingColumn($table, [
            'atualizado_em',
            'updated_at',
            'criado_em',
            'created_at',
            'data_criacao',
            'data_abertura',
        ]);

        if (!$dateCol) {
            return [];
        }

        $statusCol = $this->firstExistingColumn($table, ['status', 'situacao']);
        $tituloCol = $this->firstExistingColumn($table, [
            'numero',
            'codigo',
            'protocolo',
            'titulo',
            'cliente_nome',
            'nome',
        ]);

        $selectTitulo = $tituloCol ? $this->esc($tituloCol) : 'id';
        $selectStatus = $statusCol ? $this->esc($statusCol) : "''";

        $sql = "
            SELECT 
                {$selectTitulo} AS titulo,
                {$selectStatus} AS status,
                {$this->esc($dateCol)} AS data_movimento
            FROM {$this->esc($table)}
            ORDER BY {$this->esc($dateCol)} DESC
            LIMIT 3
        ";

        try {
            $stmt = $this->pdo->query($sql);
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            $movimentos = [];

            foreach ($rows as $row) {
                $status = trim((string)($row['status'] ?? ''));
                $titulo = trim((string)($row['titulo'] ?? ''));

                $movimentos[] = [
                    'titulo' => $titulo !== '' ? 'Protocolo ' . $titulo : 'Movimento de protocolo',
                    'descricao' => $status !== '' ? 'Status atual: ' . $status : 'Movimento registrado no sistema.',
                    'data' => $row['data_movimento'] ?? null,
                ];
            }

            return $movimentos;
        } catch (Throwable $e) {
            return [];
        }
    }

    private function contarAtrasados(string $table, ?string $dateCol, ?string $statusCol): int
    {
        if (!$dateCol) {
            return 0;
        }

        $limite = (new DateTimeImmutable('now'))->modify('-7 days')->setTime(23, 59, 59);

        $where = [
            $this->esc($dateCol) . ' <= :limite_atraso'
        ];

        $params = [
            ':limite_atraso' => $limite->format('Y-m-d H:i:s')
        ];

        [$whereNaoConcluido, $paramsNaoConcluido] = $this->whereStatusNotIn($statusCol, $this->statusConcluidos, 'na');

        $where = array_merge($where, $whereNaoConcluido);
        $params = array_merge($params, $paramsNaoConcluido);

        return $this->contar($table, $where, $params);
    }

    private function periodoAnterior(array $periodo): array
    {
        /** @var DateTimeImmutable $inicio */
        $inicio = $periodo['inicio'];

        /** @var DateTimeImmutable $fim */
        $fim = $periodo['fim'];

        $dias = max(1, (int)$inicio->diff($fim)->days + 1);

        return [
            'inicio' => $inicio->modify("-{$dias} days"),
            'fim' => $inicio->modify('-1 second'),
        ];
    }

    private function calcularVariacao(int $atual, int $anterior): int
    {
        if ($anterior <= 0 && $atual > 0) {
            return 100;
        }

        if ($anterior <= 0) {
            return 0;
        }

        return (int)round((($atual - $anterior) / $anterior) * 100);
    }

    private function percentual(int|float $parte, int|float $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        return (int)round(($parte / $total) * 100);
    }

    private function statusPorEficiencia(int $eficiencia): string
    {
        if ($eficiencia >= 75) {
            return 'ok';
        }

        if ($eficiencia >= 50) {
            return 'progress';
        }

        return 'pending';
    }

    private function textoPorEficiencia(int $eficiencia): string
    {
        if ($eficiencia >= 75) {
            return 'Boa entrega';
        }

        if ($eficiencia >= 50) {
            return 'Operação estável';
        }

        return 'Acompanhar de perto';
    }

    private function labelMes(DateTimeImmutable $data): string
    {
        $meses = [
            '01' => 'Jan',
            '02' => 'Fev',
            '03' => 'Mar',
            '04' => 'Abr',
            '05' => 'Mai',
            '06' => 'Jun',
            '07' => 'Jul',
            '08' => 'Ago',
            '09' => 'Set',
            '10' => 'Out',
            '11' => 'Nov',
            '12' => 'Dez',
        ];

        return $meses[$data->format('m')] ?? $data->format('m/Y');
    }

    private function wherePeriodo(?string $dateCol, DateTimeImmutable $inicio, DateTimeImmutable $fim, string $prefix): array
    {
        if (!$dateCol) {
            return [[], []];
        }

        return [
            [
                $this->esc($dateCol) . " BETWEEN :{$prefix}_inicio AND :{$prefix}_fim"
            ],
            [
                ":{$prefix}_inicio" => $inicio->format('Y-m-d H:i:s'),
                ":{$prefix}_fim" => $fim->format('Y-m-d H:i:s'),
            ]
        ];
    }

    private function whereStatusIn(?string $statusCol, array $statuses, string $prefix): array
    {
        if (!$statusCol) {
            return [[], []];
        }

        $placeholders = [];
        $params = [];

        foreach ($statuses as $i => $status) {
            $key = ":{$prefix}_status_{$i}";
            $placeholders[] = $key;
            $params[$key] = $this->lower((string)$status);
        }

        return [
            [
                'LOWER(TRIM(' . $this->esc($statusCol) . ')) IN (' . implode(',', $placeholders) . ')'
            ],
            $params
        ];
    }

    private function whereStatusNotIn(?string $statusCol, array $statuses, string $prefix): array
    {
        if (!$statusCol) {
            return [[], []];
        }

        $placeholders = [];
        $params = [];

        foreach ($statuses as $i => $status) {
            $key = ":{$prefix}_status_{$i}";
            $placeholders[] = $key;
            $params[$key] = $this->lower((string)$status);
        }

        return [
            [
                'LOWER(TRIM(' . $this->esc($statusCol) . ')) NOT IN (' . implode(',', $placeholders) . ')'
            ],
            $params
        ];
    }

    private function contar(string $table, array $where = [], array $params = []): int
    {
        try {
            $sql = 'SELECT COUNT(*) FROM ' . $this->esc($table);

            if (!empty($where)) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function somar(string $table, string $column, array $where = [], array $params = []): float
    {
        try {
            $sql = 'SELECT COALESCE(SUM(' . $this->esc($column) . '), 0) FROM ' . $this->esc($table);

            if (!empty($where)) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (float)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0.0;
        }
    }

    private function firstExistingTable(array $tables): ?string
    {
        foreach ($tables as $table) {
            if ($this->tableExists($table)) {
                return $table;
            }
        }

        return null;
    }

    private function firstExistingColumn(string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            if ($this->columnExists($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableCache)) {
            return $this->tableCache[$table];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table
            ");

            $stmt->execute([':table' => $table]);

            $exists = (int)$stmt->fetchColumn() > 0;
            $this->tableCache[$table] = $exists;

            return $exists;
        } catch (Throwable $e) {
            $this->tableCache[$table] = false;
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;

        if (array_key_exists($key, $this->columnCache)) {
            return $this->columnCache[$key];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table
                AND COLUMN_NAME = :column
            ");

            $stmt->execute([
                ':table' => $table,
                ':column' => $column,
            ]);

            $exists = (int)$stmt->fetchColumn() > 0;
            $this->columnCache[$key] = $exists;

            return $exists;
        } catch (Throwable $e) {
            $this->columnCache[$key] = false;
            return false;
        }
    }

    private function esc(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function lower(string $value): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtolower($value);
    }
}