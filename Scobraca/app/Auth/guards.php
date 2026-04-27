<?php

declare(strict_types=1);

function require_login(): void
{
    if (empty($_SESSION['usuario'])) {
        redirect('/login.php');
    }
}

function require_platform_admin(): void
{
    require_login();

    if (($_SESSION['usuario']['tipo'] ?? '') !== 'platform_admin') {
        http_response_code(403);
        exit('Acesso negado. Área restrita ao administrador da plataforma.');
    }
}

function require_tenant_user(): void
{
    require_login();

    $tipo = $_SESSION['usuario']['tipo'] ?? '';
    $empresaId = $_SESSION['usuario']['empresa_id'] ?? null;

    if (!in_array($tipo, ['empresa_admin', 'operador'], true) || empty($empresaId)) {
        http_response_code(403);
        exit('Acesso negado. Área restrita à empresa contratante.');
    }

    validar_empresa_ativa((int) $empresaId);
}

function validar_empresa_ativa(int $empresaId): void
{
    $stmt = db()->prepare(
        "SELECT e.status AS empresa_status,
                a.status AS assinatura_status,
                a.data_vencimento
         FROM empresas e
         LEFT JOIN assinaturas a ON a.empresa_id = e.id
         WHERE e.id = :empresa_id
         ORDER BY a.id DESC
         LIMIT 1"
    );
    $stmt->execute([':empresa_id' => $empresaId]);
    $dados = $stmt->fetch();

    if (!$dados) {
        http_response_code(403);
        exit('Empresa não encontrada.');
    }

    if ($dados['empresa_status'] !== 'ativa' && $dados['empresa_status'] !== 'teste') {
        redirect('/app/assinatura-bloqueada.php');
    }

    if (!empty($dados['data_vencimento']) && $dados['data_vencimento'] < date('Y-m-d')) {
        redirect('/app/assinatura-bloqueada.php');
    }
}
