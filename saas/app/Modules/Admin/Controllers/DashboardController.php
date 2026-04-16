<?php
declare(strict_types=1);

final class DashboardController
{
    public function index(): void
    {
        if (
            empty($_SESSION['auth']) ||
            empty($_SESSION['auth']['guard']) ||
            $_SESSION['auth']['guard'] !== 'admin'
        ) {
            flash_set('error', 'Faça login para acessar o painel.');
            redirect('/admin/login');
        }

        $admin = $_SESSION['auth'];

        $metricas = [
            [
                'titulo' => 'Contadores ativos',
                'valor' => '128',
                'detalhe' => '+12 este mês',
                'tipo' => 'success',
            ],
            [
                'titulo' => 'Assinaturas ativas',
                'valor' => '342',
                'detalhe' => '+18 novas',
                'tipo' => 'success',
            ],
            [
                'titulo' => 'Faturas pendentes',
                'valor' => '19',
                'detalhe' => 'Acompanhar cobrança',
                'tipo' => 'warning',
            ],
            [
                'titulo' => 'Receita mensal',
                'valor' => 'R$ 48.750,00',
                'detalhe' => '+8,4% vs mês anterior',
                'tipo' => 'success',
            ],
        ];

        $ultimosContadores = [
            [
                'nome' => 'Monte Castelo Contabilidade',
                'plano' => 'Premium',
                'status' => 'Ativo',
                'data' => '14/04/2026',
            ],
            [
                'nome' => 'Alpha Assessoria Contábil',
                'plano' => 'Profissional',
                'status' => 'Ativo',
                'data' => '13/04/2026',
            ],
            [
                'nome' => 'Delta Consultoria Empresarial',
                'plano' => 'Essencial',
                'status' => 'Teste',
                'data' => '12/04/2026',
            ],
            [
                'nome' => 'Nova Linha Contábil',
                'plano' => 'Premium',
                'status' => 'Bloqueado',
                'data' => '11/04/2026',
            ],
        ];

        $alertas = [
            [
                'titulo' => '3 faturas vencem hoje',
                'descricao' => 'Assinaturas precisam de acompanhamento no financeiro.',
            ],
            [
                'titulo' => '2 tickets em aberto sem resposta',
                'descricao' => 'Equipe de suporte precisa retornar os clientes.',
            ],
            [
                'titulo' => 'Plano Premium em destaque',
                'descricao' => 'Maior adesão nos últimos 7 dias.',
            ],
        ];

        View::render('Admin/Views/dashboard', [
            'admin' => $admin,
            'metricas' => $metricas,
            'ultimosContadores' => $ultimosContadores,
            'alertas' => $alertas,
        ]);
    }
}
