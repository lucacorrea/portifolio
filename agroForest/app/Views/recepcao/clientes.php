<?php
$paginaAtual = 'clientes';
$paginaTitulo = 'Clientes';
$paginaDescricao = 'Consulte os clientes cadastrados e acompanhe o histórico básico de atendimento.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Novo Protocolo';
$linkBotaoAcao = route_url('recepcao', 'novoProtocolo');
$tituloPagina = 'Recepção - Clientes';
$cssPagina = 'assets/css/recepcao/clientes.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="table-card">
            <div class="section-header">
                <h2>Clientes cadastrados</h2>
                <p>Lista de clientes atendidos pela recepção.</p>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Telefone</th>
                        <th>Documento</th>
                        <th>Último atendimento</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Carlos Henrique</td>
                        <td>(92) 99999-1020</td>
                        <td>123.456.789-00</td>
                        <td>22/04/2026</td>
                        <td>Ativo</td>
                    </tr>
                    <tr>
                        <td>Ana Beatriz Costa</td>
                        <td>(92) 98888-2451</td>
                        <td>987.654.321-00</td>
                        <td>22/04/2026</td>
                        <td>Ativo</td>
                    </tr>
                    <tr>
                        <td>João Pedro Silva</td>
                        <td>(92) 99777-8874</td>
                        <td>741.852.963-00</td>
                        <td>21/04/2026</td>
                        <td>Pendente</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>