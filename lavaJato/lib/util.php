<?php
declare(strict_types=1);

// autoErp/lib/util.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Exige método POST.
 */
function require_post(): void
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method !== 'POST') {
        http_response_code(405);
        exit('Método não permitido.');
    }
}

/**
 * Exige método GET.
 */
function require_get(): void
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method !== 'GET') {
        http_response_code(405);
        exit('Método não permitido.');
    }
}

/**
 * Escape para HTML.
 */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Redireciona e encerra.
 */
function redirect_to(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Retorna o nome da empresa da sessão ou do banco.
 *
 * @param PDO|null $pdo Opcional: conexão PDO para buscar no banco se não estiver em sessão.
 * @return string Nome fantasia da empresa ou "Empresa não definida".
 */
function empresa_nome_logada(?PDO $pdo = null): string
{
    // 1. Primeiro tenta da sessão
    if (!empty($_SESSION['empresa_nome'])) {
        return (string)$_SESSION['empresa_nome'];
    }

    // 2. Tenta buscar pelo CNPJ salvo na sessão
    $cnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));

    if ($pdo instanceof PDO && preg_match('/^\d{14}$/', $cnpj)) {
        try {
            $st = $pdo->prepare("
                SELECT nome_fantasia
                FROM empresas_peca
                WHERE cnpj = :c
                LIMIT 1
            ");
            $st->execute([':c' => $cnpj]);

            $row = $st->fetch(PDO::FETCH_ASSOC);

            if ($row && !empty($row['nome_fantasia'])) {
                $_SESSION['empresa_nome'] = (string)$row['nome_fantasia'];
                return (string)$row['nome_fantasia'];
            }
        } catch (Throwable $e) {
            error_log('empresa_nome_logada erro: ' . $e->getMessage());
        }
    }

    return 'Empresa não definida';
}