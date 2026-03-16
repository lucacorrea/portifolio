<?php include 'conexao.php'; ?>
<?php
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM membros WHERE id = ?");
$stmt->execute([$id]);
$m = $stmt->fetch();

if (!$m) {
    die('Membro não encontrado.');
}
?>
<?php include 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Visualizar membro</h3>
        <small class="text-muted">Informações completas do cadastro</small>
    </div>
    <div class="d-flex gap-2">
        <a href="editar.php?id=<?= $m['id'] ?>" class="btn btn-warning">Editar</a>
        <a href="ficha.php?id=<?= $m['id'] ?>" class="btn btn-outline-secondary">Ver ficha</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center">
                <?php if (!empty($m['foto'])): ?>
                    <img src="uploads/<?= htmlspecialchars($m['foto']) ?>" class="foto-grande mb-3" alt="foto">
                <?php else: ?>
                    <img src="https://via.placeholder.com/180x220?text=Sem+Foto" class="foto-grande mb-3" alt="foto">
                <?php endif; ?>
                <h5 class="mb-1"><?= htmlspecialchars($m['nome_completo']) ?></h5>
                <div class="text-muted"><?= htmlspecialchars($m['congregacao']) ?></div>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white"><strong>Dados pessoais</strong></div>
            <div class="card-body grid-view">
                <div><span>Sexo</span><strong><?= htmlspecialchars($m['sexo']) ?></strong></div>
                <div><span>Nascimento</span><strong><?= $m['data_nascimento'] ? date('d/m/Y', strtotime($m['data_nascimento'])) : '' ?></strong></div>
                <div><span>Nacionalidade</span><strong><?= htmlspecialchars($m['nacionalidade']) ?></strong></div>
                <div><span>Naturalidade</span><strong><?= htmlspecialchars($m['naturalidade']) ?></strong></div>
                <div><span>UF</span><strong><?= htmlspecialchars($m['estado_uf']) ?></strong></div>
                <div><span>Escolaridade</span><strong><?= htmlspecialchars($m['escolaridade']) ?></strong></div>
                <div><span>Profissão</span><strong><?= htmlspecialchars($m['profissao']) ?></strong></div>
                <div><span>CPF</span><strong><?= htmlspecialchars($m['cpf']) ?></strong></div>
                <div><span>Identidade</span><strong><?= htmlspecialchars($m['identidade']) ?></strong></div>
                <div><span>Pai</span><strong><?= htmlspecialchars($m['pai']) ?></strong></div>
                <div><span>Mãe</span><strong><?= htmlspecialchars($m['mae']) ?></strong></div>
                <div><span>Estado civil</span><strong><?= htmlspecialchars($m['estado_civil']) ?></strong></div>
                <div><span>Cônjuge</span><strong><?= htmlspecialchars($m['conjuge']) ?></strong></div>
                <div><span>Filhos</span><strong><?= htmlspecialchars($m['filhos']) ?></strong></div>
                <div><span>Telefone</span><strong><?= htmlspecialchars($m['telefone']) ?></strong></div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white"><strong>Endereço</strong></div>
            <div class="card-body grid-view">
                <div><span>Rua</span><strong><?= htmlspecialchars($m['rua']) ?></strong></div>
                <div><span>Número</span><strong><?= htmlspecialchars($m['numero']) ?></strong></div>
                <div><span>Bairro</span><strong><?= htmlspecialchars($m['bairro']) ?></strong></div>
                <div><span>CEP</span><strong><?= htmlspecialchars($m['cep']) ?></strong></div>
                <div><span>Cidade</span><strong><?= htmlspecialchars($m['cidade']) ?></strong></div>
                <div><span>Estado</span><strong><?= htmlspecialchars($m['estado']) ?></strong></div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white"><strong>Dados eclesiásticos</strong></div>
            <div class="card-body grid-view">
                <div><span>Tipo de ingresso</span><strong><?= htmlspecialchars($m['tipo_ingresso']) ?></strong></div>
                <div><span>Data decisão</span><strong><?= $m['data_decisao'] ? date('d/m/Y', strtotime($m['data_decisao'])) : '' ?></strong></div>
                <div><span>Procedência</span><strong><?= htmlspecialchars($m['procedencia']) ?></strong></div>
                <div><span>Congregação</span><strong><?= htmlspecialchars($m['congregacao']) ?></strong></div>
                <div><span>Área</span><strong><?= htmlspecialchars($m['area']) ?></strong></div>
                <div><span>Núcleo</span><strong><?= htmlspecialchars($m['nucleo']) ?></strong></div>
                <div class="full"><span>Observação</span><strong><?= nl2br(htmlspecialchars($m['observacao'])) ?></strong></div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
