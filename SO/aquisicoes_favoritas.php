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
$tabelaFavoritasExiste = so_schema_table_exists($pdo, 'aquisicoes_favoritas');
$tabelaOficiosFavoritosExiste = so_schema_table_exists($pdo, 'oficios_favoritos');

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
    if (!$tabelaFavoritasExiste && !$tabelaOficiosFavoritosExiste) {
        flash_message('danger', 'Instale as tabelas pelo arquivo sql/aquisicoes_favoritas.sql.');
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
            if (!$tabelaFavoritasExiste) {
                throw new DomainException('Instale a tabela de aquisições favoritas pelo SQL.');
            }
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
        } elseif ($acao === 'salvar_oficio') {
            if (!$tabelaOficiosFavoritosExiste) {
                throw new DomainException('Instale a tabela de ofícios favoritos no arquivo sql/aquisicoes_favoritas.sql.');
            }
            $stmt = $pdo->prepare("
                SELECT o.id, o.numero AS numero_oficio, o.secretaria_id, o.local,
                       o.justificativa, o.resumo_itens, s.nome AS secretaria_nome,
                       COALESCE(f_ind.id, f_aq.id) AS fornecedor_id,
                       COALESCE(f_ind.nome, f_aq.nome, 'Não definido') AS fornecedor_nome
                FROM oficios o
                INNER JOIN secretarias s ON s.id = o.secretaria_id
                LEFT JOIN fornecedores f_ind ON f_ind.id = o.fornecedor_indicado_id
                LEFT JOIN aquisicoes a ON a.id = (
                    SELECT a2.id FROM aquisicoes a2
                    WHERE a2.oficio_id = o.id ORDER BY a2.id DESC LIMIT 1
                )
                LEFT JOIN fornecedores f_aq ON f_aq.id = a.fornecedor_id
                WHERE o.id = ?
            ");
            $stmt->execute([$id]);
            $origem = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$origem) {
                throw new DomainException('Ofício de origem não encontrado.');
            }
            $stmtItens = $pdo->prepare("
                SELECT produto, quantidade, valor_unitario, COALESCE(NULLIF(unidade, ''), 'UN') AS unidade
                FROM itens_oficio WHERE oficio_id = ? ORDER BY id ASC
            ");
            $stmtItens->execute([$id]);
            $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
            if (!$itens) {
                throw new DomainException('Este ofício ainda não possui itens cadastrados para reutilizar.');
            }
            $titulo = 'Ofício ' . (string)$origem['numero_oficio'];
            $dados = json_encode($origem, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $itensJson = json_encode($itens, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $stmtSalvar = $pdo->prepare("
                INSERT INTO oficios_favoritos
                    (oficio_origem_id, titulo, dados_json, itens_json, criado_por)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE titulo = VALUES(titulo),
                    dados_json = VALUES(dados_json), itens_json = VALUES(itens_json),
                    atualizado_em = CURRENT_TIMESTAMP
            ");
            $stmtSalvar->execute([$id, $titulo, $dados, $itensJson, (int)($_SESSION['user_id'] ?? 0) ?: null]);
            log_action($pdo, 'FAVORITAR_OFICIO', 'Modelo de itens salvo/atualizado a partir do ofício ' . $origem['numero_oficio']);
            flash_message('success', 'Ofício adicionado aos favoritos com os itens para reutilização.');
        } elseif ($acao === 'remover_oficio') {
            if (!$tabelaOficiosFavoritosExiste) {
                throw new DomainException('Tabela de ofícios favoritos não instalada.');
            }
            $stmtRemover = $pdo->prepare('DELETE FROM oficios_favoritos WHERE id = ?');
            $stmtRemover->execute([$id]);
            log_action($pdo, 'REMOVER_OFICIO_FAVORITO', 'Modelo de ofício favorito nº ' . $id . ' removido');
            flash_message('success', 'Modelo removido dos favoritos. O ofício original foi preservado.');
        } elseif ($acao === 'remover') {
            if (!$tabelaFavoritasExiste) {
                throw new DomainException('Tabela de aquisições favoritas não instalada.');
            }
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
    foreach ($pdo->query('SELECT id, titulo, dados_json, itens_json, atualizado_em FROM aquisicoes_favoritas')->fetchAll(PDO::FETCH_ASSOC) as $modelo) {
        $modelo['tipo'] = 'aquisicao';
        $favoritas[] = $modelo;
    }
}
if ($tabelaOficiosFavoritosExiste) {
    foreach ($pdo->query('SELECT id, titulo, dados_json, itens_json, atualizado_em FROM oficios_favoritos')->fetchAll(PDO::FETCH_ASSOC) as $modelo) {
        $modelo['tipo'] = 'oficio';
        $favoritas[] = $modelo;
    }
}
usort($favoritas, static function (array $a, array $b): int {
    return strcmp((string)$b['atualizado_em'], (string)$a['atualizado_em']);
});
// Monta os dados de apresentação sem alterar os modelos salvos.
$linhasFavoritas = [];
foreach ($favoritas as $fav) {
    $dados = json_decode((string)$fav['dados_json'], true);
    $itens = json_decode((string)$fav['itens_json'], true);
    if (!is_array($dados) || !is_array($itens)) {
        continue;
    }
    $total = 0.0;
    foreach ($itens as $item) {
        $total += (float)($item['quantidade'] ?? 0) * (float)($item['valor_unitario'] ?? 0);
    }
    $fav['secretaria_nome'] = (string)($dados['secretaria_nome'] ?? '-');
    $fav['fornecedor_nome'] = (string)($dados['fornecedor_nome'] ?? '-');
    $fav['local_nome'] = (string)($dados['local'] ?? '-');
    $fav['numero_origem'] = (string)($dados['numero_aq'] ?? $dados['numero_oficio'] ?? '-');
    $fav['quantidade_itens'] = count($itens);
    $fav['valor_total'] = $total;
    $linhasFavoritas[] = $fav;
}

$buscaFavoritas = is_scalar($_GET['busca'] ?? null) ? trim((string)$_GET['busca']) : '';
$buscaFavoritas = function_exists('mb_substr') ? mb_substr($buscaFavoritas, 0, 120, 'UTF-8') : substr($buscaFavoritas, 0, 120);
$tipoFavoritas = is_scalar($_GET['tipo'] ?? null) ? (string)$_GET['tipo'] : '';
if (!in_array($tipoFavoritas, ['', 'oficio', 'aquisicao'], true)) {
    $tipoFavoritas = '';
}
$encontradasFavoritas = array_values(array_filter($linhasFavoritas, static function (array $fav) use ($buscaFavoritas, $tipoFavoritas): bool {
    if ($tipoFavoritas !== '' && $fav['tipo'] !== $tipoFavoritas) {
        return false;
    }
    if ($buscaFavoritas === '') {
        return true;
    }
    $texto = implode(' ', [
        $fav['titulo'], $fav['numero_origem'], $fav['secretaria_nome'],
        $fav['fornecedor_nome'], $fav['local_nome']
    ]);
    return function_exists('mb_stripos')
        ? mb_stripos($texto, $buscaFavoritas, 0, 'UTF-8') !== false
        : stripos($texto, $buscaFavoritas) !== false;
}));
$porPaginaFavoritas = 10;
$totalFavoritas = count($encontradasFavoritas);
$totalPaginasFavoritas = max(1, (int)ceil($totalFavoritas / $porPaginaFavoritas));
$paginaFavoritas = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$paginaFavoritas = min($paginaFavoritas, $totalPaginasFavoritas);
$exibidasFavoritas = array_slice($encontradasFavoritas, ($paginaFavoritas - 1) * $porPaginaFavoritas, $porPaginaFavoritas);
function favoritas_pagina_url(int $pagina, string $busca, string $tipo): string {
    return 'aquisicoes_favoritas.php?' . http_build_query([
        'busca' => $busca, 'tipo' => $tipo, 'page' => $pagina
    ]);
}
include 'views/layout/header.php';
?>
<link rel="stylesheet" href="assets/css/lista-acoes-modal.css">
<style>
.fav-page{max-width:1260px;margin:0 auto;padding:22px 16px 48px}
.fav-head{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:20px}
.fav-head h1{font-size:1.65rem;line-height:1.25;margin:0;color:#17233b;font-weight:800}
.fav-head p{margin:7px 0 0;color:#64748b;font-size:.91rem}
.fav-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.fav-btn{display:inline-flex;gap:8px;align-items:center;justify-content:center;text-decoration:none;border:1px solid #cbd5e1;border-radius:9px;padding:10px 14px;background:#fff;color:#173453;font:inherit;font-size:.9rem;font-weight:700;cursor:pointer}
.fav-btn:hover,.fav-btn:focus-visible{border-color:#93c5fd;background:#eff6ff;color:#1d4ed8}
.fav-table-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;box-shadow:0 4px 18px rgba(15,23,42,.045)}
.fav-filters{display:flex;align-items:flex-end;flex-wrap:wrap;gap:12px;padding:18px 18px 16px;border-bottom:1px solid #e2e8f0}
.fav-field{display:flex;flex-direction:column;gap:6px;min-width:180px;flex:1}
.fav-field--search{flex:2;min-width:230px}
.fav-field label{font-size:.78rem;font-weight:800;color:#334155}
.fav-input{width:100%;border:1px solid #cbd5e1;border-radius:9px;background:#fff;color:#17233b;padding:10px 12px;min-height:42px;font:inherit;box-sizing:border-box}
.fav-input:focus-visible{outline:2px solid #93c5fd;outline-offset:1px}
.fav-table-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:13px 18px}
.fav-table-bar h2{font-size:1.02rem;color:#17233b;margin:0;font-weight:800}
.fav-count{font-size:.79rem;font-weight:750;background:#eef2ff;border-radius:999px;padding:5px 10px;color:#334da7}
.fav-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
.fav-table{width:100%;min-width:900px;border-collapse:collapse;color:#334155}
.fav-table thead{background:#f8fafc}
.fav-table th{font-size:.76rem;text-transform:uppercase;letter-spacing:.03em;color:#64748b;font-weight:800;padding:12px 16px;text-align:left;white-space:nowrap;border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0}
.fav-table td{padding:15px 16px;border-bottom:1px solid #edf2f7;font-size:.88rem;vertical-align:middle}
.fav-table tbody tr:last-child td{border-bottom:0}
.fav-model-title{color:#17233b;font-weight:800;display:block;max-width:275px;overflow-wrap:anywhere}
.fav-number{color:#64748b;font-size:.78rem;margin-top:4px}
.fav-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border-radius:7px;background:#eff6ff;color:#1d4ed8;font-size:.77rem;font-weight:750;white-space:nowrap}
.fav-badge--oficio{background:#fff7ed;color:#9a3412}
.fav-wrap-text{display:block;max-width:210px;overflow-wrap:anywhere}
.fav-total{color:#1d4ed8;font-weight:850;white-space:nowrap}
.fav-date{white-space:nowrap;font-size:.83rem;color:#475569}
.fav-empty{text-align:center;padding:44px 20px;color:#64748b}
.fav-empty h3{font-size:1rem;color:#334155;margin:10px 0 6px}
.fav-empty p{margin:0}
.fav-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;padding:15px 18px;border-top:1px solid #e2e8f0;color:#64748b;font-size:.84rem}
.fav-pagination nav{display:flex;align-items:center;gap:8px}
.fav-pagination .fav-btn[aria-disabled="true"]{pointer-events:none;opacity:.45}
.fav-modal-details{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;margin-bottom:6px;color:#475569;font-size:.83rem;line-height:1.55;overflow-wrap:anywhere}
.fav-modal-details strong{color:#173453}
.fav-modal-remove{margin:0}
.fav-modal-note{margin:2px 0 0;color:#64748b;font-size:.75rem;line-height:1.5}
@media(max-width:640px){
.fav-head h1{font-size:1.35rem}
.fav-filters{padding:14px}
.fav-field,.fav-field--search{min-width:100%}
.fav-filters .fav-btn{flex:1}
.fav-table-bar{padding:14px}
.fav-table th,.fav-table td{padding:12px}
.fav-pagination{justify-content:center;text-align:center}
}
</style>

<div class="fav-page">
    <div class="fav-head">
        <div>
            <h1><i class="fas fa-star" style="color:#eab308" aria-hidden="true"></i> Aquisições Favoritas</h1>
            <p>Modelos de ofícios e aquisições para reutilizar. Confira os preços antes de criar uma nova solicitação.</p>
        </div>
        <div class="fav-actions">
            <a class="fav-btn" href="oficios_lista.php"><i class="fas fa-folder-open" aria-hidden="true"></i> Ofícios</a>
            <a class="fav-btn" href="aquisicoes_lista.php"><i class="fas fa-shopping-bag" aria-hidden="true"></i> Aquisições</a>
        </div>
    </div>

    <?php display_flash(); ?>
    <?php if (!$tabelaFavoritasExiste && !$tabelaOficiosFavoritosExiste): ?>
        <div class="alert alert-warning">Para habilitar os favoritos, execute <strong>SO/sql/aquisicoes_favoritas.sql</strong> no phpMyAdmin da hospedagem.</div>
    <?php else: ?>
        <div class="fav-table-card">
            <form method="get" class="fav-filters" action="aquisicoes_favoritas.php" role="search">
                <div class="fav-field fav-field--search">
                    <label for="fav-busca">Pesquisar modelo</label>
                    <input class="fav-input" id="fav-busca" type="search" name="busca" value="<?= favoritas_h($buscaFavoritas) ?>"
                           placeholder="Número, secretaria, fornecedor ou local">
                </div>
                <div class="fav-field">
                    <label for="fav-tipo">Tipo</label>
                    <select class="fav-input" id="fav-tipo" name="tipo">
                        <option value="" <?= $tipoFavoritas === '' ? 'selected' : '' ?>>Todos os modelos</option>
                        <option value="oficio" <?= $tipoFavoritas === 'oficio' ? 'selected' : '' ?>>Ofícios</option>
                        <option value="aquisicao" <?= $tipoFavoritas === 'aquisicao' ? 'selected' : '' ?>>Aquisições</option>
                    </select>
                </div>
                <button class="fav-btn" type="submit"><i class="fas fa-search" aria-hidden="true"></i> Filtrar</button>
                <a class="fav-btn" href="aquisicoes_favoritas.php"><i class="fas fa-eraser" aria-hidden="true"></i> Limpar</a>
            </form>
            <div class="fav-table-bar">
                <h2><i class="fas fa-list-ul" style="color:#2563eb" aria-hidden="true"></i> Modelos salvos</h2>
                <span class="fav-count"><?= $totalFavoritas ?> <?= $totalFavoritas === 1 ? 'modelo' : 'modelos' ?></span>
            </div>
            <p class="so-list-tip" style="padding:0 18px;margin:0 0 12px">
                <i class="fas fa-mouse-pointer" aria-hidden="true"></i> Clique em uma linha para visualizar os detalhes e as ações.
            </p>
            <div class="fav-scroll">
                <table class="fav-table">
                    <thead>
                        <tr>
                            <th>Modelo</th>
                            <th>Tipo</th>
                            <th>Secretaria</th>
                            <th>Fornecedor</th>
                            <th>Itens</th>
                            <th>Último valor</th>
                            <th>Salvo em</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exibidasFavoritas as $fav):
                            $tipo = $fav['tipo'] === 'oficio' ? 'oficio' : 'aquisicao';
                            $templateId = 'fav-acoes-' . $tipo . '-' . (int)$fav['id'];
                            $dataSalva = strtotime((string)$fav['atualizado_em']);
                        ?>
                            <tr class="so-list-row" data-so-list-row tabindex="0" aria-haspopup="dialog"
                                data-so-actions-template="<?= favoritas_h($templateId) ?>"
                                data-so-actions-title="<?= favoritas_h($fav['titulo']) ?>"
                                data-so-actions-subtitle="<?= favoritas_h($fav['secretaria_nome'] . ' • ' . $fav['fornecedor_nome']) ?>"
                                title="Clique para visualizar ações do modelo">
                                <td>
                                    <span class="fav-model-title"><i class="fas fa-star" style="color:#eab308" aria-hidden="true"></i> <?= favoritas_h($fav['titulo']) ?></span>
                                    <div class="fav-number">Ref.: <?= favoritas_h($fav['numero_origem']) ?></div>
                                </td>
                                <td>
                                    <span class="fav-badge <?= $tipo === 'oficio' ? 'fav-badge--oficio' : '' ?>">
                                        <i class="fas <?= $tipo === 'oficio' ? 'fa-file-alt' : 'fa-shopping-bag' ?>" aria-hidden="true"></i>
                                        <?= $tipo === 'oficio' ? 'Ofício' : 'Aquisição' ?>
                                    </span>
                                </td>
                                <td><span class="fav-wrap-text"><?= favoritas_h($fav['secretaria_nome']) ?></span></td>
                                <td><span class="fav-wrap-text"><?= favoritas_h($fav['fornecedor_nome']) ?></span></td>
                                <td><strong><?= (int)$fav['quantidade_itens'] ?></strong></td>
                                <td class="fav-total"><?= format_money($fav['valor_total']) ?></td>
                                <td class="fav-date"><?= $dataSalva ? favoritas_h(date('d/m/Y H:i', $dataSalva)) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$exibidasFavoritas): ?>
                            <tr>
                                <td colspan="7" class="fav-empty">
                                    <i class="far fa-star" style="font-size:1.5rem" aria-hidden="true"></i>
                                    <h3>Nenhum modelo encontrado</h3>
                                    <p><?= $buscaFavoritas !== '' || $tipoFavoritas !== '' ? 'Tente mudar os filtros da pesquisa.' : 'Favorite um ofício ou aquisição para vê-lo nesta lista.' ?></p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPaginasFavoritas > 1): ?>
                <div class="fav-pagination">
                    <span>Página <?= $paginaFavoritas ?> de <?= $totalPaginasFavoritas ?> — <?= $totalFavoritas ?> resultados</span>
                    <nav aria-label="Paginação de favoritas">
                        <a class="fav-btn" href="<?= favoritas_h(favoritas_pagina_url(max(1, $paginaFavoritas - 1), $buscaFavoritas, $tipoFavoritas)) ?>"
                           <?= $paginaFavoritas === 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Anterior</a>
                        <a class="fav-btn" href="<?= favoritas_h(favoritas_pagina_url(min($totalPaginasFavoritas, $paginaFavoritas + 1), $buscaFavoritas, $tipoFavoritas)) ?>"
                           <?= $paginaFavoritas === $totalPaginasFavoritas ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Próxima</a>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php foreach ($exibidasFavoritas as $fav):
    $tipo = $fav['tipo'] === 'oficio' ? 'oficio' : 'aquisicao';
    $templateId = 'fav-acoes-' . $tipo . '-' . (int)$fav['id'];
?>
    <template id="<?= favoritas_h($templateId) ?>">
        <div class="fav-modal-details">
            <div><strong>Secretaria:</strong> <?= favoritas_h($fav['secretaria_nome']) ?></div>
            <div><strong>Fornecedor:</strong> <?= favoritas_h($fav['fornecedor_nome']) ?></div>
            <div><strong>Local:</strong> <?= favoritas_h($fav['local_nome']) ?></div>
            <div><strong>Itens:</strong> <?= (int)$fav['quantidade_itens'] ?> • <strong>Valor salvo:</strong> <?= format_money($fav['valor_total']) ?></div>
        </div>
        <?php if ($podeGerenciarFavoritas): ?>
            <a class="so-action-link" href="aquisicoes_favoritas_reutilizar.php?tipo=<?= favoritas_h($tipo) ?>&amp;id=<?= (int)$fav['id'] ?>">
                <i class="fas fa-redo" aria-hidden="true"></i> Reutilizar e conferir preços
            </a>
            <form method="post" action="aquisicoes_favoritas.php" class="fav-modal-remove"
                  onsubmit="return confirm('Remover este modelo dos favoritos? O registro original será preservado.')">
                <input type="hidden" name="csrf_token" value="<?= favoritas_h($csrfFavoritas) ?>">
                <input type="hidden" name="acao" value="<?= $tipo === 'oficio' ? 'remover_oficio' : 'remover' ?>">
                <input type="hidden" name="id" value="<?= (int)$fav['id'] ?>">
                <button class="so-action-link so-action-link--danger" type="submit">
                    <i class="fas fa-trash-alt" aria-hidden="true"></i> Remover dos favoritos
                </button>
            </form>
        <?php else: ?>
            <p class="fav-modal-note">Você pode consultar este modelo, mas não possui permissão para alterá-lo.</p>
        <?php endif; ?>
        <p class="fav-modal-note">A exclusão do favorito não remove o ofício ou a aquisição original.</p>
    </template>
<?php endforeach; ?>

<div class="so-action-modal" id="so-list-actions-modal" hidden aria-hidden="true">
    <div class="so-action-modal__panel" role="dialog" aria-modal="true"
         aria-labelledby="so-list-actions-title" aria-describedby="so-list-actions-subtitle">
        <div class="so-action-modal__header">
            <div>
                <p class="so-action-modal__eyebrow">Ações do modelo favorito</p>
                <h3 class="so-action-modal__title" id="so-list-actions-title">Modelo favorito</h3>
            </div>
            <button class="so-action-modal__close" type="button" data-so-action-close aria-label="Fechar ações">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="so-action-modal__body">
            <p class="so-action-modal__subtitle" id="so-list-actions-subtitle"></p>
            <div class="so-action-modal__links" data-so-actions-container></div>
        </div>
    </div>
</div>
<script src="assets/js/lista-acoes-modal.js" defer></script>
<?php include 'views/layout/footer.php'; ?>

