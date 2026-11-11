<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
admin_check();

$page_title = 'Reutilizar Aquisição Favorita';
if (!db_table_exists($pdo, 'aquisicoes_favoritas')) {
    flash_message('danger', 'Instale primeiro a tabela sql/aquisicoes_favoritas.sql.');
    header('Location: aquisicoes_favoritas.php', true, 303);
    exit;
}
$id = filter_var($_GET['id'] ?? $_POST['favorita_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(400);
    exit('Modelo inválido.');
}
$stmt = $pdo->prepare('SELECT * FROM aquisicoes_favoritas WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$favorita = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$favorita) {
    http_response_code(404);
    exit('Modelo favorito não encontrado.');
}
$dados = json_decode((string)$favorita['dados_json'], true);
$itens = json_decode((string)$favorita['itens_json'], true);
if (!is_array($dados) || !is_array($itens) || !$itens) {
    http_response_code(422);
    exit('Este modelo está incompleto. Atualize-o a partir da aquisição original.');
}

if (empty($_SESSION['csrf_aquisicoes_reutilizar'])) {
    $_SESSION['csrf_aquisicoes_reutilizar'] = bin2hex(random_bytes(32));
}
$csrfReutilizar = (string)$_SESSION['csrf_aquisicoes_reutilizar'];
function reutilizar_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$erro = '';
$precos = array_map(static fn($i) => (string)($i['valor_unitario'] ?? '0'), $itens);
$numeroInformado = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = is_scalar($_POST['csrf_token'] ?? null) ? (string)$_POST['csrf_token'] : '';
    $novosPrecos = $_POST['precos'] ?? null;
    $numeroInformado = is_scalar($_POST['numero_oficio'] ?? null) ? trim((string)$_POST['numero_oficio']) : '';
    if (is_array($novosPrecos)) { $precos = $novosPrecos; }

    try {
        if ($token === '' || !hash_equals($csrfReutilizar, $token)) {
            throw new DomainException('Sua sessão expirou. Atualize a página e tente novamente.');
        }
        if (!is_array($novosPrecos) || count($novosPrecos) !== count($itens)) {
            throw new DomainException('Confira os preços de todos os itens.');
        }
        if (strlen($numeroInformado) > 120) {
            throw new DomainException('O número do ofício é muito longo.');
        }

        $secretariaId = (int)($dados['secretaria_id'] ?? 0);
        $fornecedorId = (int)($dados['fornecedor_id'] ?? 0);
        $stmtRef = $pdo->prepare('SELECT (SELECT COUNT(*) FROM secretarias WHERE id = ?) AS secretaria_ok, (SELECT COUNT(*) FROM fornecedores WHERE id = ?) AS fornecedor_ok');
        $stmtRef->execute([$secretariaId, $fornecedorId]);
        $ref = $stmtRef->fetch(PDO::FETCH_ASSOC);
        if (!$ref || !$ref['secretaria_ok'] || !$ref['fornecedor_ok']) {
            throw new DomainException('A secretaria ou o fornecedor deste modelo não está mais cadastrado.');
        }
        $itensNovos = [];
        $total = 0.0;
        foreach ($itens as $indice => $item) {
            $valor = $novosPrecos[$indice] ?? null;
            if (!is_scalar($valor) || !preg_match('/^\d{1,10}(?:[.,]\d{1,2})?$/', trim((string)$valor))) {
                throw new DomainException('Informe um preço válido, com até duas casas decimais, no item ' . ($indice + 1) . '.');
            }
            $preco = round((float)str_replace(',', '.', (string)$valor), 2);
            $quantidade = (float)($item['quantidade'] ?? 0);
            $produto = trim((string)($item['produto'] ?? ''));
            if ($produto === '' || $quantidade <= 0 || !is_finite($quantidade) || $preco <= 0) {
                throw new DomainException('Confira o produto, a quantidade e o preço do item ' . ($indice + 1) . '. Preço deve ser maior que zero.');
            }
            $unidade = trim((string)($item['unidade'] ?? 'UN'));
            $itensNovos[] = [
                'produto' => $produto,
                'quantidade' => $quantidade,
                'unidade' => $unidade !== '' ? $unidade : 'UN',
                'valor_unitario' => $preco,
            ];
            $total += $quantidade * $preco;
        }
        if (!is_finite($total) || $total > 9999999999) {
            throw new DomainException('O valor total ultrapassa o limite permitido.');
        }

        $numero = $numeroInformado !== ''
            ? (function_exists('mb_strtoupper') ? mb_strtoupper($numeroInformado, 'UTF-8') : strtoupper($numeroInformado))
            : 'SEM OFÍCIO Nº FAV-' . strtoupper(bin2hex(random_bytes(4))) . '/' . date('Y');
        $local = trim((string)($dados['local'] ?? ''));
        $justificativa = trim((string)($dados['justificativa'] ?? ''));
        if ($local === '') { $local = 'Não informado'; }
        if ($justificativa === '') { $justificativa = 'Reutilização de itens do modelo ' . $favorita['titulo']; }
        $resumo = trim((string)($dados['resumo_itens'] ?? ''));

        $pdo->beginTransaction();
        $stmtNumero = $pdo->prepare('SELECT id FROM oficios WHERE numero = ? LIMIT 1');
        $stmtNumero->execute([$numero]);
        if ($stmtNumero->fetchColumn()) {
            throw new DomainException('Este número de ofício já existe. Informe outro número ou deixe o campo em branco.');
        }
        $stmtOficio = $pdo->prepare("
            INSERT INTO oficios
                (numero, secretaria_id, local, justificativa, resumo_itens,
                 usuario_id, fornecedor_indicado_id, valor_orcamento, status, criado_em)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ENVIADO', NOW())
        ");
        $stmtOficio->execute([
            $numero, $secretariaId, $local, $justificativa,
            $resumo !== '' ? $resumo : null, (int)$_SESSION['user_id'],
            $fornecedorId, round($total, 2),
        ]);
        $oficioId = (int)$pdo->lastInsertId();
        $stmtItem = $pdo->prepare('INSERT INTO itens_oficio (oficio_id, produto, quantidade, unidade, valor_unitario) VALUES (?, ?, ?, ?, ?)');
        foreach ($itensNovos as $item) {
            $stmtItem->execute([
                $oficioId, $item['produto'], $item['quantidade'],
                $item['unidade'], $item['valor_unitario'],
            ]);
        }
        log_action($pdo, 'REUTILIZAR_AQUISICAO_FAVORITA',
            'Solicitação ' . $numero . ' criada a partir do modelo ' . $favorita['titulo'] . '; aguardando aprovação.');
        $pdo->commit();
        $_SESSION['csrf_aquisicoes_reutilizar'] = bin2hex(random_bytes(32));
        flash_message('success', 'Nova solicitação criada com ' . count($itensNovos) . ' itens e preços conferidos. A aquisição será gerada após a aprovação.');
        header('Location: oficios_visualizar.php?id=' . $oficioId, true, 303);
        exit;
    } catch (DomainException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $erro = $e->getMessage();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('SO reutilizar favorita: ' . $e->getMessage());
        $erro = 'Falha ao criar a solicitação. Verifique os dados e tente novamente.';
    }
}
include 'views/layout/header.php';
?>
<style>
.reuse-wrap {max-width:1140px;margin:0 auto;padding:22px 16px 50px}
.reuse-title {display:flex;gap:15px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:22px}
.reuse-title h1 {font-size:1.55rem;color:#17233b;margin:0}
.reuse-card {background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:22px;margin:16px 0;box-shadow:0 4px 16px rgba(15,23,42,.04)}
.reuse-card h2 {font-size:1.13rem;margin:0 0 16px;color:#17233b}
.reuse-fields {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.reuse-fields div {min-width:0}
.reuse-fields small {display:block;color:#64748b;margin-bottom:5px}
.reuse-fields strong {overflow-wrap:anywhere}
.reuse-table {width:100%;border-collapse:collapse;min-width:690px}
.reuse-table th,.reuse-table td {text-align:left;padding:12px;border-bottom:1px solid #e2e8f0}
.reuse-table th {font-size:.85rem;background:#f8fafc;color:#475569}
.reuse-price {border:1px solid #94a3b8;border-radius:9px;padding:10px;width:130px;max-width:100%;font:inherit}
.reuse-number {width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:10px}
.reuse-actions {display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap;margin-top:24px}
.reuse-btn {padding:12px 17px;border:1px solid #cbd5e1;border-radius:10px;text-decoration:none;background:#fff;color:#17233b;font:inherit;cursor:pointer;font-weight:700}
.reuse-btn-primary {background:#1d4ed8;color:white;border-color:#1d4ed8}
.reuse-total {font-size:1.25rem;font-weight:800;color:#1d4ed8;text-align:right}
@media(max-width:640px){.reuse-fields{grid-template-columns:1fr}.reuse-card{padding:15px}}
</style>
<div class="reuse-wrap">
    <div class="reuse-title">
        <h1><i class="fas fa-star" style="color:#eab308"></i> Reutilizar <?= reutilizar_h($favorita['titulo']) ?></h1>
        <a class="reuse-btn" href="aquisicoes_favoritas.php"><i class="fas fa-arrow-left"></i> Favoritas</a>
    </div>
    <?php if ($erro !== ''): ?><div class="alert alert-danger"><?= reutilizar_h($erro) ?></div><?php endif; ?>
    <div class="alert alert-info">Os dados são copiados como <strong>nova solicitação</strong>. Confira os preços e confirme a criação.
        Número, data e aprovação da aquisição anterior não serão reaproveitados.</div>
    <form method="post" id="reuse-form">
        <input type="hidden" name="favorita_id" value="<?= (int)$id ?>">
        <input type="hidden" name="csrf_token" value="<?= reutilizar_h($csrfReutilizar) ?>">
        <section class="reuse-card">
            <h2>Dados preenchidos automaticamente</h2>
            <div class="reuse-fields">
                <div><small>Secretaria</small><strong><?= reutilizar_h($dados['secretaria_nome'] ?? '-') ?></strong></div>
                <div><small>Fornecedor</small><strong><?= reutilizar_h($dados['fornecedor_nome'] ?? '-') ?></strong></div>
                <div><small>Local</small><strong><?= reutilizar_h($dados['local'] ?? '-') ?></strong></div>
                <div><small>Origem do modelo</small><strong><?= reutilizar_h($dados['numero_aq'] ?? '-') ?></strong></div>
                <div style="grid-column:1/-1"><small>Justificativa original</small>
                    <strong><?= reutilizar_h($dados['justificativa'] ?? '-') ?></strong></div>
                <div style="grid-column:1/-1">
                    <label for="numero-oficio" style="font-weight:700;display:block;margin-bottom:5px">Número do novo ofício (opcional)</label>
                    <input class="reuse-number" type="text" id="numero-oficio" name="numero_oficio" maxlength="120"
                           value="<?= reutilizar_h($numeroInformado) ?>" placeholder="Deixe em branco para gerar uma solicitação SEM OFÍCIO">
                    <small>Se houver um ofício oficial, informe o número real. Caso contrário, o sistema gera referência única “SEM OFÍCIO”.</small>
                </div>
            </div>
        </section>
        <section class="reuse-card">
            <h2>Confira os preços dos itens</h2>
            <div style="overflow-x:auto">
                <table class="reuse-table">
                    <thead><tr><th>Item</th><th>Quantidade</th><th>Unidade</th><th>Preço unitário (R$)</th><th>Subtotal</th></tr></thead>
                    <tbody>
                    <?php foreach ($itens as $idx => $item): ?>
                        <tr class="reuse-item" data-qtd="<?= reutilizar_h($item['quantidade'] ?? 0) ?>">
                            <td><?= reutilizar_h($item['produto'] ?? '-') ?></td>
                            <td><?= reutilizar_h($item['quantidade'] ?? '0') ?></td>
                            <td><?= reutilizar_h($item['unidade'] ?? 'UN') ?></td>
                            <td><input class="reuse-price" type="number" min="0.01" step="0.01"
                                       name="precos[<?= (int)$idx ?>]" value="<?= reutilizar_h($precos[$idx] ?? '0') ?>"
                                       aria-label="Preço do item <?= (int)$idx + 1 ?>" required></td>
                            <td class="reuse-subtotal">R$ 0,00</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="reuse-total">Total atualizado: <span id="reuse-total">R$ 0,00</span></p>
        </section>
        <div class="reuse-actions">
            <a class="reuse-btn" href="aquisicoes_favoritas.php">Cancelar</a>
            <button class="reuse-btn reuse-btn-primary" type="submit"
                onclick="return confirm('Criar uma nova solicitação com estes itens e valores? Ela seguirá para aprovação.')">
                <i class="fas fa-check-circle"></i> Criar solicitação com preços conferidos
            </button>
        </div>
    </form>
</div>
<script>
(function () {
    const format = new Intl.NumberFormat('pt-BR', {style: 'currency', currency: 'BRL'});
    const linhas = document.querySelectorAll('.reuse-item');
    function recalcular() {
        let total = 0;
        linhas.forEach(function (linha) {
            const qtd = Number(linha.dataset.qtd || 0);
            const preco = Number(linha.querySelector('.reuse-price').value || 0);
            const subtotal = qtd * preco;
            linha.querySelector('.reuse-subtotal').textContent = format.format(subtotal);
            total += subtotal;
        });
        document.getElementById('reuse-total').textContent = format.format(total);
    }
    linhas.forEach(function (linha) {
        linha.querySelector('.reuse-price').addEventListener('input', recalcular);
    });
    recalcular();
})();
</script>
<?php include 'views/layout/footer.php'; ?>
