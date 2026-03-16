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
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Ficha do Membro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background:#f5f5f5; }
.ficha { max-width: 1000px; margin: 20px auto; background:#fff; padding:30px; border:1px solid #ddd; }
.box { border:1px solid #bbb; padding:10px; min-height:58px; }
.tt { font-size:12px; color:#666; display:block; margin-bottom:6px; }
@media print {
    .no-print { display:none !important; }
    body { background:#fff; }
    .ficha { margin:0; border:none; max-width:100%; }
}
</style>
</head>
<body>
<div class="ficha">
    <div class="d-flex justify-content-between align-items-center no-print mb-3">
        <a href="visualizar.php?id=<?= $m['id'] ?>" class="btn btn-outline-dark btn-sm">Voltar</a>
        <button onclick="window.print()" class="btn btn-primary btn-sm">Imprimir</button>
    </div>

    <div class="text-center mb-4">
        <h2 class="mb-1">INTEGRAÇÃO À MEMBRESIA</h2>
        <div class="text-muted">Ficha simples de membro</div>
    </div>

    <div class="row g-3">
        <div class="col-md-3">
            <div class="box text-center">
                <span class="tt">Foto 3x4</span>
                <?php if (!empty($m['foto'])): ?>
                    <img src="uploads/<?= htmlspecialchars($m['foto']) ?>" style="width:110px;height:140px;object-fit:cover;">
                <?php else: ?>
                    <div style="height:140px;display:flex;align-items:center;justify-content:center;">Sem foto</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-9">
            <div class="row g-3">
                <div class="col-md-8"><div class="box"><span class="tt">Nome completo</span><?= htmlspecialchars($m['nome_completo']) ?></div></div>
                <div class="col-md-2"><div class="box"><span class="tt">Sexo</span><?= htmlspecialchars($m['sexo']) ?></div></div>
                <div class="col-md-2"><div class="box"><span class="tt">Nascimento</span><?= $m['data_nascimento'] ? date('d/m/Y', strtotime($m['data_nascimento'])) : '' ?></div></div>

                <div class="col-md-4"><div class="box"><span class="tt">Nacionalidade</span><?= htmlspecialchars($m['nacionalidade']) ?></div></div>
                <div class="col-md-4"><div class="box"><span class="tt">Naturalidade</span><?= htmlspecialchars($m['naturalidade']) ?></div></div>
                <div class="col-md-4"><div class="box"><span class="tt">Profissão</span><?= htmlspecialchars($m['profissao']) ?></div></div>
            </div>
        </div>

        <div class="col-md-4"><div class="box"><span class="tt">CPF</span><?= htmlspecialchars($m['cpf']) ?></div></div>
        <div class="col-md-4"><div class="box"><span class="tt">Identidade</span><?= htmlspecialchars($m['identidade']) ?></div></div>
        <div class="col-md-4"><div class="box"><span class="tt">Telefone</span><?= htmlspecialchars($m['telefone']) ?></div></div>

        <div class="col-md-6"><div class="box"><span class="tt">Pai</span><?= htmlspecialchars($m['pai']) ?></div></div>
        <div class="col-md-6"><div class="box"><span class="tt">Mãe</span><?= htmlspecialchars($m['mae']) ?></div></div>

        <div class="col-md-4"><div class="box"><span class="tt">Estado civil</span><?= htmlspecialchars($m['estado_civil']) ?></div></div>
        <div class="col-md-4"><div class="box"><span class="tt">Cônjuge</span><?= htmlspecialchars($m['conjuge']) ?></div></div>
        <div class="col-md-4"><div class="box"><span class="tt">Filhos</span><?= htmlspecialchars($m['filhos']) ?></div></div>

        <div class="col-md-6"><div class="box"><span class="tt">Rua</span><?= htmlspecialchars($m['rua']) ?></div></div>
        <div class="col-md-2"><div class="box"><span class="tt">Número</span><?= htmlspecialchars($m['numero']) ?></div></div>
        <div class="col-md-4"><div class="box"><span class="tt">Bairro</span><?= htmlspecialchars($m['bairro']) ?></div></div>

        <div class="col-md-4"><div class="box"><span class="tt">CEP</span><?= htmlspecialchars($m['cep']) ?></div></div>
        <div class="col-md-4"><div class="box"><span class="tt">Cidade</span><?= htmlspecialchars($m['cidade']) ?></div></div>
        <div class="col-md-4"><div class="box"><span class="tt">Estado</span><?= htmlspecialchars($m['estado']) ?></div></div>

        <div class="col-md-3"><div class="box"><span class="tt">Tipo de ingresso</span><?= htmlspecialchars($m['tipo_ingresso']) ?></div></div>
        <div class="col-md-3"><div class="box"><span class="tt">Data decisão</span><?= $m['data_decisao'] ? date('d/m/Y', strtotime($m['data_decisao'])) : '' ?></div></div>
        <div class="col-md-6"><div class="box"><span class="tt">Procedência</span><?= htmlspecialchars($m['procedencia']) ?></div></div>

        <div class="col-md-4"><div class="box"><span class="tt">Congregação</span><?= htmlspecialchars($m['congregacao']) ?></div></div>
        <div class="col-md-4"><div class="box"><span class="tt">Área</span><?= htmlspecialchars($m['area']) ?></div></div>
        <div class="col-md-4"><div class="box"><span class="tt">Núcleo</span><?= htmlspecialchars($m['nucleo']) ?></div></div>

        <div class="col-md-12"><div class="box"><span class="tt">Observação</span><?= nl2br(htmlspecialchars($m['observacao'])) ?></div></div>
    </div>
</div>
</body>
</html>
