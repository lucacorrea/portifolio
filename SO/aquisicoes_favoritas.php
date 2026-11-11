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
$secretariaFavoritas = is_scalar($_GET['secretaria'] ?? null) ? trim((string)$_GET['secretaria']) : '';
$fornecedorFavoritas = is_scalar($_GET['fornecedor'] ?? null) ? trim((string)$_GET['fornecedor']) : '';
$dataInicioFavoritas = is_scalar($_GET['data_inicio'] ?? null) ? (string)$_GET['data_inicio'] : '';
$dataFimFavoritas = is_scalar($_GET['data_fim'] ?? null) ? (string)$_GET['data_fim'] : '';
foreach (['dataInicioFavoritas', 'dataFimFavoritas'] as $campoData) {
    $valor = $$campoData;
    $data = preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) ? DateTime::createFromFormat('!Y-m-d', $valor) : false;
    if (!$data || $data->format('Y-m-d') !== $valor) {
        $$campoData = '';
    }
}
$porPaginaOptionsFavoritas = [6, 10, 25, 50, 100];
$porPaginaRequestFavoritas = is_scalar($_GET['por_pagina'] ?? null) ? (string)$_GET['por_pagina'] : '';
$porPaginaFavoritas = ctype_digit($porPaginaRequestFavoritas) && in_array((int)$porPaginaRequestFavoritas, $porPaginaOptionsFavoritas, true)
    ? (int)$porPaginaRequestFavoritas : 6;
