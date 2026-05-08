<?php

function clientes_contratos_lista(): array
{
    return [
        [
            'nome' => 'Carlos Henrique',
            'telefone' => '(92) 99999-1020',
            'documento' => '123.456.789-00',
            'email' => 'carlos@email.com',
            'ultimo_atendimento' => '22/04/2026',
            'ultimo_protocolo' => 'PRT-2026-0501',
            'ultimo_servico' => 'Solicitação de orçamento',
            'status' => 'Ativo',
            'contratos' => [
                [
                    'numero' => 'CTR-2026-014',
                    'titulo' => 'Manejo e licenciamento ambiental',
                    'vigencia' => '01/05/2026 - 30/04/2027',
                    'valor' => 18500.00,
                    'status' => 'Ativo',
                    'responsavel' => 'Administrativo',
                ],
                [
                    'numero' => 'CTR-2026-021',
                    'titulo' => 'Regularização documental',
                    'vigencia' => '10/05/2026 - 10/08/2026',
                    'valor' => 5800.00,
                    'status' => 'Em assinatura',
                    'responsavel' => 'Recepção',
                ],
            ],
        ],
        [
            'nome' => 'Fernanda Martins',
            'telefone' => '(92) 99123-4088',
            'documento' => '987.654.321-00',
            'email' => 'fernanda@email.com',
            'ultimo_atendimento' => '20/04/2026',
            'ultimo_protocolo' => 'PRT-2026-0502',
            'ultimo_servico' => 'Atendimento prioritário',
            'status' => 'Prioritário',
            'contratos' => [
                [
                    'numero' => 'CTR-2026-009',
                    'titulo' => 'Atendimento ambiental prioritário',
                    'vigencia' => '01/03/2026 - 28/02/2027',
                    'valor' => 22000.00,
                    'status' => 'Ativo',
                    'responsavel' => 'Administrativo',
                ],
            ],
        ],
        [
            'nome' => 'Ana Beatriz Costa',
            'telefone' => '(92) 98888-2451',
            'documento' => '741.852.963-00',
            'email' => 'ana.costa@email.com',
            'ultimo_atendimento' => '22/04/2026',
            'ultimo_protocolo' => 'PRT-2026-0503',
            'ultimo_servico' => 'Análise documental',
            'status' => 'Pendente',
            'contratos' => [
                [
                    'numero' => 'CTR-2026-017',
                    'titulo' => 'Análise documental rural',
                    'vigencia' => '15/04/2026 - 15/07/2026',
                    'valor' => 7200.00,
                    'status' => 'Pendente',
                    'responsavel' => 'Recepção',
                ],
            ],
        ],
        [
            'nome' => 'João Pedro Silva',
            'telefone' => '(92) 99777-8874',
            'documento' => '369.258.147-00',
            'email' => 'joao.silva@email.com',
            'ultimo_atendimento' => '21/04/2026',
            'ultimo_protocolo' => 'PRT-2026-0504',
            'ultimo_servico' => 'Cadastro de serviço',
            'status' => 'Ativo',
            'contratos' => [
                [
                    'numero' => 'CTR-2026-018',
                    'titulo' => 'Cadastro e acompanhamento de serviço',
                    'vigencia' => '21/04/2026 - 21/10/2026',
                    'valor' => 9400.00,
                    'status' => 'Ativo',
                    'responsavel' => 'Administrativo',
                ],
            ],
        ],
        [
            'nome' => 'Raimundo Lopes',
            'telefone' => '(92) 99456-7721',
            'documento' => '852.456.951-00',
            'email' => 'raimundo@email.com',
            'ultimo_atendimento' => '18/04/2026',
            'ultimo_protocolo' => 'PRT-2026-0505',
            'ultimo_servico' => 'Revisão de solicitação',
            'status' => 'Em análise',
            'contratos' => [
                [
                    'numero' => 'CTR-2026-020',
                    'titulo' => 'Revisão técnica e documental',
                    'vigencia' => '25/04/2026 - 25/09/2026',
                    'valor' => 11200.00,
                    'status' => 'Em análise',
                    'responsavel' => 'Dono',
                ],
            ],
        ],
    ];
}

function cliente_status_classe(string $status): string
{
    return match ($status) {
        'Ativo' => 'ok',
        'Pendente' => 'pending',
        'Prioritário' => 'high',
        default => 'progress',
    };
}

function contrato_status_classe(string $status): string
{
    return match ($status) {
        'Ativo' => 'ok',
        'Pendente', 'Em assinatura' => 'pending',
        'Vencido', 'Cancelado' => 'high',
        default => 'progress',
    };
}

function contrato_valor_formatado(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function clientes_contratos_indicadores(array $clientes): array
{
    $indicadores = [
        'clientes' => count($clientes),
        'contratos' => 0,
        'contratos_ativos' => 0,
        'contratos_pendentes' => 0,
        'valor_total' => 0.0,
    ];

    foreach ($clientes as $cliente) {
        foreach (($cliente['contratos'] ?? []) as $contrato) {
            $indicadores['contratos']++;
            $indicadores['valor_total'] += (float) ($contrato['valor'] ?? 0);

            if (($contrato['status'] ?? '') === 'Ativo') {
                $indicadores['contratos_ativos']++;
            }

            if (in_array($contrato['status'] ?? '', ['Pendente', 'Em assinatura', 'Em análise'], true)) {
                $indicadores['contratos_pendentes']++;
            }
        }
    }

    return $indicadores;
}
