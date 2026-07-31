<?php

declare(strict_types=1);

require __DIR__ . '/action-common.php';

admin_post();

$companyId = null;

try {
    /*
     * O ID recebido pelo formulário serve apenas para localizar
     * novamente a empresa no banco. Nenhum dado da empresa enviado
     * pelo navegador será considerado confiável.
     */
    $validatedId = filter_var(
        $_POST['id'] ?? null,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
            ],
        ]
    );

    if ($validatedId === false) {
        throw new InvalidArgumentException(
            'Empresa inválida.'
        );
    }

    $companyId = (int) $validatedId;

    $company = $application
        ->adminCompanies()
        ->find($companyId);

    if ($company === null) {
        throw new InvalidArgumentException(
            'Empresa não encontrada.'
        );
    }

    /*
     * O status é lido diretamente do banco.
     *
     * O suporte pode acessar empresas:
     * - ativas;
     * - pendentes, para configuração;
     * - inativas, para manutenção.
     *
     * Empresas bloqueadas continuam protegidas.
     */
    $companyStatus = (string) (
        $company['status']
        ?? ''
    );

    $allowedStatuses = [
        'ativo',
        'pendente',
        'inativo',
    ];

    if (
        !in_array(
            $companyStatus,
            $allowedStatuses,
            true
        )
    ) {
        throw new InvalidArgumentException(
            $companyStatus === 'bloqueado'
                ? 'A empresa está bloqueada. Reative-a antes de acessar.'
                : 'A empresa não está disponível para acesso.'
        );
    }

    /*
     * O vínculo é gerado durante a autenticação e corresponde a um
     * hash SHA-256. O ID bruto da sessão nunca é armazenado no banco.
     */
    $sessionBindingHash = $currentUser
        ->sessionBindingHash();

    if (
        !preg_match(
            '/^[a-f0-9]{64}$/D',
            $sessionBindingHash
        )
    ) {
        throw new RuntimeException(
            'Não foi possível validar a sessão administrativa.'
        );
    }

    /*
     * Regenera o ID da sessão antes de ativar um novo contexto
     * empresarial, reduzindo o risco de fixação de sessão.
     */
    $session->regenerateId();

    /*
     * O motivo não vem mais do formulário.
     *
     * Ele é definido internamente e registrado automaticamente,
     * mantendo a auditoria sem atrapalhar a navegação do suporte.
     */
    $application
        ->adminAccesses()
        ->enter(
            $company,
            $currentUser->id(),
            (string) (
                $_SERVER['REMOTE_ADDR']
                ?? ''
            ),
            (string) (
                $_SERVER['HTTP_USER_AGENT']
                ?? ''
            ),
            'Acesso administrativo direto pelo painel da plataforma',
            $sessionBindingHash,
            $application->activeCompanyContext()
        );

    $session->flash(
        'success',
        'Painel operacional aberto com acesso administrativo registrado.'
    );

    /*
     * O helper administrativo deve gerar o caminho do dashboard
     * operacional do Flux Empresas.
     */
    admin_action_redirect(
        'dashboard.php'
    );
} catch (Throwable $exception) {
    /*
     * Caso exista uma empresa válida, retorna aos detalhes dela.
     * Caso contrário, retorna à listagem administrativa.
     */
    admin_action_error(
        $exception,
        $companyId === null
            ? 'adm/empresas.php'
            : 'adm/empresa.php?id=' . $companyId
    );
}