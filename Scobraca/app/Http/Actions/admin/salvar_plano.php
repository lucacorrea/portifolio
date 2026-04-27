<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bootstrap/app.php';
require_platform_admin();
verify_csrf();

$nome = trim($_POST['nome'] ?? '');
$preco = str_replace(',', '.', str_replace('.', '', $_POST['preco'] ?? '0'));
$limiteClientes = $_POST['limite_clientes'] !== '' ? (int) $_POST['limite_clientes'] : null;
$limiteUsuarios = $_POST['limite_usuarios'] !== '' ? (int) $_POST['limite_usuarios'] : null;

if ($nome === '') {
    flash('error', 'Informe o nome do plano.');
    redirect('/admin/planos.php');
}

$stmt = db()->prepare(
    "INSERT INTO planos (nome, preco, limite_clientes, limite_usuarios, whatsapp_ativo, leitura_comprovante, relatorios_avancados, ativo, criado_em)
     VALUES (:nome, :preco, :limite_clientes, :limite_usuarios, :whatsapp, :leitura, :relatorios, 1, NOW())"
);
$stmt->execute([
    ':nome' => $nome,
    ':preco' => (float) $preco,
    ':limite_clientes' => $limiteClientes,
    ':limite_usuarios' => $limiteUsuarios,
    ':whatsapp' => isset($_POST['whatsapp_ativo']) ? 1 : 0,
    ':leitura' => isset($_POST['leitura_comprovante']) ? 1 : 0,
    ':relatorios' => isset($_POST['relatorios_avancados']) ? 1 : 0,
]);

flash('success', 'Plano cadastrado com sucesso.');
redirect('/admin/planos.php');
