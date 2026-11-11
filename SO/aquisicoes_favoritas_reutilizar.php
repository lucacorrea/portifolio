<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
admin_check();

$tipo = is_scalar($_POST['tipo'] ?? $_GET['tipo'] ?? null) ? (string)($_POST['tipo'] ?? $_GET['tipo']) : 'aquisicao';
if (!in_array($tipo, ['oficio', 'aquisicao'], true)) {
    http_response_code(400);
    exit('Tipo de modelo inválido.');
}
$tabela = $tipo === 'oficio' ? 'oficios_favoritos' : 'aquisicoes_favoritas';
$page_title = 'Reutilizar Modelo Favorito';
if (!so_schema_table_exists($pdo, $tabela)) {
    flash_message('danger', 'Instale as tabelas pelo arquivo sql/aquisicoes_favoritas.sql.');
    header('Location: aquisicoes_favoritas.php', true, 303);
    exit;
}
$id = filter_var($_POST['favorita_id'] ?? $_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(400);
    exit('Modelo favorito inválido.');
}
$stmt = $pdo->prepare("SELECT * FROM {$tabela} WHERE id = ? LIMIT 1");
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
    exit('Este favorito está incompleto. Atualize-o a partir do registro original.');
}
$secretariaId = (int)($dados['secretaria_id'] ?? 0);
$stmt = $pdo->prepare('SELECT nome FROM secretarias WHERE id = ? LIMIT 1');
$stmt->execute([$secretariaId]);
$secretariaNome = $stmt->fetchColumn();
if (!$secretariaNome) {
    http_response_code(422);
    exit('A secretaria do modelo não está mais cadastrada.');
}
$fornecedores = $pdo->query('SELECT id, nome FROM fornecedores ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);

function reutilizar_h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function reutilizar_sigla(string $nome): string {
    $nome = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $nome)), 'UTF-8');
    if (preg_match('/(?:-|\/)\s*([A-Z0-9]{2,15})\s*$/u', $nome, $m)) {
        return $m[1];
    }
    $mapa = [
        'CASA CIVIL' => 'SMCC', 'ADMINISTRAÇÃO' => 'SEMAD',
        'FAZENDA' => 'SEMFAZ', 'EDUCAÇÃO' => 'SEMED',
        'SAÚDE' => 'SEMSA', 'CULTURA E TURISMO' => 'SECULT',
        'COMUNICAÇÃO' => 'SEMCOM', 'PLANEJAMENTO' => 'SEMPLAN',
        'OBRAS' => 'SEMOB', 'LIMPEZA PÚBLICA' => 'SEMLIP',
        'ASSISTÊNCIA SOCIAL' => 'SEMAS', 'TERRAS E HABITAÇÃO' => 'SEMTH',
        'MEIO AMBIENTE' => 'SEMMA', 'SEGURANÇA PÚBLICA' => 'SMSPDS',
        'ESPORTE' => 'SEMESP', 'PROCURADORIA' => 'PGM',
        'CONTROLADORIA' => 'CGM', 'CIÊNCIA, TECNOLOGIA' => 'SMCTI',
        'DESENVOLVIMENTO RURAL' => 'SMDRE', 'RELAÇÕES INSTITUCIONAIS' => 'SMRI',
    ];
    foreach ($mapa as $texto => $sigla) {
        if (mb_strpos($nome, $texto, 0, 'UTF-8') !== false) {
            return $sigla;
        }
    }
    $partes = preg_split('/\s+/u', preg_replace('/[^\p{L}\s]+/u', '', $nome));
    $ignorar = ['DE', 'DA', 'DO', 'DAS', 'DOS', 'E', 'A', 'O', 'AS', 'OS', 'MUNICIPAL', 'SECRETARIA'];
    $sigla = '';
    foreach ($partes as $parte) {
        if ($parte !== '' && !in_array($parte, $ignorar, true)) {
            $sigla .= mb_substr($parte, 0, 1, 'UTF-8');
        }
    }
    return $sigla !== '' ? mb_substr($sigla, 0, 15, 'UTF-8') : 'SEC';
}
function reutilizar_sem_oficio_numero(PDO $pdo, string $secretaria): string {
    $sigla = reutilizar_sigla($secretaria);
    $ano = date('Y');
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM oficios WHERE numero = ? OR numero LIKE ?');
    for ($tentativa = 0; $tentativa < 100; $tentativa++) {
        $codigo = '';
        for ($i = 0; $i < 4; $i++) {
            $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }
        $numero = 'SEM OFÍCIO Nº ' . $codigo . '/' . $ano . '/' . $sigla;
        $stmt->execute([$numero, 'SEM OFÍCIO Nº ' . $codigo . '/%']);
        if ((int)$stmt->fetchColumn() === 0) {
            return $numero;
        }
    }
    throw new DomainException('Não foi possível gerar um número único. Tente novamente.');
}
function reutilizar_valor($valor, int $indice): float {
    if (!is_scalar($valor)) {
        throw new DomainException('Valor inválido no item ' . $indice . '.');
    }
    $texto = trim((string)$valor);
    if (preg_match('/^\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?$/', $texto)) {
        $texto = str_replace(',', '.', str_replace('.', '', $texto));
    } elseif (preg_match('/^\d+(?:,\d{1,2})?$/', $texto)) {
        $texto = str_replace(',', '.', $texto);
    } elseif (!preg_match('/^\d+(?:\.\d{1,2})?$/', $texto)) {
        throw new DomainException('Informe um valor válido no item ' . $indice . '.');
    }
    $valorFinal = (float)$texto;
    if (!is_finite($valorFinal) || $valorFinal > 999999999.99) {
        throw new DomainException('Preço do item ' . $indice . ' ultrapassa o limite permitido.');
    }
    return round($valorFinal, 2);
}

