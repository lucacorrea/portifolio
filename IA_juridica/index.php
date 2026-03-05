<?php
require_once __DIR__ . '/layout.php';
getHeader("Novo Documento - IA Jurídica");

$hoje = date('Y-m-d');
?>

<h1>Gerar Novo Documento</h1>
<p style="color: var(--text-secondary); margin-bottom: 2rem;">Preencha os campos abaixo para que a IA gere o texto jurídico formal.</p>

<form action="gerar_documento_action.php" method="POST" class="card">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <div class="form-group">
            <label class="form-label">Tipo de Documento</label>
            <select name="tipo_documento" class="form-control" required>
                <option value="">Selecione...</option>
                <option value="Oficio">Ofício</option>
                <option value="Memorando">Memorando</option>
                <option value="Parecer">Parecer</option>
                <option value="Relatorio">Relatório Administrativo</option>
                <option value="Despacho">Despacho</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Data do Documento</label>
            <input type="date" name="data" class="form-control" value="<?php echo $hoje; ?>" required>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Destinatário (Nome e Cargo)</label>
        <input type="text" name="destinatario" class="form-control" placeholder="Ex: Sr. João da Silva, Secretário de Obras" required>
    </div>

    <div class="form-group">
        <label class="form-label">Assunto</label>
        <input type="text" name="assunto" class="form-control" placeholder="Breve resumo do assunto" required>
    </div>

    <div class="form-group">
        <label class="form-label">Conteúdo ou Solicitação (Promp para a IA)</label>
        <textarea name="solicitacao" class="form-control" placeholder="Descreva os detalhes que devem constar no documento. A IA cuidará da formalidade." required></textarea>
    </div>

    <hr style="margin: 2rem 0; border: 0; border-top: 1px solid #eee;">

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <div class="form-group">
            <label class="form-label">Responsável pela Assinatura</label>
            <input type="text" name="responsavel" class="form-control" placeholder="Seu nome" required>
        </div>

        <div class="form-group">
            <label class="form-label">Cargo do Responsável</label>
            <input type="text" name="cargo" class="form-control" placeholder="Seu cargo" required>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Cidade</label>
        <input type="text" name="cidade" class="form-control" value="Brasília" required>
    </div>

    <div style="margin-top: 2rem; display: flex; gap: 1rem;">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-magic"></i> Gerar Documento com IA
        </button>
        <button type="reset" class="btn" style="background: #eee; color: #333;">Limpar</button>
    </div>
</form>

<?php getFooter(); ?>
