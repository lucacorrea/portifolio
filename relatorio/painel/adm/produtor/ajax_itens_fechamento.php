<?php
declare(strict_types=1);
session_start();
require '../../../assets/php/conexao.php';
$pdo = db();

if (empty($_SESSION['usuario_logado'])) exit;

$produtorId = (int)($_GET['id'] ?? 0);
$dia = $_GET['dia'] ?? date('Y-m-d');

try {
    // Busca o romaneio do dia
    $stR = $pdo->prepare("SELECT id FROM romaneio_dia WHERE feira_id = 1 AND data_ref = :d LIMIT 1");
    $stR->execute([':d' => $dia]);
    $romId = $stR->fetchColumn();

    if (!$romId) {
        echo '<div class="alert alert-warning">Romaneio não encontrado para esta data.</div>';
        exit;
    }

    // Busca os itens lançados por este produtor
    $st = $pdo->prepare("
        SELECT ri.id, p.nome as produto_nome, ri.quantidade_entrada, ri.quantidade_vendida
        FROM romaneio_itens ri
        JOIN produtos p ON p.id = ri.produto_id
        WHERE ri.romaneio_id = :rom AND ri.produtor_id = :prod
    ");
    $st->execute([':rom' => $romId, ':prod' => $produtorId]);
    $itens = $st->fetchAll();

    if (empty($itens)) {
        echo '<div class="alert alert-info">Nenhum produto lançado para este produtor hoje.</div>';
        exit;
    }
?>
    <div class="table-responsive">
        <table class="table table-bordered table-sm">
            <thead class="bg-light">
                <tr>
                    <th>Produto</th>
                    <th class="text-center" style="width:100px;">Entrada</th>
                    <th class="text-center" style="width:150px;">Vendas Reais</th>
                    <th class="text-center" style="width:140px;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $item): 
                    $qtdTotal = (float)$item['quantidade_entrada'];
                    $qtdVendida = $item['quantidade_vendida'] !== null ? (float)$item['quantidade_vendida'] : '';
                ?>
                    <tr>
                        <td class="align-middle"><b><?= htmlspecialchars((string)$item['produto_nome']) ?></b></td>
                        <td class="text-center align-middle"><?= number_format($qtdTotal, 3, ',', '.') ?></td>
                        <td>
                            <input type="text" name="venda[<?= $item['id'] ?>]" id="venda_<?= $item['id'] ?>" 
                                   class="form-control form-control-sm text-center font-weight-bold" 
                                   value="<?= $qtdVendida ?>"
                                   placeholder="0,000" 
                                   oninput="this.value = this.value.replace(/[^0-9,]/g,'')">
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-outline-success btn-sm w-100" 
                                    onclick="toggleVendeuTudo(<?= $item['id'] ?>, '<?= $qtdTotal ?>')">
                                Vendeu Tudo?
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php
} catch (Throwable $e) {
    echo '<div class="alert alert-danger">Erro ao carregar itens: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
