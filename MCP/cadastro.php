<?php
require_once __DIR__ . '/layout.php';
require_login();

$editing = isset($_GET['id']) && (int) $_GET['id'] > 0;

render_layout_start(
    'cadastro',
    $editing ? 'Editar Processo' : 'Cadastro de Processo',
    'Registro dos dados principais para acompanhamento juridico pessoal.',
    'Preencha cliente, numero, tipo, situacao e prazo. A observacao pode guardar qualquer detalhe importante do processo.'
);
?>

<section class="form-layout" data-page="process-form" data-process-id="<?= (int) ($_GET['id'] ?? 0) ?>">
    <form id="process-form" class="data-panel form-panel">
        <input type="hidden" id="processo-id" value="<?= (int) ($_GET['id'] ?? 0) ?>">

        <div class="form-grid">
            <label class="form-field">
                <span>Nome do cliente</span>
                <input type="text" id="cliente" required maxlength="180" placeholder="Ex: Secretaria Municipal de Saude">
            </label>

            <label class="form-field">
                <span>Numero do processo</span>
                <input type="text" id="numero_processo" required maxlength="80" placeholder="Ex: 000123/2026">
            </label>

            <label class="form-field">
                <span>Data do prazo</span>
                <input type="date" id="data_prazo" required>
            </label>

            <label class="form-field">
                <span>Tipo de processo</span>
                <select id="tipo_processo" required>
                    <option value="">Selecione...</option>
                </select>
                <input type="text" id="tipo_processo_personalizado" class="custom-field-input" maxlength="140" placeholder="Digite o novo tipo de processo" hidden>
            </label>

            <label class="form-field">
                <span>Situacao</span>
                <select id="situacao" required>
                    <option value="">Selecione...</option>
                </select>
                <input type="text" id="situacao_personalizada" class="custom-field-input" maxlength="140" placeholder="Digite a nova situacao" hidden>
            </label>

            <label class="form-field full">
                <span>Observacao</span>
                <textarea id="observacao" rows="7" maxlength="3000" placeholder="Registre detalhes, pendencias, encaminhamentos ou historico relevante."></textarea>
            </label>
        </div>

        <div class="form-footer">
            <a class="btn ghost" href="index.php">
                <i class="fa-solid fa-arrow-left"></i>
                Voltar
            </a>
            <button class="btn primary" type="submit" id="process-submit">
                <i class="fa-solid fa-floppy-disk"></i>
                Salvar processo
            </button>
        </div>
    </form>

    <aside class="side-panel">
        <h2>Resumo</h2>
        <dl>
            <div>
                <dt>Cliente</dt>
                <dd id="preview-cliente">-</dd>
            </div>
            <div>
                <dt>Numero</dt>
                <dd id="preview-numero">-</dd>
            </div>
            <div>
                <dt>Prazo</dt>
                <dd id="preview-prazo">-</dd>
            </div>
            <div>
                <dt>Tipo</dt>
                <dd id="preview-tipo">-</dd>
            </div>
            <div>
                <dt>Situacao</dt>
                <dd id="preview-situacao">-</dd>
            </div>
        </dl>
    </aside>
</section>

<?php render_layout_end(); ?>