// As opções dos filtros são extraídas dos modelos já existentes.
$secretariasFavoritas = [];
$fornecedoresFavoritas = [];
foreach ($linhasFavoritas as $fav) {
    $secretaria = trim((string)$fav['secretaria_nome']);
    $fornecedor = trim((string)$fav['fornecedor_nome']);
    if ($secretaria !== '' && $secretaria !== '-') {
        $secretariasFavoritas[$secretaria] = $secretaria;
    }
    if ($fornecedor !== '' && $fornecedor !== '-') {
        $fornecedoresFavoritas[$fornecedor] = $fornecedor;
    }
}
natcasesort($secretariasFavoritas);
natcasesort($fornecedoresFavoritas);
$encontradasFavoritas = array_values(array_filter($linhasFavoritas, static function (array $fav) use (
    $buscaFavoritas, $tipoFavoritas, $secretariaFavoritas, $fornecedorFavoritas, $dataInicioFavoritas, $dataFimFavoritas
): bool {
    if ($tipoFavoritas !== '' && $fav['tipo'] !== $tipoFavoritas) {
        return false;
    }
    if ($secretariaFavoritas !== '' && $fav['secretaria_nome'] !== $secretariaFavoritas) {
        return false;
    }
    if ($fornecedorFavoritas !== '' && $fav['fornecedor_nome'] !== $fornecedorFavoritas) {
        return false;
    }
    $dia = substr((string)$fav['atualizado_em'], 0, 10);
    if ($dataInicioFavoritas !== '' && $dia < $dataInicioFavoritas) {
        return false;
    }
    if ($dataFimFavoritas !== '' && $dia > $dataFimFavoritas) {
        return false;
    }
    if ($buscaFavoritas === '') {
        return true;
    }
    $texto = implode(' ', [
        $fav['titulo'], $fav['numero_origem'], $fav['secretaria_nome'],
        $fav['fornecedor_nome'], $fav['local_nome'], (string)$fav['valor_total'],
        number_format((float)$fav['valor_total'], 2, ',', '.')
    ]);
    return function_exists('mb_stripos')
        ? mb_stripos($texto, $buscaFavoritas, 0, 'UTF-8') !== false
        : stripos($texto, $buscaFavoritas) !== false;
}));
$totalFavoritas = count($encontradasFavoritas);
$totalPaginasFavoritas = max(1, (int)ceil($totalFavoritas / $porPaginaFavoritas));
$paginaFavoritas = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$paginaFavoritas = min($paginaFavoritas, $totalPaginasFavoritas);
$exibidasFavoritas = array_slice($encontradasFavoritas, ($paginaFavoritas - 1) * $porPaginaFavoritas, $porPaginaFavoritas);
function favoritas_pagina_url(int $pagina): string {
    $filtros = $_GET;
    unset($filtros['page']);
    $filtros['page'] = $pagina;
    return 'aquisicoes_favoritas.php?' . http_build_query($filtros);
}
include 'views/layout/header.php';
?>
<link rel="stylesheet" href="assets/css/lista-acoes-modal.css">
<div class="card no-print lista-filtros-card favoritas-filtros">
    <div class="card-body">
        <div class="lista-filtros-header">
            <div class="lista-filtros-titulo-wrap">
                <span class="lista-filtros-icone"><i class="fas fa-filter" aria-hidden="true"></i></span>
                <div>
                    <h3 class="lista-filtros-titulo">Filtros de Busca</h3>
                    <p class="lista-filtros-subtitulo">Localize modelos favoritos por dados, período ou tipo.</p>
                </div>
            </div>
            <span class="lista-filtros-resumo"><i class="fas fa-sliders-h" aria-hidden="true"></i> Refine a listagem</span>
        </div>
        <form method="get" action="aquisicoes_favoritas.php" class="lista-filtros-form lista-filtros-form--oficios">
            <div class="lista-filtros-principais">
                <div class="lista-filtro lista-filtro-busca">
                    <label for="filtro-favorita-busca">Termo de busca</label>
                    <div class="lista-filtro-input-icon">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <input type="search" id="filtro-favorita-busca" name="busca" class="form-control"
                               placeholder="Número, secretaria, fornecedor ou valor..."
                               value="<?= favoritas_h($buscaFavoritas) ?>">
                    </div>
                </div>
                <div class="lista-filtro">
                    <label for="filtro-favorita-tipo">Tipo</label>
                    <select id="filtro-favorita-tipo" name="tipo" class="form-control">
                        <option value="" <?= $tipoFavoritas === '' ? 'selected' : '' ?>>Todos os tipos</option>
                        <option value="oficio" <?= $tipoFavoritas === 'oficio' ? 'selected' : '' ?>>Ofícios</option>
                        <option value="aquisicao" <?= $tipoFavoritas === 'aquisicao' ? 'selected' : '' ?>>Aquisições</option>
                    </select>
                </div>
                <div class="lista-filtro">
                    <label for="filtro-favorita-secretaria">Secretaria</label>
                    <select id="filtro-favorita-secretaria" name="secretaria" class="form-control">
                        <option value="">Todas as Secretarias</option>
                        <?php foreach ($secretariasFavoritas as $nome): ?>
                            <option value="<?= favoritas_h($nome) ?>" <?= $secretariaFavoritas === $nome ? 'selected' : '' ?>><?= favoritas_h($nome) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="lista-filtro lista-filtro-fornecedor">
                    <label for="filtro-favorita-fornecedor">Fornecedor</label>
                    <select id="filtro-favorita-fornecedor" name="fornecedor" class="form-control">
                        <option value="">Todos os Fornecedores</option>
                        <?php foreach ($fornecedoresFavoritas as $nome): ?>
                            <option value="<?= favoritas_h($nome) ?>" <?= $fornecedorFavoritas === $nome ? 'selected' : '' ?>><?= favoritas_h($nome) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="lista-filtros-secundarios">
                <div class="lista-filtros-secundarios-campos">
                    <div class="lista-filtro">
                        <label for="filtro-favorita-inicio">Data inicial</label>
                        <input type="date" id="filtro-favorita-inicio" name="data_inicio" class="form-control"
                               value="<?= favoritas_h($dataInicioFavoritas) ?>">
                    </div>
                    <div class="lista-filtro">
                        <label for="filtro-favorita-fim">Data final</label>
                        <input type="date" id="filtro-favorita-fim" name="data_fim" class="form-control"
                               value="<?= favoritas_h($dataFimFavoritas) ?>">
                    </div>
                    <div class="lista-filtro">
                        <label for="filtro-favorita-linhas">Linhas por página</label>
                        <select id="filtro-favorita-linhas" name="por_pagina" class="form-control">
                            <?php foreach ($porPaginaOptionsFavoritas as $qtd): ?>
                                <option value="<?= $qtd ?>" <?= $porPaginaFavoritas === $qtd ? 'selected' : '' ?>><?= $qtd ?> linhas</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="lista-filtros-acoes">
                    <button class="filtro-btn filtro-btn-primary" type="submit" title="Aplicar filtros">
                        <i class="fas fa-search" aria-hidden="true"></i> Filtrar
                    </button>
                    <a class="filtro-btn filtro-btn-clear" href="aquisicoes_favoritas.php" title="Limpar filtros">
                        <i class="fas fa-eraser" aria-hidden="true"></i> Limpar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <div class="favoritas-lista-header">
            <h3><i class="fas fa-star" style="color:var(--primary);margin-right:10px" aria-hidden="true"></i> Favoritos Salvos</h3>
            <div class="favoritas-lista-header__acoes">
                <a class="btn btn-primary btn-sm" href="oficios_lista.php"><i class="fas fa-folder-open" aria-hidden="true"></i> Solicitações</a>
                <a class="btn btn-outline btn-sm" href="aquisicoes_lista.php"><i class="fas fa-shopping-bag" aria-hidden="true"></i> Aquisições</a>
            </div>
        </div>
        <?php display_flash(); ?>
        <?php if (!$tabelaFavoritasExiste && !$tabelaOficiosFavoritosExiste): ?>
            <div class="alert alert-warning">As tabelas de favoritas ainda não estão instaladas. Execute o arquivo
                <strong>SO/sql/aquisicoes_favoritas.sql</strong> no phpMyAdmin.</div>
        <?php else: ?>
            <p class="so-list-tip">
                <i class="fas fa-mouse-pointer" aria-hidden="true"></i>
                Clique em uma linha para visualizar os detalhes e as ações disponíveis.
            </p>
            <div class="table-responsive lista-table-wrap">
                <table class="table-vcenter text-nowrap lista-table favoritas-lista-table">
                    <thead>
                        <tr>
                            <th>Número / Modelo</th>
                            <th>Secretaria</th>
                            <th>Fornecedor</th>
                            <th>Data</th>
                            <th style="text-align:right">Valor</th>
                            <th>Tipo</th>
                            <th>Itens</th>
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
                                title="Clique para ver as ações deste favorito">
                                <td>
                                    <strong class="favoritas-numero" title="<?= favoritas_h($fav['titulo']) ?>">
                                        <?= favoritas_h($fav['titulo']) ?>
                                    </strong>
                                </td>
                                <td><span class="favoritas-secretaria" title="<?= favoritas_h($fav['secretaria_nome']) ?>"><?= favoritas_h($fav['secretaria_nome']) ?></span></td>
                                <td><span class="favoritas-fornecedor" title="<?= favoritas_h($fav['fornecedor_nome']) ?>"><?= favoritas_h($fav['fornecedor_nome']) ?></span></td>
                                <td class="favoritas-data"><?= $dataSalva ? favoritas_h(date('d/m/Y H:i', $dataSalva)) : '-' ?></td>
                                <td class="favoritas-valor"><?= format_money($fav['valor_total']) ?></td>
                                <td><span class="favoritas-tipo <?= $tipo === 'oficio' ? 'favoritas-tipo--oficio' : '' ?>">
                                    <?= $tipo === 'oficio' ? 'Ofício' : 'Aquisição' ?>
                                </span></td>
                                <td><strong><?= (int)$fav['quantidade_itens'] ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$exibidasFavoritas): ?>
                            <tr>
                                <td colspan="7" class="favoritas-lista-vazia">
                                    <?= $buscaFavoritas !== '' || $tipoFavoritas !== '' || $secretariaFavoritas !== '' || $fornecedorFavoritas !== '' || $dataInicioFavoritas !== '' || $dataFimFavoritas !== ''
                                        ? 'Nenhum favorito encontrado com os filtros selecionados.'
                                        : 'Ainda não há modelos favoritos. Salve um ofício ou aquisição na respectiva listagem.' ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPaginasFavoritas > 1): ?>
                <div class="favoritas-paginacao">
                    <a class="btn btn-outline btn-sm <?= $paginaFavoritas === 1 ? 'disabled' : '' ?>"
                       href="<?= favoritas_h(favoritas_pagina_url(max(1, $paginaFavoritas - 1))) ?>"
                       <?= $paginaFavoritas === 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                        <i class="fas fa-angle-left" aria-hidden="true"></i> Anterior
                    </a>
                    <span class="favoritas-paginacao-info">Página <?= $paginaFavoritas ?> de <?= $totalPaginasFavoritas ?></span>
                    <a class="btn btn-outline btn-sm <?= $paginaFavoritas === $totalPaginasFavoritas ? 'disabled' : '' ?>"
                       href="<?= favoritas_h(favoritas_pagina_url(min($totalPaginasFavoritas, $paginaFavoritas + 1))) ?>"
                       <?= $paginaFavoritas === $totalPaginasFavoritas ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                        Próxima <i class="fas fa-angle-right" aria-hidden="true"></i>
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
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
