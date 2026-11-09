<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $prefixoOs = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim((string) ($_POST['os_prefixo'] ?? 'OS'))) ?: 'OS');
    $prefixoOrcamento = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim((string) ($_POST['orcamento_prefixo'] ?? 'ORC'))) ?: 'ORC');

    $valores = [
        'empresa.nome' => trim((string) ($_POST['empresa_nome'] ?? 'Bianka Oficina Mecânica')),
        'empresa.cnpj' => trim((string) ($_POST['empresa_cnpj'] ?? '')),
        'empresa.telefone' => trim((string) ($_POST['empresa_telefone'] ?? '')),
        'empresa.endereco' => trim((string) ($_POST['empresa_endereco'] ?? '')),
        'empresa.cidade' => trim((string) ($_POST['empresa_cidade'] ?? '')),
        'empresa.uf' => strtoupper(substr(trim((string) ($_POST['empresa_uf'] ?? '')), 0, 2)),
        'empresa.timezone' => 'America/Manaus',
        'os.prefixo' => substr($prefixoOs, 0, 10),
        'orcamento.prefixo' => substr($prefixoOrcamento, 0, 10),
        'os.permitir_desconto' => isset($_POST['permitir_desconto']) ? '1' : '0',
        'estoque.controlar_minimo' => isset($_POST['controlar_estoque']) ? '1' : '0',
        'os.exigir_mecanico' => isset($_POST['exigir_mecanico']) ? '1' : '0',
        'estoque.permitir_negativo' => isset($_POST['estoque_negativo']) ? '1' : '0',
    ];

    if ($valores['empresa.nome'] === '') {
        flash('danger', 'Informe o nome da oficina.');
        redirect('configuracoes.php');
    }

    $tiposBooleanos = ['os.permitir_desconto', 'estoque.controlar_minimo', 'os.exigir_mecanico', 'estoque.permitir_negativo'];
    $stmt = $pdo->prepare('INSERT INTO configuracoes (chave, valor, tipo) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor), tipo = VALUES(tipo), updated_at = CURRENT_TIMESTAMP');

    foreach ($valores as $chave => $valor) {
        $stmt->execute([$chave, $valor, in_array($chave, $tiposBooleanos, true) ? 'boolean' : 'string']);
    }

    audit($pdo, 'configuracoes', null, 'atualizar', null, $valores);
    flash('success', 'Configurações salvas com sucesso.');
    redirect('configuracoes.php');
}

$config = [];
foreach ($pdo->query('SELECT chave, valor FROM configuracoes')->fetchAll() as $linha) {
    $config[$linha['chave']] = $linha['valor'];
}

$pageTitle = 'Configurações';
$currentPage = 'configuracoes';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>

<?= page_header('Configurações', 'Defina os dados da oficina e as principais regras de funcionamento do sistema.') ?>

<form method="post">
    <?= csrf_field() ?>

    <div class="two-col">
        <div class="card section-card">
            <div class="section-title"><div><h2>Dados da oficina</h2><p>Informações usadas no sistema e nos documentos.</p></div></div>
            <div class="form-row">
                <div class="form-group" style="grid-column:1/-1"><label>Nome da oficina</label><input class="input" name="empresa_nome" maxlength="190" required value="<?= h($config['empresa.nome'] ?? 'Bianka Oficina Mecânica') ?>"></div>
                <div class="form-group"><label>CNPJ</label><input class="input" name="empresa_cnpj" maxlength="20" value="<?= h($config['empresa.cnpj'] ?? '') ?>"></div>
                <div class="form-group"><label>Telefone</label><input class="input" name="empresa_telefone" maxlength="30" value="<?= h($config['empresa.telefone'] ?? '') ?>"></div>
                <div class="form-group" style="grid-column:1/-1"><label>Endereço</label><input class="input" name="empresa_endereco" maxlength="255" value="<?= h($config['empresa.endereco'] ?? '') ?>"></div>
                <div class="form-group"><label>Cidade</label><input class="input" name="empresa_cidade" maxlength="120" value="<?= h($config['empresa.cidade'] ?? 'Coari') ?>"></div>
                <div class="form-group"><label>UF</label><input class="input" name="empresa_uf" maxlength="2" value="<?= h($config['empresa.uf'] ?? 'AM') ?>"></div>
            </div>
        </div>

        <div class="card section-card">
            <div class="section-title"><div><h2>Numeração</h2><p>Os próximos registros usarão estes prefixos.</p></div></div>
            <div class="form-row" style="grid-template-columns:1fr">
                <div class="form-group"><label>Prefixo das Ordens de Serviço</label><input class="input" name="os_prefixo" maxlength="10" required value="<?= h($config['os.prefixo'] ?? 'OS') ?>"><span class="muted" style="font-size:12px">Exemplo: OS-<?= date('Y') ?>-00001</span></div>
                <div class="form-group"><label>Prefixo dos Orçamentos</label><input class="input" name="orcamento_prefixo" maxlength="10" required value="<?= h($config['orcamento.prefixo'] ?? 'ORC') ?>"><span class="muted" style="font-size:12px">Exemplo: ORC-<?= date('Y') ?>-00001</span></div>
            </div>
        </div>
    </div>

    <div class="card section-card">
        <div class="section-title"><div><h2>Regras operacionais</h2><p>Estas opções alteram o comportamento real das Ordens de Serviço, Orçamentos e Estoque.</p></div></div>
        <div class="operation-list">
            <label class="operation-item"><div class="operation-copy"><strong>Permitir desconto</strong><span>Habilita desconto em Ordens de Serviço e Orçamentos</span></div><input type="checkbox" name="permitir_desconto" value="1" <?= ($config['os.permitir_desconto'] ?? '1') === '1' ? 'checked' : '' ?>></label>
            <label class="operation-item"><div class="operation-copy"><strong>Controlar estoque mínimo</strong><span>Destaca itens abaixo do mínimo na tela de peças e no Dashboard</span></div><input type="checkbox" name="controlar_estoque" value="1" <?= ($config['estoque.controlar_minimo'] ?? '1') === '1' ? 'checked' : '' ?>></label>
            <label class="operation-item"><div class="operation-copy"><strong>Exigir mecânico responsável na OS</strong><span>Impede criar ou avançar uma OS sem responsável definido</span></div><input type="checkbox" name="exigir_mecanico" value="1" <?= ($config['os.exigir_mecanico'] ?? '0') === '1' ? 'checked' : '' ?>></label>
            <label class="operation-item"><div class="operation-copy"><strong>Permitir estoque negativo</strong><span>Permite saídas e conversões mesmo sem saldo suficiente. Use apenas se necessário.</span></div><input type="checkbox" name="estoque_negativo" value="1" <?= ($config['estoque.permitir_negativo'] ?? '0') === '1' ? 'checked' : '' ?>></label>
        </div>
        <div class="actions" style="margin-top:18px">
            <button class="btn btn-primary" type="submit">Salvar configurações</button>
        </div>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>