<?php
require './conex.php';
session_start();

/* PEGAR ID */
$id = $_GET['id'] ?? 0;
$id = (int)$id;

$sql = "SELECT * FROM produtos WHERE id = $id LIMIT 1";
$res = $conex->query($sql);

if (!$res || $res->num_rows == 0) {
    die("Produto não encontrado.");
}

$p = $res->fetch_assoc();

/* Estoque (se não existir coluna, fica 0) */
$estoque = isset($p['estoque']) ? (int)$p['estoque'] : 0;
$sem_estoque = $estoque <= 0;

/* Função imagem */
function imgPath($img)
{
    if (!$img) return "img/core-img/no-image.png";
    $img = basename(trim($img));
    return file_exists("uploads/" . $img) ? "uploads/" . $img : "img/core-img/no-image.png";
}

/* VERIFICAR SE COMPRA FOI FEITA */
$compra_ok = isset($_GET['sucesso']) && $_GET['sucesso'] == 1;
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($p['nome']) ?></title>

    <link rel="stylesheet" href="css/core-style.css">
    <link rel="stylesheet" href="style.css">

    <style>
        .msg-sucesso {
            background: #28a745;
            color: #fff;
            padding: 18px;
            border-radius: 10px;
            font-size: 20px;
            text-align: center;
            margin-bottom: 25px;
        }

        .estoque-info {
            font-size: 14px;
            margin-top: 8px;
        }

        .estoque-info.ok {
            color: #28a745;
        }

        .estoque-info.sem {
            color: #dc3545;
            font-weight: 600;
        }

        .btn-comprar {
            width: 100%;
            padding: 15px 0;
            font-size: 22px;
            font-weight: 600;
            text-transform: uppercase;
            background: linear-gradient(135deg, #FFD438, #F6FE0E);
            border: none;
            border-radius: 12px;
            color: #3a3a3a;
            letter-spacing: 1px;
            cursor: pointer;
            transition: 0.3s ease-in-out;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
        }

        .btn-comprar:hover:not([disabled]) {
            background: linear-gradient(135deg, #FEE834, #E8F40C);
            transform: translateY(-3px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25);
        }

        .btn-comprar[disabled] {
            cursor: not-allowed;
            opacity: 0.6;
            box-shadow: none;
        }

        .qty-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 20px 0 25px 0;
        }

        .qty-wrapper p {
            margin: 0;
            font-weight: 600;
            font-size: 16px;
        }

        .quantity {
            display: inline-flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }

        .quantity .qty-minus,
        .quantity .qty-plus {
            padding: 8px 12px;
            cursor: pointer;
            user-select: none;
        }

        .quantity .qty-minus:hover,
        .quantity .qty-plus:hover {
            background: #f2f2f2;
        }

        .quantity .qty-text {
            width: 70px;
            border: none;
            text-align: center;
            font-size: 16px;
            height: 40px;
        }

        .quantity .qty-text:focus {
            outline: none;
        }
    </style>

</head>

<body>

    <div class="main-content-wrapper d-flex clearfix">

        <!-- MENU -->
        <header class="header-area clearfix">
            <div class="logo">
                <a href="index.php"><img src="img/core-img/logo.png" alt="Logo"></a>
            </div>
            <nav class="amado-nav">
                <ul>
                    <li><a href="index.php">Início</a></li>
                    <li class="active"><a href="shop.php">Compras</a></li>
                    <li><a href="checkout.php">Cadastro</a></li>
                </ul>
            </nav>
        </header>

        <!-- DETALHES DO PRODUTO -->
        <div class="single-product-area section-padding-100 clearfix">
            <div class="container-fluid">

                <?php if ($compra_ok): ?>
                    <div class="msg-sucesso">
                        Compra realizada com sucesso! ❤️ Obrigado pela preferência!
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- IMAGEM -->
                    <div class="col-12 col-lg-7">
                        <img src="<?= imgPath($p['imagem']) ?>" style="width:100%; border-radius:10px;" alt="<?= htmlspecialchars($p['nome']) ?>">
                    </div>

                    <!-- INFO / COMPRA -->
                    <div class="col-12 col-lg-5">
                        <h3><?= htmlspecialchars($p['nome']) ?></h3>
                        <p class="product-price">R$ <?= number_format($p['preco'], 2, ',', '.') ?></p>
                        <p><?= nl2br(htmlspecialchars($p['descricao'])) ?></p>

                        <!-- Estoque -->
                        <?php if ($sem_estoque): ?>
                            <p class="estoque-info sem">Produto esgotado no momento.</p>
                        <?php else: ?>
                            <p class="estoque-info ok">Estoque disponível: <?= $estoque ?> unidade(s)</p>
                        <?php endif; ?>

                        <!-- FORM COM QUANTIDADE -->
                        <form action="comprar.php" method="post">
                            <input type="hidden" name="produto_id" value="<?= (int)$p['id'] ?>">

                            <?php if (!$sem_estoque): ?>
                                <div class="qty-wrapper">
                                    <p>Quantidade</p>
                                    <div class="quantity">
                                        <span class="qty-minus" onclick="
                                            var q = document.getElementById('qty');
                                            var v = parseInt(q.value) || 1;
                                            if (v > 1) q.value = v - 1;
                                        "><i class="fa fa-caret-down" aria-hidden="true"></i></span>

                                        <input type="number"
                                               class="qty-text"
                                               id="qty"
                                               name="quantidade"
                                               min="1"
                                               max="<?= $estoque ?>"
                                               value="1">

                                        <span class="qty-plus" onclick="
                                            var q = document.getElementById('qty');
                                            var v = parseInt(q.value) || 1;
                                            var max = parseInt(q.getAttribute('max')) || 9999;
                                            if (v < max) q.value = v + 1;
                                        "><i class="fa fa-caret-up" aria-hidden="true"></i></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <button type="submit" class="btn-comprar" <?= $sem_estoque ? 'disabled' : '' ?>>
                                <?= $sem_estoque ? 'SEM ESTOQUE' : 'COMPRAR' ?>
                            </button>
                        </form>

                    </div>
                </div>
            </div>
        </div>

    </div>

</body>
</html>
