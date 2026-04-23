<?php
$paginaAtual = 'relatorios';
$paginaTitulo = 'Relatórios';
$paginaDescricao = 'Indicadores operacionais da recepção e volume de atendimento.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Exportar Relatório';
$linkBotaoAcao = '#';
$tituloPagina = 'Recepção - Relatórios';
$cssPagina = 'assets/css/recepcao/relatorios.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="stats-grid">
            <article class="stat-card">
                <h3>128</h3>
                <p>Atendimentos no mês</p>
            </article>
            <article class="stat-card">
                <h3>89</h3>
                <p>Protocolos concluídos</p>
            </article>
            <article class="stat-card">
                <h3>17</h3>
                <p>Pendências registradas</p>
            </article>
            <article class="stat-card">
                <h3>11 min</h3>
                <p>Tempo médio por atendimento</p>
            </article>
        </section>

        <section class="table-card">
            <h2>Resumo mensal</h2>
            <table>
                <thead>
                    <tr>
                        <th>Período</th>
                        <th>Atendimentos</th>
                        <th>Encaminhados</th>
                        <th>Pendências</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Abril/2026</td>
                        <td>128</td>
                        <td>89</td>
                        <td>17</td>
                    </tr>
                    <tr>
                        <td>Março/2026</td>
                        <td>114</td>
                        <td>77</td>
                        <td>14</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>