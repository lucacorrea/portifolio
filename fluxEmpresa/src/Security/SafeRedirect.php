<?php
declare(strict_types=1);

namespace App\Security;

final class SafeRedirect
{
    private const DEFAULT_TARGET = 'dashboard.php';
    private const BASE_PATH = '/YK/';

    private const ALLOWED_TARGETS = [
        'dashboard.php',
        'acesso-negado.php',
        'ordens-servico.php',
        'orcamentos.php',
        'clientes.php',
        'agenda.php',
        'painel-semanal.php',
        'produtos.php',
        'pecas.php',
        'fornecedores.php',
        'servicos.php',
        'funcionarios.php',
        'tecnicos.php',
        'caixa.php',
        'frente-caixa.php',
        'caixa-vendas.php',
        'caixa-movimentacoes.php',
        'contas-receber.php',
        'contas-pagar.php',
        'faturamento.php',
        'relatorios.php',
        'configuracoes.php',
        'configuracoes-fiscais.php',
        'usuarios.php',
        'perfis-acesso.php',
        'perfil-formulario.php',
        'perfil-permissoes.php',
        'ordem-servico-comprovante.php',
        'ordem-servico-imprimir.php',
        'orcamento-imprimir.php',
        'recibo-imprimir.php',
    ];

    public function sanitize(?string $next): string
    {
        $next = trim((string) $next);

        if ($next === '') {
            return self::DEFAULT_TARGET;
        }

        $decoded = rawurldecode($next);
        if (
            preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1
            || str_contains($decoded, '..')
            || str_starts_with($decoded, '/')
            || str_starts_with($decoded, '\\')
            || str_starts_with($decoded, '//')
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $decoded)
        ) {
            return self::DEFAULT_TARGET;
        }

        $target = strtok($decoded, '?') ?: $decoded;
        $fragmentlessTarget = strtok($target, '#') ?: $target;

        if (!in_array($fragmentlessTarget, self::ALLOWED_TARGETS, true)) {
            return self::DEFAULT_TARGET;
        }

        return $decoded;
    }

    public function applicationUrl(?string $target): string
    {
        $safeTarget = $this->sanitize($target);

        return self::BASE_PATH . ltrim($safeTarget, '/');
    }

    public function loginUrl(): string
    {
        return self::BASE_PATH . 'login.php';
    }
}
