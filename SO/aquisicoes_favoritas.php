<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

$nivelFavoritas = strtoupper(trim((string)($_SESSION['nivel'] ?? '')));
if (!in_array($nivelFavoritas, ['ADMIN', 'SUPORTE', 'SECRETARIO'], true)) {
    http_response_code(403);
    exit('Você não tem permissão para acessar aquisições favoritas.');
}
$podeGerenciarFavoritas = in_array($nivelFavoritas, ['ADMIN', 'SUPORTE'], true);
$page_title = 'Aquisições Favoritas';
$tabelaFavoritasExiste = db_table_exists($pdo, 'aquisicoes_favoritas');

if (empty($_SESSION['csrf_aquisicoes_favoritas'])) {
    $_SESSION['csrf_aquisicoes_favoritas'] = bin2hex(random_bytes(32));
}
$csrfFavoritas = (string)$_SESSION['csrf_aquisicoes_favoritas'];

function favoritas_h($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$podeGerenciarFavoritas) {
        http_response_code(403);
        exit('Sem permissão para alterar modelos.');
    }
    if (!$tabelaFavoritasExiste) {
        flash_message('danger', 'Instale primeiro a tabela pelo arquivo sql/aquisicoes_favoritas.sql.');
        header('Location: aquisicoes_favoritas.php', true, 303);
        exit;
    }
    $token = is_scalar($_POST['csrf_token'] ?? null) ? (string)$_POST['csrf_token'] : '';
    if ($token === '' || !hash_equals($csrfFavoritas, $token)) {
        http_response_code(403);
        exit('Sessão expirada. Atualize a página antes de continuar.');
    }
    $acao = is_scalar($_POST['acao'] ?? null) ? (string)$_POST['acao'] : '';
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    try {
        if (!$id) {
            throw new DomainException('Identificador inválido.');
        }
        if ($acao === 'salvar') {
            $stmt = $pdo->prepare("
                SELECT a.id, a.numero_aq, a.fornecedor_id, o.secretaria_id,
                       o.local, o.justificativa, o.resumo_itens,
                       f.nome AS fornecedor_nome, s.nome AS secretaria_nome
                FROM aquisicoes a
                INNER JOIN oficios o ON o.id = a.oficio_id
                INNER JOIN fornecedores f ON f.id = a.fornecedor_id
                INNER JOIN secretarias s ON s.id = o.secretaria_id
                WHERE a.id = ?
            ");
            $stmt->execute([$id]);
            $origem = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$origem) {
                throw new DomainException('A aquisição de origem não foi encontrada.');
            }
            $stmtItens = $pdo->prepare("
                SELECT ai.produto, ai.quantidade, ai.valor_unitario,
                       COALESCE(
                           (SELECT NULLIF(io2.unidade, '') FROM itens_oficio io2
                            WHERE io2.oficio_id = a.oficio_id AND io2.produto = ai.produto
                            ORDER BY io2.id ASC LIMIT 1), 'UN') AS unidade
                FROM itens_aquisicao ai
                INNER JOIN aquisicoes a ON a.id = ai.aquisicao_id
                WHERE ai.aquisicao_id = ? ORDER BY ai.id ASC
            ");
            $stmtItens->execute([$id]);
            $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
            if (!$itens) {
                throw new DomainException('Esta aquisição não possui itens para reutilizar.');
            }
            $titulo = 'Aquisição ' . (string)$origem['numero_aq'];
            $dados = json_encode($origem, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $itensJson = json_encode($itens, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $stmtSalvar = $pdo->prepare("
                INSERT INTO aquisicoes_favoritas
                    (aquisicao_origem_id, titulo, dados_json, itens_json, criado_por)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE titulo = VALUES(titulo),
                    dados_json = VALUES(dados_json), itens_json = VALUES(itens_json),
                    atualizado_em = CURRENT_TIMESTAMP
            ");
            $stmtSalvar->execute([$id, $titulo, $dados, $itensJson, (int)($_SESSION['user_id'] ?? 0) ?: null]);
            log_action($pdo, 'FAVORITAR_AQUISICAO', 'Modelo salvo/atualizado a partir da aquisição ' . $origem['numero_aq']);
            flash_message('success', 'Aquisição adicionada aos favoritos. Itens e dados foram salvos como modelo.');
        } elseif ($acao === 'remover') {
            $stmtRemover = $pdo->prepare('DELETE FROM aquisicoes_favoritas WHERE id = ?');
            $stmtRemover->execute([$id]);
            log_action($pdo, 'REMOVER_FAVORITA', 'Modelo de aquisição favorito nº ' . $id . ' removido');
            flash_message('success', 'Modelo removido dos favoritos. A aquisição original não foi alterada.');
        } else {
            throw new DomainException('Ação inválida.');
        }
    } catch (DomainException $e) {
        flash_message('danger', $e->getMessage());
    } catch (Throwable $e) {
        error_log('SO favoritas: ' . $e->getMessage());
        flash_message('danger', 'Não foi possível concluir a operação. Confira os dados e a estrutura do banco.');
    }
    header('Location: aquisicoes_favoritas.php', true, 303);
    exit;
}

$favoritas = [];
if ($tabelaFavoritasExiste) {
    $favoritas = $pdo->query('SELECT id, titulo, dados_json, itens_json, atualizado_em FROM aquisicoes_favoritas ORDER BY atualizado_em DESC, id DESC')
        ->fetchAll(PDO::FETCH_ASSOC);
}
include 'views/layout/header.php';
?>
<style>
.fav-page {max-width: 1180px;margin: 0 auto;padding: 20px 16px 46px}
.fav-head {display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:22px}
.fav-head h1 {font-size:1.7rem;margin:0;color:#17233b}
.fav-head p {color:#64748b;margin:6px 0 0}
.fav-btn {display:inline-flex;align-items:center;gap:8px;border-radius:10px;border:1px solid #cbd5e1;padding:11px 15px;background:#fff;color:#173453;font-weight:700;text-decoration:none;cursor:pointer;font:inherit}
.fav-btn-primary {background:#1d4ed8;border-color:#1d4ed8;color:#fff}
.fav-btn-danger {background:#fff;color:#b91c1c;border-color:#fecaca}
.fav-grid {display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:18px}
.fav-card {background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:20px;box-shadow:0 4px 18px rgba(15,23,42,.05)}
.fav-card h2 {font-size:1.13rem;margin:0 0 12px;color:#17233b}
.fav-meta {color:#475569;font-size:.91rem;margin:7px 0;overflow-wrap:anywhere}
.fav-price {font-weight:800;font-size:1.1rem;color:#1d4ed8;margin:15px 0}
.fav-actions {display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}
.fav-empty {padding:42px 22px;background:#fff;border:1px dashed #cbd5e1;border-radius:16px;text-align:center;color:#64748b}
.fav-tag {background:#f0fdf4;color:#166534;border-radius:9px;padding:5px 9px;font-size:.76rem;font-weight:750}
</style>
<div class="fav-page">
    <div class="fav-head">
        <div>
            <h1><i class="fas fa-star" style="color:#eab308"></i> Aquisições Favoritas</h1>
            <p>Reutilize itens, fornecedor e secretaria sem copiar número, assinatura ou aprovação.</p>
        </div>
        <a class="fav-btn" href="aquisicoes_lista.php"><i class="fas fa-arrow-left"></i> Voltar às aquisições</a>
    </div>
    <?php display_flash(); ?>
    <?php if (!$tabelaFavoritasExiste): ?>
        <div class="alert alert-warning">O módulo ainda não foi instalado no banco. Execute manualmente
            <strong>SO/sql/aquisicoes_favoritas.sql</strong> no phpMyAdmin da hospedagem.</div>
    <?php elseif (!$favoritas): ?>
        <div class="fav-empty"><i class="fas fa-star" style="font-size:2rem"></i>
            <h2>Nenhum modelo salvo</h2>
            <p>Abra a lista de aquisições, clique em uma linha e selecione “Adicionar aos favoritos”.</p>
        </div>
    <?php else: ?>
        <div class="fav-grid">
            <?php foreach ($favoritas as $fav):
                $dados = json_decode((string)$fav['dados_json'], true);
                $itens = json_decode((string)$fav['itens_json'], true);
                if (!is_array($dados) || !is_array($itens)) { continue; }
                $valor = 0.0;
                foreach ($itens as $item) {
                    $valor += (float)($item['quantidade'] ?? 0) * (float)($item['valor_unitario'] ?? 0);
                }
            ?>
                <article class="fav-card">
                    <div class="fav-tag"><i class="fas fa-bookmark"></i> Modelo reutilizável</div>
                    <h2 style="margin-top:13px"><?= favoritas_h($fav['titulo']) ?></h2>
                    <p class="fav-meta"><strong>Secretaria:</strong> <?= favoritas_h($dados['secretaria_nome'] ?? '-') ?></p>
                    <p class="fav-meta"><strong>Fornecedor:</strong> <?= favoritas_h($dados['fornecedor_nome'] ?? '-') ?></p>
                    <p class="fav-meta"><strong>Local:</strong> <?= favoritas_h($dados['local'] ?? '-') ?></p>
                    <p class="fav-meta"><strong>Itens:</strong> <?= count($itens) ?></p>
                    <p class="fav-price">Último valor salvo: <?= format_money($valor) ?></p>
                    <small class="fav-meta">Salvo em <?= favoritas_h(date('d/m/Y H:i', strtotime((string)$fav['atualizado_em']))) ?></small>
                    <div class="fav-actions">
                        <?php if ($podeGerenciarFavoritas): ?>
                            <a class="fav-btn fav-btn-primary" href="aquisicoes_favoritas_reutilizar.php?id=<?= (int)$fav['id'] ?>">
                                <i class="fas fa-redo"></i> Reutilizar
                            </a>
                            <form method="post" action="aquisicoes_favoritas.php" onsubmit="return confirm('Remover este modelo dos favoritos? A aquisição original será preservada.')">
                                <input type="hidden" name="csrf_token" value="<?= favoritas_h($csrfFavoritas) ?>">
                                <input type="hidden" name="acao" value="remover">
                                <input type="hidden" name="id" value="<?= (int)$fav['id'] ?>">
                                <button class="fav-btn fav-btn-danger" type="submit"><i class="fas fa-trash-alt"></i> Remover</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php include 'views/layout/footer.php'; ?>