if (empty($_SESSION['csrf_aquisicoes_reutilizar'])) {
    $_SESSION['csrf_aquisicoes_reutilizar'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_aquisicoes_reutilizar'];
$origem = (string)($dados['numero_oficio'] ?? $dados['numero_aq'] ?? '');
$semOficio = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (isset($_POST['sem_oficio']) && (string)$_POST['sem_oficio'] === '1')
    : (bool)preg_match('/^SEM OFÍCIO Nº/i', $origem);
$numeroInformado = is_scalar($_POST['numero_oficio'] ?? null) ? (string)$_POST['numero_oficio'] : '';
$local = is_scalar($_POST['local'] ?? null) ? trim((string)$_POST['local']) : trim((string)($dados['local'] ?? ''));
$justificativa = is_scalar($_POST['justificativa'] ?? null)
    ? trim((string)$_POST['justificativa'])
    : trim((string)($dados['justificativa'] ?? ''));
if ($justificativa === '') {
    $justificativa = 'Reutilização dos itens do modelo ' . $favorita['titulo'];
}
$resumo = is_scalar($_POST['resumo_itens'] ?? null)
    ? trim((string)$_POST['resumo_itens']) : trim((string)($dados['resumo_itens'] ?? ''));
$fornecedorSelecionado = filter_var($_POST['fornecedor_id'] ?? $dados['fornecedor_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$itemsForm = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_array($_POST['produtos'] ?? null)) {
    foreach (array_slice(array_values($_POST['produtos']), 0, 201) as $item) {
        if (!is_array($item)) continue;
        $itemsForm[] = [
            'produto' => (string)($item['nome'] ?? ''),
            'quantidade' => (string)($item['qtd'] ?? ''),
            'unidade' => (string)($item['unidade'] ?? ''),
            'valor' => (string)($item['valor'] ?? ''),
        ];
    }
} else {
    foreach ($itens as $item) {
        $itemsForm[] = [
            'produto' => (string)($item['produto'] ?? ''),
            'quantidade' => number_format((float)($item['quantidade'] ?? 1), 2, '.', ''),
            'unidade' => (string)($item['unidade'] ?? 'UN'),
            'valor' => number_format((float)($item['valor_unitario'] ?? 0), 2, ',', '.'),
        ];
    }
}
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = is_scalar($_POST['csrf_token'] ?? null) ? (string)$_POST['csrf_token'] : '';
        if ($token === '' || !hash_equals($csrf, $token)) {
            throw new DomainException('Sessão expirada. Atualize a página e tente novamente.');
        }
        if (!$semOficio) {
            $numeroInformado = mb_strtoupper(trim($numeroInformado), 'UTF-8');
            if ($numeroInformado === '') {
                throw new DomainException('Informe o número do ofício ou marque Sem ofício.');
            }
            if (mb_strlen($numeroInformado, 'UTF-8') > 50) {
                throw new DomainException('O número do ofício deve ter no máximo 50 caracteres.');
            }
        }
        if ($local === '' || mb_strlen($local, 'UTF-8') > 150) {
            throw new DomainException('Informe o local da nova solicitação (até 150 caracteres).');
        }
        if ($justificativa === '') {
            throw new DomainException('Informe a justificativa da solicitação.');
        }
        if (mb_strlen($resumo, 'UTF-8') > 10000) {
            throw new DomainException('O resumo dos itens está muito longo.');
        }
        $fornecedorId = (int)$fornecedorSelecionado;
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM fornecedores WHERE id = ?');
        $stmt->execute([$fornecedorId]);
        if (!$fornecedorId || !(int)$stmt->fetchColumn()) {
            throw new DomainException('Selecione um fornecedor válido.');
        }
        $dataDevice = is_scalar($_POST['criado_em_device'] ?? null) ? trim((string)$_POST['criado_em_device']) : '';
        $data = DateTime::createFromFormat('!Y-m-d H:i:s', $dataDevice);
        if (!$data || $data->format('Y-m-d H:i:s') !== $dataDevice) {
            throw new DomainException('Não foi possível validar a data e hora do dispositivo. Atualize a página.');
        }
        $produtos = $_POST['produtos'] ?? null;
        if (!is_array($produtos) || count($produtos) < 1 || count($produtos) > 200) {
            throw new DomainException('A solicitação deve conter entre 1 e 200 itens.');
        }
        $novosItens = [];
        $total = 0.0;
        $comPrecos = true;
        foreach (array_values($produtos) as $indice => $item) {
            $pos = $indice + 1;
            if (!is_array($item)) {
                throw new DomainException('Dados inválidos no item ' . $pos . '.');
            }
            $nome = is_scalar($item['nome'] ?? null) ? trim((string)$item['nome']) : '';
            $unidade = is_scalar($item['unidade'] ?? null) ? mb_strtoupper(trim((string)$item['unidade']), 'UTF-8') : '';
            $quantidadeInput = is_scalar($item['qtd'] ?? null) ? trim((string)$item['qtd']) : '';
            if ($nome === '' || mb_strlen($nome, 'UTF-8') > 255) {
                throw new DomainException('Confira o nome do item ' . $pos . ' (até 255 caracteres).');
            }
            if ($unidade === '' || mb_strlen($unidade, 'UTF-8') > 20) {
                throw new DomainException('Informe uma unidade válida no item ' . $pos . '.');
            }
            if (!preg_match('/^\d{1,8}(?:[.,]\d{1,2})?$/', $quantidadeInput)) {
                throw new DomainException('Informe uma quantidade válida no item ' . $pos . '.');
            }
            $quantidade = round((float)str_replace(',', '.', $quantidadeInput), 2);
            if ($quantidade <= 0) {
                throw new DomainException('A quantidade do item ' . $pos . ' deve ser maior que zero.');
            }
            $preco = reutilizar_valor($item['valor'] ?? null, $pos);
            if ($preco <= 0) $comPrecos = false;
            $novosItens[] = [$nome, $quantidade, $unidade, $preco];
            $total += $quantidade * $preco;
        }
        if (!is_finite($total) || $total > 9999999999999.99) {
            throw new DomainException('O total da solicitação ultrapassa o limite permitido.');
        }

        $pdo->beginTransaction();
        $numero = $semOficio
            ? reutilizar_sem_oficio_numero($pdo, (string)$secretariaNome)
            : $numeroInformado;
        $stmt = $pdo->prepare('SELECT id FROM oficios WHERE numero = ? LIMIT 1');
        $stmt->execute([$numero]);
        if ($stmt->fetchColumn()) {
            throw new DomainException('Este número de ofício já existe. Informe outro ou marque Sem ofício.');
        }
        $status = $comPrecos ? 'ENVIADO' : 'PENDENTE_ITENS';
        $stmt = $pdo->prepare("
            INSERT INTO oficios
                (numero, secretaria_id, local, justificativa, resumo_itens,
                 usuario_id, fornecedor_indicado_id, valor_orcamento, status, criado_em)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $numero, $secretariaId, $local, $justificativa, $resumo !== '' ? $resumo : null,
            (int)$_SESSION['user_id'], $fornecedorId,
            $total > 0 ? round($total, 2) : null, $status, $dataDevice,
        ]);
        $oficioId = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare('INSERT INTO itens_oficio (oficio_id, produto, quantidade, unidade, valor_unitario) VALUES (?, ?, ?, ?, ?)');
        foreach ($novosItens as $item) {
            $stmt->execute([$oficioId, ...$item]);
        }
        log_action($pdo, 'REUTILIZAR_MODELO_FAVORITO',
            'Nova solicitação ' . $numero . ' criada a partir de ' . $favorita['titulo'] . ' (' . count($novosItens) . ' itens).');
        $pdo->commit();
        $_SESSION['csrf_aquisicoes_reutilizar'] = bin2hex(random_bytes(32));
        flash_message('success', 'Solicitação ' . $numero . ' criada com os preços conferidos. Status: ' . $status . '.');
        header('Location: oficios_visualizar.php?id=' . $oficioId, true, 303);
        exit;
    } catch (DomainException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $erro = $e->getMessage();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('SO reutilizar favorita: ' . $e->getMessage());
        $erro = 'Não foi possível salvar a solicitação. Confira os dados e tente novamente.';
    }
}
include 'views/layout/header.php';
?>
<link rel="stylesheet" href="assets/css/aquisicoes-favoritas-reutilizar.css?v=20261111">
<div class="card">
    <div class="card-body">
        <div class="fav-reuse-header">
            <h3><i class="fas fa-edit" style="color:var(--primary);margin-right:10px" aria-hidden="true"></i> Conferir itens — <?= reutilizar_h($favorita['titulo']) ?></h3>
            <a href="aquisicoes_favoritas.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left" aria-hidden="true"></i> Voltar</a>
        </div>
        <div class="fav-reuse-progress">
            <span class="active">1. Dados da solicitação</span><i class="fas fa-chevron-right" aria-hidden="true"></i>
            <span class="active">2. Conferência de itens e preços</span><i class="fas fa-chevron-right" aria-hidden="true"></i>
            <span>3. Aprovação</span>
        </div>
        <?php if ($erro !== ''): ?><div class="alert alert-danger"><?= reutilizar_h($erro) ?></div><?php endif; ?>
        <div class="fav-reuse-device">
            <strong>Data/hora do dispositivo:</strong> <span id="fav-datahora-dispositivo">Carregando...</span>
        </div>
        <form method="post" id="fav-reuse-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= reutilizar_h($csrf) ?>">
            <input type="hidden" name="favorita_id" value="<?= (int)$id ?>">
            <input type="hidden" name="tipo" value="<?= reutilizar_h($tipo) ?>">
            <input type="hidden" name="criado_em_device" id="fav-criado-em-device">
            <div class="fav-reuse-fields">
                <div class="form-group">
                    <label class="form-label" for="fav-numero-oficio">Número do Ofício <span style="color:#dc2626">*</span></label>
                    <input class="form-control" id="fav-numero-oficio" name="numero_oficio" type="text" maxlength="50"
                           placeholder="Ex: OF-2026-01" value="<?= reutilizar_h($semOficio ? '' : $numeroInformado) ?>"
                           data-sigla="<?= reutilizar_h(reutilizar_sigla((string)$secretariaNome)) ?>">
                    <label class="fav-reuse-sem-option" for="fav-sem-oficio">
                        <input type="checkbox" id="fav-sem-oficio" name="sem_oficio" value="1" <?= $semOficio ? 'checked' : '' ?>>
                        Sem ofício
                    </label>
                    <small class="fav-reuse-hint">Com Sem ofício, o sistema gera automaticamente um código único com o ano e a sigla da secretaria.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Secretaria Solicitante</label>
                    <div class="form-control" style="height:auto;min-height:42px;padding:11px 12px"><?= reutilizar_h($secretariaNome) ?></div>
                    <small>Preenchida a partir do favorito original.</small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="fav-local">Local <span style="color:#dc2626">*</span></label>
                    <input class="form-control" id="fav-local" name="local" type="text" maxlength="150" required
                           placeholder="Ex: Almoxarifado Central" value="<?= reutilizar_h($local) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="fav-fornecedor">Fornecedor <span style="color:#dc2626">*</span></label>
                    <select class="form-control" id="fav-fornecedor" name="fornecedor_id" required>
                        <option value="">Selecione o fornecedor...</option>
                        <?php foreach ($fornecedores as $fornecedor): ?>
                            <option value="<?= (int)$fornecedor['id'] ?>" <?= $fornecedorSelecionado === (int)$fornecedor['id'] ? 'selected' : '' ?>><?= reutilizar_h($fornecedor['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small>Fornecedor do modelo preenchido automaticamente; confirme antes de salvar.</small>
                </div>
                <div class="form-group full">
                    <label class="form-label" for="fav-resumo">Resumo dos Itens a Cadastrar</label>
                    <textarea class="form-control" name="resumo_itens" id="fav-resumo" rows="2" placeholder="Descrição geral dos itens e observações..."><?= reutilizar_h($resumo) ?></textarea>
                    <small class="fav-reuse-reference">Modelo de origem: <?= reutilizar_h($origem) ?></small>
                </div>
                <div class="form-group full">
                    <label class="form-label" for="fav-justificativa">Justificativa / Finalidade <span style="color:#dc2626">*</span></label>
                    <textarea class="form-control" name="justificativa" id="fav-justificativa" rows="3" required><?= reutilizar_h($justificativa) ?></textarea>
                    <small>Os textos vieram do favorito e podem ser corrigidos para a nova solicitação.</small>
                </div>
            </div>

            <div class="fav-reuse-budget">
                <div>
                    <strong>Secretaria:</strong> <?= reutilizar_h($secretariaNome) ?>
                    <div class="fav-reuse-original">Origem: <?= reutilizar_h($origem) ?></div>
                    <small class="text-muted">Confira os valores unitários abaixo antes de continuar.</small>
                </div>
                <div>
                    <span class="total-label">Total Atual dos Itens:</span>
                    <span id="fav-total-itens">R$ 0,00</span>
                </div>
            </div>
            <div class="fav-reuse-help">
                <i class="fas fa-info-circle" aria-hidden="true"></i>
                Itens carregados do modelo favorito. Revise descrição, quantidade, unidade e preço; o total é recalculado automaticamente.
                Se algum item ficar sem valor, a solicitação será salva como <strong>PENDENTE_ITENS</strong> para conferência posterior.
            </div>
            <div class="fav-reuse-items-head">
                <div>
                    <h4><i class="fas fa-box-open" aria-hidden="true"></i> Itens e preços</h4>
                    <small>Mesmo formato da atribuição de itens do SO.</small>
                </div>
            </div>
            <div class="fav-reuse-count">
                <div class="form-group">
                    <label class="form-label" for="fav-count-input">Total de itens</label>
                    <input class="form-control" type="number" id="fav-count-input" min="1" max="200" step="1" value="<?= count($itemsForm) ?>">
                </div>
                <button class="btn btn-primary" type="button" id="fav-generate-items"><i class="fas fa-list-ol" aria-hidden="true"></i> Gerar campos</button>
            </div>
            <div class="fav-reuse-rows">
                <div id="fav-items-container">
                    <?php foreach ($itemsForm as $idx => $item): ?>
                        <div class="fav-reuse-row">
                            <div class="form-group">
                                <label class="form-label">Nº</label>
                                <input class="form-control fav-item-index" type="text" value="<?= $idx + 1 ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Nome do Item</label>
                                <input class="form-control fav-item-name" name="produtos[<?= $idx ?>][nome]" data-field="nome" maxlength="255" required
                                       value="<?= reutilizar_h($item['produto']) ?>" placeholder="Ex: Papel A4">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Quantidade</label>
                                <input class="form-control fav-item-qtd" name="produtos[<?= $idx ?>][qtd]" data-field="qtd" type="number" min="0.01" step="0.01" required
                                       value="<?= reutilizar_h($item['quantidade']) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Unidade</label>
                                <input class="form-control fav-item-unit" name="produtos[<?= $idx ?>][unidade]" data-field="unidade" maxlength="20" required
                                       value="<?= reutilizar_h($item['unidade']) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Valor Unitário</label>
                                <input class="form-control fav-item-price" name="produtos[<?= $idx ?>][valor]" data-field="valor" inputmode="decimal" required
                                       placeholder="0,00" value="<?= reutilizar_h($item['valor']) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Valor Total</label>
                                <input class="form-control fav-item-total" type="text" inputmode="decimal" title="Digite o total para recalcular o valor unitário" placeholder="0,00">
                            </div>
                            <div>
                                <button type="button" class="btn btn-outline btn-sm fav-remove-item" title="Remover item" aria-label="Remover item">
                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <button class="btn btn-outline fav-reuse-more" type="button" id="fav-add-item">
                <i class="fas fa-plus" aria-hidden="true"></i> Adicionar Mais Itens
            </button>
            <div class="fav-reuse-actions">
                <a class="btn btn-outline" href="aquisicoes_favoritas.php">Cancelar</a>
                <button class="btn btn-primary" type="submit" id="fav-submit">
                    <i class="fas fa-check-double" aria-hidden="true"></i> Criar solicitação com preços conferidos
                </button>
            </div>
        </form>
    </div>
</div>
<script src="assets/js/aquisicoes-favoritas-reutilizar.js?v=20261111" defer></script>
<?php include 'views/layout/footer.php'; ?>
