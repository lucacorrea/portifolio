<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_tenant_user();

$pageTitle = 'Configurações';
$pageDescription = 'Preferências de cobrança, PIX e automação da empresa.';
$empresaId = current_empresa_id();

$stmt = db()->prepare('SELECT * FROM configuracoes_automacao WHERE empresa_id = :empresa_id LIMIT 1');
$stmt->execute([':empresa_id' => $empresaId]);
$config = $stmt->fetch() ?: [];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/app.css')) ?>">
</head>
<body>
<div class="layout">
    <?php require APP_PATH . '/Includes/tenant_sidebar.php'; ?>
    <main class="content">
        <?php require APP_PATH . '/Includes/topbar.php'; ?>
        <?php require APP_PATH . '/Includes/flash.php'; ?>

        <section class="card">
            <div class="section-heading">
                <div>
                    <h2>Automação de cobrança</h2>
                    <p class="muted">Configurações usadas para mensagens, bloqueio e dados de recebimento.</p>
                </div>
                <span class="soft-label <?= (int) ($config['automacao_ativa'] ?? 1) === 1 ? 'success' : 'warning' ?>"><?= (int) ($config['automacao_ativa'] ?? 1) === 1 ? 'Ativa' : 'Pausada' ?></span>
            </div>
            <form class="form-grid" method="post" action="<?= e(public_url('/actions/app/salvar_configuracoes.php')) ?>">
                <?= csrf_field() ?>
                <label>Nome da empresa<input name="empresa_nome" value="<?= e($config['empresa_nome'] ?? '') ?>" placeholder="Nome exibido nas mensagens"></label>
                <label>CNPJ<input name="empresa_cnpj" value="<?= e($config['empresa_cnpj'] ?? '') ?>" placeholder="00.000.000/0000-00"></label>
                <label>Dia padrão de vencimento<input type="number" name="dia_vencimento_padrao" min="1" max="31" value="<?= e((string) ($config['dia_vencimento_padrao'] ?? 10)) ?>"></label>
                <label>Bloquear após dias<input type="number" name="bloquear_apos_dias" min="0" value="<?= e((string) ($config['bloquear_apos_dias'] ?? 7)) ?>"></label>
                <label class="check"><input type="checkbox" name="automacao_ativa" value="1" <?= (int) ($config['automacao_ativa'] ?? 1) === 1 ? 'checked' : '' ?>> Automação ativa</label>
                <label>Nome do recebedor PIX<input name="pix_nome_recebedor" value="<?= e($config['pix_nome_recebedor'] ?? '') ?>"></label>
                <label>Tipo da chave PIX
                    <select name="pix_tipo_chave">
                        <?php $tipoPix = (string) ($config['pix_tipo_chave'] ?? 'CPF/CNPJ'); ?>
                        <?php foreach (['CPF/CNPJ', 'E-mail', 'Telefone', 'Aleatória'] as $tipo): ?>
                            <option value="<?= e($tipo) ?>" <?= $tipoPix === $tipo ? 'selected' : '' ?>><?= e($tipo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Chave PIX<input name="pix_chave" value="<?= e($config['pix_chave'] ?? '') ?>"></label>
                <label>Status após atraso
                    <select name="status_cliente_apos_atraso">
                        <?php $statusAtraso = (string) ($config['status_cliente_apos_atraso'] ?? 'bloqueado'); ?>
                        <option value="bloqueado" <?= $statusAtraso === 'bloqueado' ? 'selected' : '' ?>>Bloqueado</option>
                        <option value="ativo" <?= $statusAtraso === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                    </select>
                </label>
                <label>Mensagem 10 dias antes<textarea name="mensagem_10_dias" rows="3"><?= e($config['mensagem_10_dias'] ?? 'Olá, sua mensalidade vence em breve.') ?></textarea></label>
                <label>Mensagem 5 dias antes<textarea name="mensagem_5_dias" rows="3"><?= e($config['mensagem_5_dias'] ?? 'Olá, sua mensalidade vence em poucos dias.') ?></textarea></label>
                <label>Mensagem no vencimento<textarea name="mensagem_dia_vencimento" rows="3"><?= e($config['mensagem_dia_vencimento'] ?? 'Hoje é o vencimento da sua mensalidade.') ?></textarea></label>
                <label>Mensagem após atraso<textarea name="mensagem_7_dias_atraso" rows="3"><?= e($config['mensagem_7_dias_atraso'] ?? 'Identificamos uma pendência em aberto.') ?></textarea></label>
                <label>Nova chave Gemini
                    <input type="password" name="gemini_api_key" autocomplete="new-password" placeholder="<?= !empty($config['gemini_api_key']) ? 'Chave configurada. Preencha apenas para trocar.' : 'Cole a chave para leitura de comprovantes' ?>">
                </label>
                <label class="check"><input type="checkbox" name="limpar_gemini_api_key" value="1"> Remover chave Gemini salva</label>
                <div class="form-actions"><button type="submit" class="btn btn-primary">Salvar configurações</button></div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
