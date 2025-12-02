<?php
require '../conex.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$sql = "SELECT * FROM produtos ORDER BY id DESC";
$resultado = $conex->query($sql);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Lista de Produtos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Estilos Amado -->
    <link rel="stylesheet" href="../css/core-style.css">
    <link rel="stylesheet" href="../style.css">
</head>
<body>

<!-- Área de listagem -->
<div class="cart-table-area section-padding-100">
    <div class="container-fluid">
        <div class="cart-title text-center mb-5">
            <h2>Produtos Cadastrados</h2>
        </div>

        <div class="row">
            <?php if ($resultado->num_rows > 0): ?>
                <?php while ($produto = $resultado->fetch_assoc()): ?>
                    <div class="col-12 col-sm-6 col-lg-4 mb-5">
                        <div class="single-product-wrapper shadow-sm p-3" style="border: 1px solid #e1e1e1; border-radius: 10px;">
                            <!-- Imagem -->
                            <div class="product-img">
                                <img src="<?php echo $produto['imagem']; ?>" alt="Imagem do produto" style="height: 200px; width: 100%; object-fit: cover; border-radius: 8px;">
                            </div>

                            <!-- Detalhes -->
                            <div class="product-description text-center mt-3">
                                <h5><?php echo htmlspecialchars($produto['nome']); ?></h5>
                                <p class="mb-1"><strong>Preço:</strong> R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></p>
                                <p class="mb-1"><strong>Categoria:</strong> <?php echo htmlspecialchars($produto['categoria']); ?></p>
                                <p class="small text-muted"><?php echo nl2br(htmlspecialchars($produto['descricao'])); ?></p>

                                <!-- Ações futuras -->
                                <div class="mt-3 d-flex justify-content-center gap-2">
                                    <a href="editar-produto.php?id=<?php echo $produto['id']; ?>" class="btn btn-sm btn-outline-secondary">Editar</a>
                                    <a href="excluir-produto.php?id=<?php echo $produto['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Tem certeza que deseja excluir?')">Excluir</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center">
                    <p class="text-muted">Nenhum produto cadastrado.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="text-center mt-5">
            <a href="cadprod.php" class="btn amado-btn">← Voltar para cadastro</a>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="../js/jquery/jquery-2.2.4.min.js"></script>
<script src="../js/popper.min.js"></script>
<script src="../js/bootstrap.min.js"></script>
<script src="../js/plugins.js"></script>
<script src="../js/active.js"></script>

</body>
</html>
