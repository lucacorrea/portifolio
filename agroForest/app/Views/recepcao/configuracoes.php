<?php
$paginaAtual = 'configuracoes';
$paginaTitulo = 'Configurações';
$paginaDescricao = 'Ajustes básicos do setor de recepção e preferências operacionais.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Salvar Configurações';
$linkBotaoAcao = '#';
$tituloPagina = 'Recepção - Configurações';
$cssPagina = 'assets/css/recepcao/configuracoes.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="form-card">
            <div class="section-header">
                <h2>Configurações da Recepção</h2>
                <p>Defina preferências visuais e operacionais da área.</p>
            </div>

            <form class="form-grid" action="" method="POST">
                <div class="form-group">
                    <label for="nome_setor">Nome do Setor</label>
                    <input type="text" id="nome_setor" name="nome_setor" value="Recepção">
                </div>

                <div class="form-group">
                    <label for="responsavel">Responsável</label>
                    <input type="text" id="responsavel" name="responsavel" value="Maria Souza">
                </div>

                <div class="form-group">
                    <label for="notificacao">Receber notificações</label>
                    <select id="notificacao" name="notificacao">
                        <option value="sim">Sim</option>
                        <option value="nao">Não</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="prioridade_padrao">Prioridade padrão</label>
                    <select id="prioridade_padrao" name="prioridade_padrao">
                        <option value="normal">Normal</option>
                        <option value="media">Média</option>
                        <option value="alta">Alta</option>
                    </select>
                </div>

                <div class="form-actions col-2">
                    <button type="submit" class="btn-primary">Salvar Alterações</button>
                </div>
            </form>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>