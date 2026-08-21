<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/repository.php';

$pageDefinition = [
    'title' => 'Candidatos',
    'description' => 'Base de candidatos com controle de qualidade cadastral e filtros de revisão.',
    'actions' => [
        [
            'label' => 'Novo candidato',
            'icon' => 'person-plus',
            'primary' => true,
            'href' => 'primeiro-emprego/cadastro-candidato.php',
        ],
        [
            'label' => 'Importações',
            'icon' => 'arrow-down-up',
            'href' => 'primeiro-emprego/importar-candidatos.php',
        ],
    ],
    'demo' => false,
    'show_states' => false,
    'modal' => [
        'title' => 'Candidato',
    ],
];

$dbReady =
    pe_db_ready()
    && pe_schema_ready();

$stats = [
    'total' => 0,
    'contemplados' => 0,
    'lista_espera' => 0,
    'visitas' => 0,
    'deferidos' => 0,
    'indeferidos' => 0,
    'importados' => 0,
    'revisao_pendente' => 0,
    'revisar_cadastro' => 0,
    'cpf_duplicado' => 0,
];

$filters = [
    'q' =>
        trim(
            (string) (
                $_GET['q']
                ?? ''
            )
        ),

    'status' =>
        trim(
            (string) (
                $_GET['status']
                ?? ''
            )
        ),

    'bairro' =>
        trim(
            (string) (
                $_GET['bairro']
                ?? ''
            )
        ),

    'setor' =>
        trim(
            (string) (
                $_GET['setor']
                ?? ''
            )
        ),

    'origem' =>
        trim(
            (string) (
                $_GET['origem']
                ?? ''
            )
        ),

    'revisao' =>
        trim(
            (string) (
                $_GET['revisao']
                ?? ''
            )
        ),
];

$currentPage =
    max(
        1,
        (int) (
            $_GET['p']
            ?? 1
        )
    );

$list = [
    'rows' => [],
    'total' => 0,
    'page' => 1,
    'pages' => 1,
    'per_page' => 50,
];

$filterOptions = [
    'bairros' => [],
    'setores' => [],
];

$message = null;

$reviewCandidate = null;
$reviewPeers = [];
$reviewHistory = [];

$visitCandidate = null;
$visitHistory = [];

$profileCandidate = null;
$profileData = null;

$reviewId =
    (int) (
        $_GET['revisar']
        ?? 0
    );

$visitId =
    (int) (
        $_GET['visita']
        ?? 0
    );

$profileId =
    (int) (
        $_GET['ficha']
        ?? 0
    );


if ($dbReady) {
    $pdo = pe_db();

    /*
     * ============================================================
     * AÇÕES POST
     * ============================================================
     */

    if (
        $_SERVER['REQUEST_METHOD']
        === 'POST'
    ) {
        $action =
            (string) (
                $_POST['pe_action']
                ?? ''
            );

        $postedCandidateId =
            (int) (
                $_POST['candidato_id']
                ?? 0
            );

        try {
            if ($action !== '') {
                pe_verify_csrf();
            }

            if (
                $action
                === 'save_review'
            ) {
                $reviewId =
                    $postedCandidateId;

                pe_review_candidate(
                    $pdo,
                    $reviewId,
                    $_POST,
                    pe_current_user_label()
                );

                $message = [
                    'type' => 'success',
                    'text' => 'Revisão cadastral salva. As pendências foram recalculadas automaticamente.',
                ];
            } elseif (
                $action
                === 'save_visit'
            ) {
                $visitId =
                    $postedCandidateId;

                pe_save_visit(
                    $pdo,
                    $_POST
                );

                $message = [
                    'type' => 'success',
                    'text' => 'Visita social registrada com sucesso.',
                ];
            } elseif (
                $action
                === 'save_profile'
            ) {
                $profileId =
                    $postedCandidateId;

                pe_save_profile(
                    $pdo,
                    $_POST,
                    $_FILES
                );

                $message = [
                    'type' => 'success',
                    'text' => 'Ficha cadastral atualizada com sucesso.',
                ];
            } elseif (
                $action
                === 'delete_candidate'
            ) {
                pe_delete_candidate(
                    $pdo,
                    $postedCandidateId
                );

                $message = [
                    'type' => 'success',
                    'text' => 'Candidato excluído com sucesso.',
                ];
            }
        } catch (Throwable $e) {
            $message = [
                'type' => 'danger',
                'text' => $e->getMessage(),
            ];
        }
    }


    /*
     * ============================================================
     * CARREGAR REVISÃO
     * ============================================================
     */

    if ($reviewId > 0) {
        $reviewCandidate =
            pe_candidate_by_id(
                $pdo,
                $reviewId
            );

        if ($reviewCandidate) {
            $reviewPeers =
                pe_candidate_duplicate_peers(
                    $pdo,
                    $reviewId,
                    $reviewCandidate['cpf']
                    ?? null
                );

            $reviewHistory =
                pe_review_history(
                    $pdo,
                    $reviewId,
                    10
                );
        }
    }


    /*
     * ============================================================
     * CARREGAR VISITA
     * ============================================================
     */

    if ($visitId > 0) {
        $visitCandidate =
            pe_candidate_by_id(
                $pdo,
                $visitId
            );

        if ($visitCandidate) {
            $visitStmt =
                $pdo->prepare(
                    '
                    SELECT
                        id,
                        data_visita,
                        entrevistador,
                        parecer_tecnico,
                        decisao,
                        tecnico_responsavel,
                        created_at

                    FROM pe_visitas_sociais

                    WHERE candidato_id = :candidato_id

                    ORDER BY
                        data_visita DESC,
                        id DESC

                    LIMIT 5
                    '
                );

            $visitStmt->execute([
                'candidato_id'
                    => $visitId,
            ]);

            $visitHistory =
                $visitStmt->fetchAll(
                    PDO::FETCH_ASSOC
                )
                ?: [];
        }
    }


    /*
     * ============================================================
     * CARREGAR FICHA CADASTRAL
     * ============================================================
     */

    if ($profileId > 0) {
        $profileCandidate =
            pe_candidate_by_id(
                $pdo,
                $profileId
            );

        if ($profileCandidate) {
            $profileStmt =
                $pdo->prepare(
                    '
                    SELECT *
                    FROM pe_fichas_cadastrais
                    WHERE candidato_id = :candidato_id
                    LIMIT 1
                    '
                );

            $profileStmt->execute([
                'candidato_id'
                    => $profileId,
            ]);

            $profileData =
                $profileStmt->fetch(
                    PDO::FETCH_ASSOC
                )
                ?: null;
        }
    }


    /*
     * ============================================================
     * LISTAGEM
     * ============================================================
     */

    $stats =
        pe_dashboard_stats(
            $pdo
        );

    $list =
        pe_candidate_page(
            $pdo,
            $filters,
            $currentPage,
            50
        );

    $filterOptions =
        pe_candidate_filters(
            $pdo
        );
}


/*
 * ================================================================
 * URL DA PAGINAÇÃO
 * ================================================================
 */

function pe_candidate_page_url(
    int $targetPage
): string {
    $query = $_GET;

    unset(
        $query['revisar'],
        $query['visita'],
        $query['ficha']
    );

    $query['p'] =
        $targetPage;

    return
        'primeiro-emprego/candidatos.php?'
        . http_build_query(
            $query
        );
}


/*
 * ================================================================
 * URL DAS AÇÕES
 * ================================================================
 */

function pe_candidate_action_url(
    string $action,
    int $candidateId
): string {
    $query = $_GET;

    unset(
        $query['revisar'],
        $query['visita'],
        $query['ficha']
    );

    $query[$action] =
        $candidateId;

    return
        'primeiro-emprego/candidatos.php?'
        . http_build_query(
            $query
        );
}


/*
 * ================================================================
 * URL LIMPA
 * ================================================================
 */

function pe_candidate_clean_url(): string
{
    $query = $_GET;

    unset(
        $query['revisar'],
        $query['visita'],
        $query['ficha']
    );

    $queryString =
        http_build_query(
            $query
        );

    return
        'primeiro-emprego/candidatos.php'
        . (
            $queryString !== ''
                ? '?' . $queryString
                : ''
        );
}


ob_start();
?>

<section
    class="
        content-card
        pe-form-card
        pe-page
        pe-candidates-page
    "
>

    <?php if (!$dbReady): ?>

        <div
            class="
                alert
                alert-warning
                mb-3
            "
        >
            <strong>
                Estrutura de revisão não pronta.
            </strong>

            Execute

            <code>
                database/primeiroEmprego/0002-primeiroEmprego-operacional.sql
            </code>

            no banco da hospedagem.
        </div>

    <?php endif; ?>


    <?php if ($message): ?>

        <div
            class="
                alert
                alert-<?= pe_h(
                    $message['type']
                ) ?>
            "
        >
            <?= pe_h(
                $message['text']
            ) ?>
        </div>

    <?php endif; ?>


    <!-- ==========================================================
         CABEÇALHO
         ========================================================== -->

    <div
        class="
            pe-page-hero
            pe-candidates-hero
        "
    >
        <div>

            <div class="card-kicker">
                Banco de candidatos
            </div>

            <h2>
                Candidatos do Meu Primeiro Emprego
            </h2>

            <p>
                Consulte, filtre e acompanhe os candidatos.
                Clique em qualquer linha para abrir o painel
                de ações.
            </p>

        </div>


        <div
            class="
                pe-page-actions
                pe-candidates-hero__actions
                pe-no-print
            "
        >

            <a
                class="
                    btn
                    btn-primary
                "
                href="
                    primeiro-emprego/cadastro-candidato.php
                "
            >
                <i
                    class="
                        bi
                        bi-person-plus
                    "
                ></i>

                Novo candidato
            </a>


            <a
                class="
                    btn
                    btn-light
                "
                href="
                    primeiro-emprego/importar-candidatos.php
                "
            >
                <i
                    class="
                        bi
                        bi-arrow-down-up
                    "
                ></i>

                Importações
            </a>

        </div>
    </div>


    <!-- ==========================================================
         INDICADORES
         ========================================================== -->

    <div
        class="
            pe-kpi-grid
            mb-4
        "
    >

        <div
            class="
                pe-kpi
                pe-kpi--primary
            "
        >
            <span>
                Total de candidatos
            </span>

            <strong>
                <?= (int) $stats['total'] ?>
            </strong>

            <small>
                base cadastrada
            </small>
        </div>


        <div class="pe-kpi">

            <span>
                Contemplados
            </span>

            <strong>
                <?= (int) $stats['contemplados'] ?>
            </strong>

            <small>
                no programa
            </small>
        </div>


        <div class="pe-kpi pe-kpi--info">

            <span>
                Lista de espera
            </span>

            <strong>
                <?= (int) $stats['lista_espera'] ?>
            </strong>

            <small>
                aguardando contemplação
            </small>
        </div>


        <div
            class="
                pe-kpi
                pe-kpi--warning
            "
        >

            <span>
                Revisão pendente
            </span>

            <strong>
                <?= (int) $stats['revisao_pendente'] ?>
            </strong>

            <small>
                precisam de atenção
            </small>
        </div>


        <div
            class="
                pe-kpi
                pe-kpi--orange
            "
        >

            <span>
                Revisar cadastro
            </span>

            <strong>
                <?= (int) $stats['revisar_cadastro'] ?>
            </strong>

            <small>
                múltiplas pendências
            </small>
        </div>


        <div
            class="
                pe-kpi
                pe-kpi--danger
            "
        >

            <span>
                CPF duplicado
            </span>

            <strong>
                <?= (int) $stats['cpf_duplicado'] ?>
            </strong>

            <small>
                conferência necessária
            </small>
        </div>


        <div class="pe-kpi">

            <span>
                Importados
            </span>

            <strong>
                <?= (int) $stats['importados'] ?>
            </strong>

            <small>
                via planilha
            </small>
        </div>

    </div>


    <!-- ==========================================================
         LEGENDA
         ========================================================== -->

    <div
        class="
            pe-legend
            pe-review-legend
            pe-no-print
            mb-3
        "
        aria-label="
            Legenda de revisão
        "
    >

        <span>
            <i
                class="
                    pe-review-dot
                    pe-review-dot--yellow
                "
            ></i>

            uma pendência
        </span>


        <span>
            <i
                class="
                    pe-review-dot
                    pe-review-dot--orange
                "
            ></i>

            revisar cadastro
        </span>


        <span>
            <i
                class="
                    pe-review-dot
                    pe-review-dot--red
                "
            ></i>

            CPF duplicado
        </span>


        <span
            class="
                ms-auto
                d-none
                d-lg-inline-flex
            "
        >
            <i
                class="
                    bi
                    bi-cursor
                "
            ></i>

            clique na linha para ver ações
        </span>

    </div>


    <!-- ==========================================================
         FILTROS
         ========================================================== -->

    <form
        method="get"
        class="
            pe-filter-panel
            pe-candidate-filters
            pe-no-print
            mb-4
        "
    >

        <div class="pe-filter-search">

            <i
                class="
                    bi
                    bi-search
                "
            ></i>

            <input
                class="form-control"
                name="q"
                value="<?= pe_h(
                    $filters['q']
                ) ?>"
                placeholder="
                    Buscar por nome, CPF, telefone,
                    responsável, bairro ou setor
                "
                aria-label="
                    Buscar candidatos
                "
            >

        </div>


        <select
            class="form-select"
            name="revisao"
            aria-label="
                Filtrar por revisão
            "
        >

            <option value="">
                Todas as revisões
            </option>

            <option
                value="pendentes"
                <?= (
                    $filters['revisao']
                    === 'pendentes'
                )
                    ? ' selected'
                    : ''
                ?>
            >
                Todas pendentes
            </option>

            <option
                value="cpf"
                <?= (
                    $filters['revisao']
                    === 'cpf'
                )
                    ? ' selected'
                    : ''
                ?>
            >
                Revisar CPF
            </option>

            <option
                value="telefone"
                <?= (
                    $filters['revisao']
                    === 'telefone'
                )
                    ? ' selected'
                    : ''
                ?>
            >
                Revisar Telefone
            </option>

            <option
                value="nascimento"
                <?= (
                    $filters['revisao']
                    === 'nascimento'
                )
                    ? ' selected'
                    : ''
                ?>
            >
                Revisar Nascimento
            </option>

            <option
                value="cadastro"
                <?= (
                    $filters['revisao']
                    === 'cadastro'
                )
                    ? ' selected'
                    : ''
                ?>
            >
                Revisar Cadastro
            </option>

            <option
                value="cpf_duplicado"
                <?= (
                    $filters['revisao']
                    === 'cpf_duplicado'
                )
                    ? ' selected'
                    : ''
                ?>
            >
                CPF Duplicado
            </option>

            <option
                value="sem_pendencia"
                <?= (
                    $filters['revisao']
                    === 'sem_pendencia'
                )
                    ? ' selected'
                    : ''
                ?>
            >
                Sem pendência
            </option>

        </select>


        <select
            class="form-select"
            name="status"
            aria-label="
                Filtrar por status
            "
        >

            <option value="">
                Todos os status
            </option>

            <?php foreach (
                [
                    'Em triagem',
                    'Em análise',
                    'Deferido',
                    'Indeferido',
                    'Importado',
                    'Lista de espera',
                    'Contemplado',
                ]
                as $v
            ): ?>

                <option
                    value="<?= pe_h($v) ?>"
                    <?= (
                        $filters['status']
                        === $v
                    )
                        ? ' selected'
                        : ''
                    ?>
                >
                    <?= pe_h($v) ?>
                </option>

            <?php endforeach; ?>

        </select>


        <select
            class="form-select"
            name="bairro"
            aria-label="
                Filtrar por bairro
            "
        >

            <option value="">
                Todos os bairros
            </option>

            <?php foreach (
                $filterOptions['bairros']
                as $v
            ): ?>

                <option
                    value="<?= pe_h($v) ?>"
                    <?= (
                        $filters['bairro']
                        === $v
                    )
                        ? ' selected'
                        : ''
                    ?>
                >
                    <?= pe_h($v) ?>
                </option>

            <?php endforeach; ?>

        </select>


        <select
            class="form-select"
            name="setor"
            aria-label="
                Filtrar por setor
            "
        >

            <option value="">
                Todos os setores
            </option>

            <?php foreach (
                $filterOptions['setores']
                as $v
            ): ?>

                <option
                    value="<?= pe_h($v) ?>"
                    <?= (
                        $filters['setor']
                        === $v
                    )
                        ? ' selected'
                        : ''
                    ?>
                >
                    <?= pe_h($v) ?>
                </option>

            <?php endforeach; ?>

        </select>


        <select
            class="form-select"
            name="origem"
            aria-label="
                Filtrar por origem
            "
        >

            <option value="">
                Todas as origens
            </option>

            <option
                value="manual"
                <?= (
                    $filters['origem']
                    === 'manual'
                )
                    ? ' selected'
                    : ''
                ?>
            >
                Manual
            </option>

            <option
                value="importacao"
                <?= (
                    $filters['origem']
                    === 'importacao'
                )
                    ? ' selected'
                    : ''
                ?>
            >
                Importação
            </option>

        </select>


        <button
            class="
                btn
                btn-primary
                pe-filter-submit
            "
            type="submit"
        >
            <i
                class="
                    bi
                    bi-funnel
                "
            ></i>

            <span>
                Filtrar
            </span>
        </button>


        <a
            class="
                btn
                btn-light
                pe-filter-clear
            "
            href="
                primeiro-emprego/candidatos.php
            "
            title="
                Limpar filtros
            "
            aria-label="
                Limpar filtros
            "
        >
            <i
                class="
                    bi
                    bi-x-lg
                "
            ></i>
        </a>

    </form>


    <!-- ==========================================================
         TOOLBAR
         ========================================================== -->

    <div class="pe-table-toolbar">

        <div>
            <strong>
                <?= (int) $list['total'] ?>
            </strong>

            candidato(s)

            <span>
                • Página
                <?= (int) $list['page'] ?>
                de
                <?= (int) $list['pages'] ?>
            </span>
        </div>


        <small
            class="
                text-muted
                d-none
                d-md-inline
            "
        >
            <i
                class="
                    bi
                    bi-hand-index-thumb
                "
            ></i>

            Clique em uma linha para abrir
            detalhes e ações
        </small>

    </div>


    <!-- ==========================================================
         TABELA
         ========================================================== -->

    <div
        class="
            pe-table-wrap
            pe-candidate-table-wrap
        "
    >

        <div
            class="
                table-responsive
                pe-table-scroll
                pe-candidate-table-scroll
            "
        >

            <table
                class="
                    table
                    align-middle
                    pe-data-table
                    pe-table
                    pe-candidate-review-table
                    pe-candidate-table
                "
            >

                <thead>
                    <tr>
                        <th>
                            Candidato
                        </th>

                        <th>
                            CPF
                        </th>

                        <th>
                            Nascimento
                        </th>

                        <th>
                            Telefone
                        </th>

                        <th>
                            Bairro
                        </th>

                        <th>
                            Setor
                        </th>

                        <th>
                            Revisão
                        </th>

                        <th>
                            Status
                        </th>
                    </tr>
                </thead>


                <tbody>

                <?php if (
                    !$list['rows']
                ): ?>

                    <tr class="pe-empty-row">

                        <td
                            colspan="8"
                            class="
                                text-center
                                text-muted
                                py-5
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-search
                                    d-block
                                    fs-3
                                    mb-2
                                "
                            ></i>

                            Nenhum candidato encontrado
                            com os filtros atuais.

                        </td>

                    </tr>

                <?php endif; ?>


                <?php foreach (
                    $list['rows']
                    as $row
                ): ?>

                    <?php

                    $reviewLabels =
                        pe_review_labels(
                            $row
                        );

                    $candidateCpf =
                        pe_format_cpf(
                            $row['cpf']
                            ?: $row['cpf_informado']
                            ?: '—'
                        );

                    $candidateBirth =
                        $row['data_nascimento']
                            ? date(
                                'd/m/Y',
                                strtotime(
                                    (string) $row['data_nascimento']
                                )
                            )
                            : 'Não informada';

                    $candidatePhone =
                        pe_format_phone(
                            $row['telefone']
                            ?: '—'
                        );

                    $candidateReview =
                        !empty(
                            $row['revisao_status']
                        )
                            ? (string) $row['revisao_status']
                            : 'Sem pendência';

                    $candidateReviewDetails =
                        $reviewLabels
                            ? implode(
                                ' · ',
                                $reviewLabels
                            )
                            : 'Cadastro sem pendências';

                    $candidateStatusClass =
                        match ((string) $row['status']) {
                            'Contemplado' => 'status-success',
                            'Lista de espera' => 'status-warning',
                            'Indeferido' => 'status-danger',
                            'Em análise', 'Em triagem' => 'status-info',
                            default => 'status-neutral',
                        };

                    ?>


                    <tr
                        class="
                            pe-table-row
                            pe-candidate-row
                            <?= pe_h(
                                pe_review_row_class(
                                    $row
                                )
                            ) ?>
                        "
                        tabindex="0"
                        role="button"

                        aria-label="
                            Abrir ações de
                            <?= pe_h(
                                $row['nome']
                            ) ?>
                        "

                        data-pe-candidate-row

                        data-id="
                            <?= (int) $row['id'] ?>
                        "

                        data-name="
                            <?= pe_h(
                                $row['nome']
                            ) ?>
                        "

                        data-cpf="
                            <?= pe_h(
                                $candidateCpf
                            ) ?>
                        "

                        data-birth="
                            <?= pe_h(
                                $candidateBirth
                            ) ?>
                        "

                        data-phone="
                            <?= pe_h(
                                $candidatePhone
                            ) ?>
                        "

                        data-neighborhood="
                            <?= pe_h(
                                $row['bairro']
                                ?: 'Não informado'
                            ) ?>
                        "

                        data-sector="
                            <?= pe_h(
                                $row['setor']
                                ?: 'Não informado'
                            ) ?>
                        "

                        data-review="
                            <?= pe_h(
                                $candidateReview
                            ) ?>
                        "

                        data-review-details="
                            <?= pe_h(
                                $candidateReviewDetails
                            ) ?>
                        "

                        data-status="
                            <?= pe_h(
                                $row['status']
                            ) ?>
                        "

                        data-origin="
                            <?= pe_h(
                                $row['origem']
                            ) ?>
                        "

                        data-duplicate="
                            <?= (
                                !empty(
                                    $row['cpf_duplicado']
                                )
                                    ? '1'
                                    : '0'
                            ) ?>
                        "
                    >

                        <td
                            data-label="
                                Candidato
                            "
                            class="
                                pe-candidate-name-cell
                            "
                        >

                            <div
                                class="
                                    pe-candidate-name
                                "
                            >

                                <span
                                    class="
                                        pe-candidate-avatar
                                    "
                                    aria-hidden="true"
                                >
                                    <i
                                        class="
                                            bi
                                            bi-person
                                        "
                                    ></i>
                                </span>


                                <div>

                                    <strong>
                                        <?= pe_h(
                                            $row['nome']
                                        ) ?>
                                    </strong>

                                    <small>
                                        #<?= (int) $row['id'] ?>
                                        ·
                                        <?= pe_h(
                                            $row['origem']
                                        ) ?>
                                    </small>

                                </div>

                            </div>

                        </td>


                        <td
                            data-label="
                                CPF
                            "
                        >

                            <span>
                                <?= pe_h(
                                    $candidateCpf
                                ) ?>
                            </span>

                            <?php if (
                                !empty(
                                    $row['cpf_duplicado']
                                )
                            ): ?>

                                <small
                                    class="
                                        pe-inline-alert
                                    "
                                >
                                    <i
                                        class="
                                            bi
                                            bi-exclamation-triangle-fill
                                        "
                                    ></i>

                                    duplicado
                                </small>

                            <?php endif; ?>

                        </td>


                        <td
                            data-label="
                                Nascimento
                            "
                        >
                            <?= pe_h(
                                $candidateBirth
                            ) ?>
                        </td>


                        <td
                            data-label="
                                Telefone
                            "
                        >
                            <?= pe_h(
                                $candidatePhone
                            ) ?>
                        </td>


                        <td
                            data-label="
                                Bairro
                            "
                        >
                            <?= pe_h(
                                $row['bairro']
                                ?: '—'
                            ) ?>
                        </td>


                        <td
                            data-label="
                                Setor
                            "
                        >
                            <?= pe_h(
                                $row['setor']
                                ?: '—'
                            ) ?>
                        </td>


                        <td
                            data-label="
                                Revisão
                            "
                            class="
                                pe-review-cell
                            "
                        >

                            <?php if (
                                !empty(
                                    $row['revisao_status']
                                )
                            ): ?>

                                <span
                                    class="
                                        badge
                                        <?= pe_h(
                                            pe_review_badge_class(
                                                $row
                                            )
                                        ) ?>
                                    "
                                >
                                    <?= pe_h(
                                        $row['revisao_status']
                                    ) ?>
                                </span>


                                <?php if (
                                    $reviewLabels
                                ): ?>

                                    <small>
                                        <?= pe_h(
                                            implode(
                                                ' · ',
                                                $reviewLabels
                                            )
                                        ) ?>
                                    </small>

                                <?php endif; ?>


                            <?php else: ?>

                                <span
                                    class="
                                        badge
                                        pe-badge-ok
                                    "
                                >
                                    <i
                                        class="
                                            bi
                                            bi-check2
                                        "
                                    ></i>

                                    Sem pendência
                                </span>

                            <?php endif; ?>

                        </td>


                        <td
                            data-label="
                                Status
                            "
                        >
                            <span
                                class="
                                    pe-status-badge
                                    <?= pe_h($candidateStatusClass) ?>
                                "
                            >
                                <?= pe_h(
                                    $row['status']
                                ) ?>
                            </span>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>
    </div>


    <!-- ==========================================================
         MODAL DE AÇÕES
         ========================================================== -->

    <dialog
        class="
            pe-modal
            pe-candidate-modal
        "
        id="peCandidateDialog"
        aria-labelledby="
            peCandidateDialogTitle
        "
    >

        <div
            class="
                pe-modal__shell
                pe-candidate-modal__shell
            "
        >

            <header
                class="
                    pe-modal__header
                    pe-candidate-modal__header
                "
            >

                <div>

                    <div class="card-kicker">
                        Candidato
                    </div>

                    <h2
                        id="
                            peCandidateDialogTitle
                        "
                        data-pe-modal-name
                    >
                        Detalhes do candidato
                    </h2>

                    <p
                        data-pe-modal-meta
                    >
                        Selecione um candidato
                        para visualizar as ações
                        disponíveis.
                    </p>

                </div>


                <button
                    type="button"
                    class="
                        pe-modal__close
                        pe-candidate-modal__close
                    "
                    data-pe-modal-close
                    aria-label="
                        Fechar
                    "
                >
                    <i
                        class="
                            bi
                            bi-x-lg
                        "
                        aria-hidden="true"
                    ></i>
                </button>

            </header>


            <div
                class="
                    pe-modal__body
                    pe-candidate-modal__body
                "
            >

                <div
                    class="
                        pe-modal-review
                    "
                    data-pe-modal-review-box
                >

                    <div
                        class="
                            pe-modal-review__heading
                        "
                    >
                        <i
                            class="
                                bi
                                bi-shield-check
                            "
                            aria-hidden="true"
                        ></i>

                        <strong
                            data-pe-modal-review
                        >
                            Sem pendência
                        </strong>
                    </div>

                    <span
                        data-pe-modal-review-details
                    >
                        Cadastro sem pendências.
                    </span>

                </div>


                <dl
                    class="
                        pe-modal-details
                    "
                >

                    <div>
                        <dt>
                            CPF
                        </dt>

                        <dd
                            data-pe-modal-cpf
                        >
                            —
                        </dd>
                    </div>


                    <div>
                        <dt>
                            Telefone
                        </dt>

                        <dd
                            data-pe-modal-phone
                        >
                            —
                        </dd>
                    </div>


                    <div>
                        <dt>
                            Nascimento
                        </dt>

                        <dd
                            data-pe-modal-birth
                        >
                            —
                        </dd>
                    </div>


                    <div>
                        <dt>
                            Bairro
                        </dt>

                        <dd
                            data-pe-modal-neighborhood
                        >
                            —
                        </dd>
                    </div>


                    <div>
                        <dt>
                            Setor
                        </dt>

                        <dd
                            data-pe-modal-sector
                        >
                            —
                        </dd>
                    </div>


                    <div>
                        <dt>
                            Status
                        </dt>

                        <dd
                            data-pe-modal-status
                        >
                            —
                        </dd>
                    </div>

                </dl>


                <div
                    class="
                        pe-modal-actions-title
                    "
                >
                    Ações do candidato
                </div>


                <div
                    class="
                        pe-modal-actions
                    "
                >

                    <a
                        class="
                            pe-modal-action
                            pe-modal-action--primary
                        "
                        href="#"
                        data-pe-modal-action-review
                    >

                        <span
                            class="
                                pe-modal-action__icon
                            "
                        >
                            <i
                                class="
                                    bi
                                    bi-pencil-square
                                "
                            ></i>
                        </span>

                        <span>

                            <strong>
                                Revisar / editar cadastro
                            </strong>

                            <small>
                                Corrigir ou confirmar
                                dados cadastrais
                            </small>

                        </span>

                        <i
                            class="
                                bi
                                bi-chevron-right
                            "
                        ></i>

                    </a>


                    <a
                        class="
                            pe-modal-action
                        "
                        href="#"
                        data-pe-modal-action-visit
                    >

                        <span
                            class="
                                pe-modal-action__icon
                            "
                        >
                            <i
                                class="
                                    bi
                                    bi-house-check
                                "
                            ></i>
                        </span>

                        <span>

                            <strong>
                                Visita social
                            </strong>

                            <small>
                                Abrir acompanhamento
                                e parecer técnico
                            </small>

                        </span>

                        <i
                            class="
                                bi
                                bi-chevron-right
                            "
                        ></i>

                    </a>


                    <a
                        class="
                            pe-modal-action
                        "
                        href="#"
                        data-pe-modal-action-profile
                    >

                        <span
                            class="
                                pe-modal-action__icon
                            "
                        >
                            <i
                                class="
                                    bi
                                    bi-person-vcard
                                "
                            ></i>
                        </span>

                        <span>

                            <strong>
                                Ficha cadastral
                            </strong>

                            <small>
                                Consultar ou preencher
                                a ficha do candidato
                            </small>

                        </span>

                        <i
                            class="
                                bi
                                bi-chevron-right
                            "
                        ></i>

                    </a>

                    <button
                        class="pe-modal-action pe-modal-action--danger"
                        type="button"
                        data-pe-candidate-delete
                    >
                        <span class="pe-modal-action__icon"><i class="bi bi-trash3"></i></span>
                        <span><strong>Excluir candidato</strong><small>Remover o cadastro e os vínculos relacionados</small></span>
                        <i class="bi bi-chevron-right"></i>
                    </button>

                </div>

            </div>

        </div>

    </dialog>


    <dialog class="pe-modal pe-modal--confirm" id="peCandidateDeleteDialog">
        <div class="pe-modal__shell">
            <header class="pe-modal__header">
                <div><div class="card-kicker">Excluir candidato</div><h2>Confirmar exclusão</h2><p>Esta ação remove o candidato e os registros vinculados.</p></div>
                <button type="button" class="pe-modal__close" data-pe-dialog-close aria-label="Fechar"><i class="bi bi-x-lg"></i></button>
            </header>
            <div class="pe-modal__body">
                <div class="pe-delete-warning"><i class="bi bi-exclamation-triangle"></i><div><strong data-pe-candidate-delete-name>Candidato selecionado</strong><span>Use esta ação somente quando a exclusão for realmente necessária.</span></div></div>
                <form method="post" class="pe-delete-form">
                    <?= pe_csrf_field() ?>
                    <input type="hidden" name="pe_action" value="delete_candidate">
                    <input type="hidden" name="candidato_id" value="" data-pe-candidate-delete-id>
                    <label class="pe-check-option pe-delete-confirm"><input type="checkbox" required><span>Confirmo a exclusão deste candidato.</span></label>
                    <footer class="pe-action-modal-footer"><button type="button" class="btn btn-light" data-pe-dialog-close>Cancelar</button><button type="submit" class="btn btn-danger"><i class="bi bi-trash3"></i> Excluir candidato</button></footer>
                </form>
            </div>
        </div>
    </dialog>


    <!-- ==========================================================
         MODAL DE REVISÃO
         ========================================================== -->

    <?php if (
        $reviewCandidate
    ): ?>

        <?php
        $reviewLabelsCurrent =
            pe_review_labels(
                $reviewCandidate
            );
        ?>

        <dialog
            class="
                pe-modal
                pe-modal--form
                pe-candidate-modal
                pe-candidate-modal--form
            "
            id="
                peReviewDialog
            "
            aria-labelledby="
                peReviewDialogTitle
            "
            data-pe-auto-open
        >

            <div
                class="
                    pe-modal__shell
                    pe-candidate-modal__shell
                "
            >

                <header
                    class="
                        pe-modal__header
                        pe-candidate-modal__header
                    "
                >

                    <div>

                        <div
                            class="
                                card-kicker
                            "
                        >
                            Revisão cadastral
                        </div>

                        <h2
                            id="
                                peReviewDialogTitle
                            "
                        >
                            <?= pe_h(
                                $reviewCandidate['nome']
                            ) ?>
                        </h2>

                        <p>
                            #<?= (int) $reviewCandidate['id'] ?>
                            · Corrija os dados ou
                            confirme a situação
                            após conferência.
                        </p>

                    </div>


                    <button
                        type="button"
                        class="
                            pe-modal__close
                            pe-candidate-modal__close
                        "
                        data-pe-dialog-close
                        data-clean-param="
                            revisar
                        "
                        aria-label="
                            Fechar
                        "
                    >
                        <i
                            class="
                                bi
                                bi-x-lg
                            "
                            aria-hidden="true"
                        ></i>
                    </button>

                </header>


                <div
                    class="
                        pe-modal__body
                        pe-candidate-modal__body
                        pe-action-form-body
                    "
                >

                    <?php if (
                        $message
                    ): ?>

                        <div
                            class="
                                pe-action-notice
                                <?= (
                                    $message['type']
                                    === 'danger'
                                        ? 'pe-action-notice--danger'
                                        : 'pe-action-notice--success'
                                ) ?>
                            "
                        >

                            <i
                                class="
                                    bi
                                    <?= (
                                        $message['type']
                                        === 'danger'
                                            ? 'bi-exclamation-octagon'
                                            : 'bi-check-circle'
                                    ) ?>
                                "
                            ></i>

                            <div>

                                <strong>
                                    <?= (
                                        $message['type']
                                        === 'danger'
                                            ? 'Não foi possível concluir'
                                            : 'Operação concluída'
                                    ) ?>
                                </strong>

                                <span>
                                    <?= pe_h(
                                        $message['text']
                                    ) ?>
                                </span>

                            </div>

                        </div>

                    <?php endif; ?>


                    <?php if (
                        $reviewLabelsCurrent
                    ): ?>

                        <div
                            class="
                                pe-action-notice
                                pe-action-notice--warning
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-exclamation-circle
                                "
                            ></i>

                            <div>

                                <strong>
                                    Pendências atuais
                                </strong>

                                <span>
                                    <?= pe_h(
                                        implode(
                                            ' · ',
                                            $reviewLabelsCurrent
                                        )
                                    ) ?>
                                </span>

                            </div>

                        </div>

                    <?php else: ?>

                        <div
                            class="
                                pe-action-notice
                                pe-action-notice--success
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-check-circle
                                "
                            ></i>

                            <div>

                                <strong>
                                    Cadastro sem pendências
                                </strong>

                                <span>
                                    Os dados ainda podem
                                    ser atualizados
                                    normalmente.
                                </span>

                            </div>

                        </div>

                    <?php endif; ?>


                    <?php if (
                        $reviewPeers
                    ): ?>

                        <div
                            class="
                                pe-action-notice
                                pe-action-notice--danger
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-exclamation-triangle-fill
                                "
                            ></i>

                            <div>

                                <strong>
                                    CPF encontrado em outro(s)
                                    candidato(s)
                                </strong>

                                <span>

                                    <?php foreach (
                                        $reviewPeers
                                        as $index => $peer
                                    ): ?>

                                        <?= (
                                            $index > 0
                                                ? ' · '
                                                : ''
                                        ) ?>

                                        #<?= (int) $peer['id'] ?>

                                        <?= pe_h(
                                            $peer['nome']
                                        ) ?>

                                        <?php if (
                                            $peer['data_nascimento']
                                        ): ?>

                                            (
                                            <?= pe_h(
                                                date(
                                                    'd/m/Y',
                                                    strtotime(
                                                        (string) $peer['data_nascimento']
                                                    )
                                                )
                                            ) ?>
                                            )

                                        <?php endif; ?>

                                    <?php endforeach; ?>

                                </span>

                            </div>

                        </div>

                    <?php endif; ?>


                    <form
                        method="post"
                        class="
                            pe-action-form
                        "
                        autocomplete="off"
                    >

                        <?= pe_csrf_field() ?>

                        <input
                            type="hidden"
                            name="pe_action"
                            value="save_review"
                        >

                        <input
                            type="hidden"
                            name="candidato_id"
                            value="<?= (int) $reviewCandidate['id'] ?>"
                        >


                        <div
                            class="
                                pe-action-form-grid
                                pe-action-form-grid--3
                            "
                        >

                            <!-- CPF -->

                            <section
                                class="
                                    pe-action-field-card
                                "
                            >

                                <label
                                    class="
                                        form-label
                                    "
                                    for="
                                        review-cpf
                                    "
                                >
                                    CPF
                                </label>

                                <input
                                    id="
                                        review-cpf
                                    "
                                    class="
                                        form-control
                                    "
                                    name="cpf"
                                    inputmode="numeric"
                                    maxlength="18"
                                    value="<?= pe_h(
                                        $reviewCandidate['cpf_informado']
                                        ?: $reviewCandidate['cpf']
                                    ) ?>"
                                >


                                <label
                                    class="
                                        pe-check-option
                                    "
                                >

                                    <input
                                        type="checkbox"
                                        name="
                                            confirmar_cpf_atual
                                        "
                                        value="1"
                                        <?= (
                                            !empty(
                                                $reviewCandidate['cpf_revisado_confirmado']
                                            )
                                                ? ' checked'
                                                : ''
                                        ) ?>
                                    >

                                    <span>
                                        Confirmar CPF
                                        atual/indisponível
                                    </span>

                                </label>


                                <?php if (
                                    !empty(
                                        $reviewCandidate['cpf_duplicado']
                                    )
                                ): ?>

                                    <label
                                        class="
                                            pe-check-option
                                        "
                                    >

                                        <input
                                            type="checkbox"
                                            name="
                                                confirmar_cpf_duplicado
                                            "
                                            value="1"
                                            <?= (
                                                !empty(
                                                    $reviewCandidate['cpf_duplicado_confirmado']
                                                )
                                                    ? ' checked'
                                                    : ''
                                            ) ?>
                                        >

                                        <span>
                                            Duplicidade conferida
                                        </span>

                                    </label>

                                <?php endif; ?>

                            </section>


                            <!-- TELEFONE -->

                            <section
                                class="
                                    pe-action-field-card
                                "
                            >

                                <label
                                    class="
                                        form-label
                                    "
                                    for="
                                        review-phone
                                    "
                                >
                                    Telefone
                                </label>

                                <input
                                    id="
                                        review-phone
                                    "
                                    class="
                                        form-control
                                    "
                                    name="
                                        telefone
                                    "
                                    inputmode="tel"
                                    maxlength="20"
                                    value="<?= pe_h(
                                        $reviewCandidate['telefone']
                                    ) ?>"
                                >


                                <label
                                    class="
                                        pe-check-option
                                    "
                                >

                                    <input
                                        type="checkbox"
                                        name="
                                            confirmar_telefone_atual
                                        "
                                        value="1"
                                        <?= (
                                            !empty(
                                                $reviewCandidate['telefone_revisado_confirmado']
                                            )
                                                ? ' checked'
                                                : ''
                                        ) ?>
                                    >

                                    <span>
                                        Confirmar que não possui
                                        ou manter atual
                                    </span>

                                </label>

                            </section>


                            <!-- NASCIMENTO -->

                            <section
                                class="
                                    pe-action-field-card
                                "
                            >

                                <label
                                    class="
                                        form-label
                                    "
                                    for="
                                        review-birth
                                    "
                                >
                                    Data de nascimento
                                </label>

                                <input
                                    id="
                                        review-birth
                                    "
                                    class="
                                        form-control
                                    "
                                    type="date"
                                    name="
                                        data_nascimento
                                    "
                                    value="<?= pe_h(
                                        $reviewCandidate['data_nascimento']
                                    ) ?>"
                                >


                                <label
                                    class="
                                        pe-check-option
                                    "
                                >

                                    <input
                                        type="checkbox"
                                        name="
                                            confirmar_nascimento_atual
                                        "
                                        value="1"
                                        <?= (
                                            !empty(
                                                $reviewCandidate['nascimento_revisado_confirmado']
                                            )
                                                ? ' checked'
                                                : ''
                                        ) ?>
                                    >

                                    <span>
                                        Confirmar que a data
                                        não está disponível
                                    </span>

                                </label>

                            </section>

                        </div>


                        <div
                            class="
                                pe-action-field-card
                                pe-action-field-card--full
                            "
                        >

                            <label
                                class="
                                    form-label
                                "
                                for="
                                    review-note
                                "
                            >
                                Observação da revisão
                            </label>

                            <textarea
                                id="
                                    review-note
                                "
                                class="
                                    form-control
                                "
                                name="
                                    observacao
                                "
                                rows="3"
                                maxlength="500"
                                placeholder="
                                    Registre o que foi
                                    conferido ou corrigido.
                                "
                            ></textarea>

                        </div>


                        <?php if (
                            $reviewHistory
                        ): ?>

                            <details
                                class="
                                    pe-action-history
                                "
                            >

                                <summary>

                                    <i
                                        class="
                                            bi
                                            bi-clock-history
                                        "
                                    ></i>

                                    Histórico de revisões

                                    <span>
                                        <?= count(
                                            $reviewHistory
                                        ) ?>
                                    </span>

                                </summary>


                                <div
                                    class="
                                        pe-action-history__list
                                    "
                                >

                                    <?php foreach (
                                        $reviewHistory
                                        as $hist
                                    ): ?>

                                        <article>

                                            <strong>
                                                <?= pe_h(
                                                    date(
                                                        'd/m/Y H:i',
                                                        strtotime(
                                                            (string) $hist['created_at']
                                                        )
                                                    )
                                                ) ?>
                                            </strong>

                                            <span>
                                                <?= pe_h(
                                                    $hist['revisado_por']
                                                    ?: 'Usuário não identificado'
                                                ) ?>
                                            </span>

                                            <p>
                                                <?= pe_h(
                                                    $hist['observacao']
                                                    ?: 'Revisão registrada'
                                                ) ?>
                                            </p>

                                        </article>

                                    <?php endforeach; ?>

                                </div>

                            </details>

                        <?php endif; ?>


                        <footer
                            class="
                                pe-action-modal-footer
                            "
                        >

                            <button
                                type="button"
                                class="
                                    btn
                                    btn-light
                                "
                                data-pe-dialog-close
                                data-clean-param="
                                    revisar
                                "
                            >
                                Cancelar
                            </button>


                            <button
                                class="
                                    btn
                                    btn-primary
                                "
                                type="submit"
                            >
                                <i
                                    class="
                                        bi
                                        bi-check2-circle
                                    "
                                ></i>

                                Salvar revisão
                            </button>

                        </footer>

                    </form>

                </div>

            </div>

        </dialog>

    <?php endif; ?>


    <!-- ==========================================================
         MODAL DE VISITA SOCIAL
         ========================================================== -->

    <?php if (
        $visitCandidate
    ): ?>

        <dialog
            class="
                pe-modal
                pe-modal--form
                pe-candidate-modal
                pe-candidate-modal--form
            "
            id="
                peVisitDialog
            "
            aria-labelledby="
                peVisitDialogTitle
            "
            data-pe-auto-open
        >

            <div
                class="
                    pe-modal__shell
                    pe-candidate-modal__shell
                "
            >

                <header
                    class="
                        pe-modal__header
                        pe-candidate-modal__header
                    "
                >

                    <div>

                        <div
                            class="
                                card-kicker
                            "
                        >
                            Visita social
                        </div>

                        <h2
                            id="
                                peVisitDialogTitle
                            "
                        >
                            <?= pe_h(
                                $visitCandidate['nome']
                            ) ?>
                        </h2>

                        <p>
                            #<?= (int) $visitCandidate['id'] ?>
                            · Registre a visita,
                            o parecer técnico
                            e a decisão.
                        </p>

                    </div>


                    <button
                        type="button"
                        class="
                            pe-modal__close
                            pe-candidate-modal__close
                        "
                        data-pe-dialog-close
                        data-clean-param="
                            visita
                        "
                        aria-label="
                            Fechar
                        "
                    >
                        <i
                            class="
                                bi
                                bi-x-lg
                            "
                            aria-hidden="true"
                        ></i>
                    </button>

                </header>


                <div
                    class="
                        pe-modal__body
                        pe-candidate-modal__body
                        pe-action-form-body
                    "
                >

                    <?php if (
                        $message
                    ): ?>

                        <div
                            class="
                                pe-action-notice
                                <?= (
                                    $message['type']
                                    === 'danger'
                                        ? 'pe-action-notice--danger'
                                        : 'pe-action-notice--success'
                                ) ?>
                            "
                        >

                            <i
                                class="
                                    bi
                                    <?= (
                                        $message['type']
                                        === 'danger'
                                            ? 'bi-exclamation-octagon'
                                            : 'bi-check-circle'
                                    ) ?>
                                "
                            ></i>

                            <div>

                                <strong>
                                    <?= (
                                        $message['type']
                                        === 'danger'
                                            ? 'Não foi possível concluir'
                                            : 'Operação concluída'
                                    ) ?>
                                </strong>

                                <span>
                                    <?= pe_h(
                                        $message['text']
                                    ) ?>
                                </span>

                            </div>

                        </div>

                    <?php endif; ?>


                    <form
                        method="post"
                        class="
                            pe-action-form
                        "
                        autocomplete="off"
                    >

                        <?= pe_csrf_field() ?>

                        <input
                            type="hidden"
                            name="
                                pe_action
                            "
                            value="
                                save_visit
                            "
                        >

                        <input
                            type="hidden"
                            name="
                                candidato_id
                            "
                            value="
                                <?= (int) $visitCandidate['id'] ?>
                            "
                        >


                        <div
                            class="
                                pe-action-form-grid
                                pe-action-form-grid--3
                            "
                        >

                            <div>

                                <label
                                    class="
                                        form-label
                                    "
                                    for="
                                        visit-date
                                    "
                                >
                                    Data da visita
                                </label>

                                <input
                                    id="
                                        visit-date
                                    "
                                    class="
                                        form-control
                                    "
                                    type="date"
                                    name="
                                        data_visita
                                    "
                                    value="<?= pe_h(
                                        date(
                                            'Y-m-d'
                                        )
                                    ) ?>"
                                    required
                                >

                            </div>


                            <div>

                                <label
                                    class="
                                        form-label
                                    "
                                    for="
                                        visit-interviewer
                                    "
                                >
                                    Entrevistador(a)
                                </label>

                                <input
                                    id="
                                        visit-interviewer
                                    "
                                    class="
                                        form-control
                                    "
                                    name="
                                        entrevistador
                                    "
                                    maxlength="
                                        160
                                    "
                                    value="<?= pe_h(
                                        pe_current_user_label()
                                    ) ?>"
                                >

                            </div>


                            <div>

                                <label
                                    class="
                                        form-label
                                    "
                                    for="
                                        visit-tech
                                    "
                                >
                                    Técnico responsável
                                </label>

                                <input
                                    id="
                                        visit-tech
                                    "
                                    class="
                                        form-control
                                    "
                                    name="
                                        tecnico_responsavel
                                    "
                                    maxlength="
                                        160
                                    "
                                    value="<?= pe_h(
                                        pe_current_user_label()
                                    ) ?>"
                                >

                            </div>

                        </div>


                        <div
                            class="
                                pe-action-field-card
                                pe-action-field-card--full
                            "
                        >

                            <label
                                class="
                                    form-label
                                "
                                for="
                                    visit-info
                                "
                            >
                                Informações complementares
                            </label>

                            <textarea
                                id="
                                    visit-info
                                "
                                class="
                                    form-control
                                "
                                name="
                                    informacoes_complementares
                                "
                                rows="3"
                                placeholder="
                                    Condições observadas,
                                    contexto familiar e
                                    demais informações
                                    relevantes.
                                "
                            ></textarea>

                        </div>


                        <div
                            class="
                                pe-action-field-card
                                pe-action-field-card--full
                            "
                        >

                            <label
                                class="
                                    form-label
                                "
                                for="
                                    visit-report
                                "
                            >
                                Parecer técnico
                            </label>

                            <textarea
                                id="
                                    visit-report
                                "
                                class="
                                    form-control
                                "
                                name="
                                    parecer_tecnico
                                "
                                rows="5"
                                placeholder="
                                    Descreva o parecer
                                    técnico da visita.
                                "
                                required
                            ></textarea>

                        </div>


                        <div
                            class="
                                pe-decision-grid
                            "
                            role="
                                group
                            "
                            aria-label="
                                Decisão da visita
                            "
                        >

                            <?php foreach (
                                [
                                    'Pendente'
                                        => 'clock',

                                    'Deferido'
                                        => 'check-circle',

                                    'Indeferido'
                                        => 'x-circle',
                                ]
                                as $decision
                                => $icon
                            ): ?>

                                <label
                                    class="
                                        pe-decision-option
                                        pe-decision-option--<?= strtolower(
                                            $decision
                                        ) ?>
                                    "
                                >

                                    <input
                                        type="
                                            radio
                                        "
                                        name="
                                            decisao
                                        "
                                        value="<?= pe_h(
                                            $decision
                                        ) ?>"
                                        <?= (
                                            $decision
                                            === 'Pendente'
                                                ? ' checked'
                                                : ''
                                        ) ?>
                                    >

                                    <span>

                                        <i
                                            class="
                                                bi
                                                bi-<?= pe_h(
                                                    $icon
                                                ) ?>
                                            "
                                        ></i>

                                        <strong>
                                            <?= pe_h(
                                                $decision
                                            ) ?>
                                        </strong>

                                    </span>

                                </label>

                            <?php endforeach; ?>

                        </div>


                        <?php if (
                            $visitHistory
                        ): ?>

                            <details
                                class="
                                    pe-action-history
                                "
                            >

                                <summary>

                                    <i
                                        class="
                                            bi
                                            bi-clock-history
                                        "
                                    ></i>

                                    Últimas visitas

                                    <span>
                                        <?= count(
                                            $visitHistory
                                        ) ?>
                                    </span>

                                </summary>


                                <div
                                    class="
                                        pe-action-history__list
                                    "
                                >

                                    <?php foreach (
                                        $visitHistory
                                        as $visit
                                    ): ?>

                                        <article>

                                            <strong>
                                                <?= pe_h(
                                                    date(
                                                        'd/m/Y',
                                                        strtotime(
                                                            (string) $visit['data_visita']
                                                        )
                                                    )
                                                ) ?>

                                                ·

                                                <?= pe_h(
                                                    $visit['decisao']
                                                ) ?>
                                            </strong>

                                            <span>
                                                <?= pe_h(
                                                    $visit['tecnico_responsavel']
                                                    ?: $visit['entrevistador']
                                                    ?: 'Responsável não informado'
                                                ) ?>
                                            </span>

                                            <p>
                                                <?= pe_h(
                                                    $visit['parecer_tecnico']
                                                ) ?>
                                            </p>

                                        </article>

                                    <?php endforeach; ?>

                                </div>

                            </details>

                        <?php endif; ?>


                        <footer
                            class="
                                pe-action-modal-footer
                            "
                        >

                            <button
                                type="button"
                                class="
                                    btn
                                    btn-light
                                "
                                data-pe-dialog-close
                                data-clean-param="
                                    visita
                                "
                            >
                                Cancelar
                            </button>


                            <button
                                class="
                                    btn
                                    btn-primary
                                "
                                type="
                                    submit
                                "
                            >
                                <i
                                    class="
                                        bi
                                        bi-house-check
                                    "
                                ></i>

                                Registrar visita
                            </button>

                        </footer>

                    </form>

                </div>

            </div>

        </dialog>

    <?php endif; ?>


    <!-- ==========================================================
         MODAL DE FICHA CADASTRAL
         ========================================================== -->

    <?php if (
        $profileCandidate
    ): ?>

        <?php

        $profile =
            $profileData
            ?: [];

        $profileEducation =
            (string) (
                $profile['nivel_escolaridade']
                ?? $profileCandidate['escolaridade']
                ?? ''
            );

        $profileSchoolStatus =
            (string) (
                $profile['situacao_escolar']
                ?? $profileCandidate['situacao_escolar']
                ?? ''
            );

        $profileInstitution =
            (string) (
                $profile['instituicao_ensino']
                ?? $profileCandidate['instituicao_ensino']
                ?? ''
            );

        $profilePeriod =
            (string) (
                $profile['serie_periodo']
                ?? ''
            );

        $profileStudyShift =
            (string) (
                $profile['turno_estudo']
                ?? $profileCandidate['turno_estudo']
                ?? ''
            );

        $profileWorkplace =
            (string) (
                $profile['local_atuacao']
                ?? ''
            );

        $profileWorkShift =
            (string) (
                $profile['turno_atuacao']
                ?? ''
            );

        ?>


        <dialog
            class="
                pe-modal
                pe-modal--form
                pe-candidate-modal
                pe-candidate-modal--form
            "
            id="
                peProfileDialog
            "
            aria-labelledby="
                peProfileDialogTitle
            "
            data-pe-auto-open
        >

            <div
                class="
                    pe-modal__shell
                    pe-candidate-modal__shell
                "
            >

                <header
                    class="
                        pe-modal__header
                        pe-candidate-modal__header
                    "
                >

                    <div>

                        <div
                            class="
                                card-kicker
                            "
                        >
                            Ficha cadastral
                        </div>

                        <h2
                            id="
                                peProfileDialogTitle
                            "
                        >
                            <?= pe_h(
                                $profileCandidate['nome']
                            ) ?>
                        </h2>

                        <p>
                            #<?= (int) $profileCandidate['id'] ?>
                            · Dados complementares
                            de escolaridade e atuação
                            no programa.
                        </p>

                    </div>


                    <button
                        type="button"
                        class="
                            pe-modal__close
                            pe-candidate-modal__close
                        "
                        data-pe-dialog-close
                        data-clean-param="
                            ficha
                        "
                        aria-label="
                            Fechar
                        "
                    >
                        <i
                            class="
                                bi
                                bi-x-lg
                            "
                            aria-hidden="true"
                        ></i>
                    </button>

                </header>


                <div
                    class="
                        pe-modal__body
                        pe-candidate-modal__body
                        pe-action-form-body
                    "
                >

                    <?php if (
                        $message
                    ): ?>

                        <div
                            class="
                                pe-action-notice
                                <?= (
                                    $message['type']
                                    === 'danger'
                                        ? 'pe-action-notice--danger'
                                        : 'pe-action-notice--success'
                                ) ?>
                            "
                        >

                            <i
                                class="
                                    bi
                                    <?= (
                                        $message['type']
                                        === 'danger'
                                            ? 'bi-exclamation-octagon'
                                            : 'bi-check-circle'
                                    ) ?>
                                "
                            ></i>

                            <div>

                                <strong>
                                    <?= (
                                        $message['type']
                                        === 'danger'
                                            ? 'Não foi possível concluir'
                                            : 'Operação concluída'
                                    ) ?>
                                </strong>

                                <span>
                                    <?= pe_h(
                                        $message['text']
                                    ) ?>
                                </span>

                            </div>

                        </div>

                    <?php endif; ?>


                    <form
                        method="
                            post
                        "
                        enctype="
                            multipart/form-data
                        "
                        class="
                            pe-action-form
                        "
                        autocomplete="
                            off
                        "
                    >

                        <?= pe_csrf_field() ?>

                        <input
                            type="
                                hidden
                            "
                            name="
                                pe_action
                            "
                            value="
                                save_profile
                            "
                        >

                        <input
                            type="
                                hidden
                            "
                            name="
                                candidato_id
                            "
                            value="
                                <?= (int) $profileCandidate['id'] ?>
                            "
                        >


                        <div
                            class="
                                pe-profile-summary
                            "
                        >

                            <div>

                                <span>
                                    CPF
                                </span>

                                <strong>
                                    <?= pe_h(
                                        pe_format_cpf(
                                            $profileCandidate['cpf']
                                            ?: $profileCandidate['cpf_informado']
                                            ?: '—'
                                        )
                                    ) ?>
                                </strong>

                            </div>


                            <div>

                                <span>
                                    Telefone
                                </span>

                                <strong>
                                    <?= pe_h(
                                        pe_format_phone(
                                            $profileCandidate['telefone']
                                            ?: '—'
                                        )
                                    ) ?>
                                </strong>

                            </div>


                            <div>

                                <span>
                                    Status
                                </span>

                                <strong>
                                    <?= pe_h(
                                        $profileCandidate['status']
                                    ) ?>
                                </strong>

                            </div>

                        </div>


                        <div
                            class="
                                pe-action-form-grid
                                pe-action-form-grid--2
                            "
                        >

                            <div>

                                <label
                                    class="
                                        form-label
                                    "
                                    for="
                                        profile-education
                                    "
                                >
                                    Nível de escolaridade
                                </label>

                                <input
                                    id="
                                        profile-education
                                    "
                                    class="
                                        form-control
                                    "
                                    name="
                                        nivel_escolaridade
                                    "
                                    maxlength="
                                        120
                                    "
                                    value="<?= pe_h(
                                        $profileEducation
                                    ) ?>"
                                    placeholder="
                                        Ex.: Ensino Médio completo
                                    "
                                >

                            </div>


                            <div>

                                <label
                                    class="
                                        form-label
                                    "
                                    for="
                                        profile-school-status
                                    "
                                >
                                    Situação escolar
                                </label>

                                <input
                                    id="
                                        profile-school-status
                                    "
                                    class="
                                        form-control
                                    "
                                    name="
                                        situacao_escolar
                                    "
                                    maxlength="
                                        120
                                    "
                                    value="<?= pe_h(
                                        $profileSchoolStatus
                                    ) ?>"
                                    placeholder="
                                        Ex.: Concluído, cursando
                                    "
                                >

                            </div>


                            <div>

                                <label
                                    class="
                                        form-label
                                    "
                                    for="
                                        profile-institution
                                    "
                                >
                                    Instituição de ensino
                                </label>

                                <input
                                    id="
                                        profile-institution
                                    "
                                    class="
                                        form-control
                                    "
                                    name="
                                        instituicao_ensino
                                    "
                                    maxlength="
                                        180
                                    "
                                    value="<?= pe_h(
                                        $profileInstitution
                                    ) ?>"
                                >

                            </div>


                            <div>

                                <label
                                    class="
                                        form-label
                                    "
                                    for="
                                        profile-period
                                    "
                                >
                                    Série / período
                                </label>

                                <input
                                    id="
                                        profile-period
                                    "
                                    class="
                                        form-control
                                    "
                                    name="
                                        serie_periodo
                                    "
                                    maxlength="
                                        80
                                    "
                                    value="<?= pe_h(
                                        $profilePeriod
                                    ) ?>"
                                >

                            </div>


                            <div>

                                <label
                                    class="
                                        form-label
                                    "
                                    for="
                                        profile-study-shift
                                    "
                                >
                                    Turno de estudo
                                </label>

                                <select
                                    id="
                                        profile-study-shift
                                    "
                                    class="
                                        form-select
                                    "
                                    name="
                                        turno_estudo
                                    "
                                >

                                    <option
                                        value=""
                                    >
                                        Não informado
                                    </option>


                                    <?php foreach (
                                        [
                                            'Matutino',
                                            'Vespertino',
                                            'Noturno',
                                            'Integral',
                                        ]
                                        as $shift
                                    ): ?>

                                        <option
                                            value="<?= pe_h(
                                                $shift
                                            ) ?>"
                                            <?= (
                                                $profileStudyShift
                                                === $shift
                                                    ? ' selected'
                                                    : ''
                                            ) ?>
                                        >
                                            <?= pe_h(
                                                $shift
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div>

                                <label
                                    class="
                                        form-label
                                    "
                                    for="
                                        profile-photo
                                    "
                                >
                                    Foto do candidato
                                </label>

                                <input
                                    id="
                                        profile-photo
                                    "
                                    class="
                                        form-control
                                    "
                                    type="
                                        file
                                    "
                                    name="
                                        foto
                                    "
                                    accept="
                                        image/jpeg,
                                        image/png,
                                        image/webp
                                    "
                                >

                                <small
                                    class="
                                        pe-field-help
                                    "
                                >
                                    JPG, PNG ou WEBP
                                    · máximo 3 MB.
                                </small>

                            </div>

                        </div>


                        <div
                            class="
                                pe-action-section-title
                            "
                        >
                            <span>
                                Atuação no programa
                            </span>
                        </div>


                        <div
                            class="
                                pe-action-form-grid
                                pe-action-form-grid--2
                            "
                        >

                            <div>

                                <label
                                    class="
                                        form-label
                                    "
                                    for="
                                        profile-workplace
                                    "
                                >
                                    Órgão / local de atuação
                                </label>

                                <input
                                    id="
                                        profile-workplace
                                    "
                                    class="
                                        form-control
                                    "
                                    name="
                                        local_atuacao
                                    "
                                    maxlength="
                                        180
                                    "
                                    value="<?= pe_h(
                                        $profileWorkplace
                                    ) ?>"
                                    placeholder="
                                        Ex.: SEMAS, SEMED, Saúde
                                    "
                                >

                            </div>


                            <div>

                                <label
                                    class="
                                        form-label
                                    "
                                    for="
                                        profile-work-shift
                                    "
                                >
                                    Turno de atuação
                                </label>

                                <select
                                    id="
                                        profile-work-shift
                                    "
                                    class="
                                        form-select
                                    "
                                    name="
                                        turno_atuacao
                                    "
                                >

                                    <option
                                        value=""
                                    >
                                        Não informado
                                    </option>


                                    <?php foreach (
                                        [
                                            'Matutino',
                                            'Vespertino',
                                            'Noturno',
                                            'Integral',
                                        ]
                                        as $shift
                                    ): ?>

                                        <option
                                            value="<?= pe_h(
                                                $shift
                                            ) ?>"
                                            <?= (
                                                $profileWorkShift
                                                === $shift
                                                    ? ' selected'
                                                    : ''
                                            ) ?>
                                        >
                                            <?= pe_h(
                                                $shift
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                        </div>


                        <footer
                            class="
                                pe-action-modal-footer
                            "
                        >

                            <button
                                type="
                                    button
                                "
                                class="
                                    btn
                                    btn-light
                                "
                                data-pe-dialog-close
                                data-clean-param="
                                    ficha
                                "
                            >
                                Cancelar
                            </button>


                            <button
                                class="
                                    btn
                                    btn-primary
                                "
                                type="
                                    submit
                                "
                            >
                                <i
                                    class="
                                        bi
                                        bi-save
                                    "
                                ></i>

                                Salvar ficha
                            </button>

                        </footer>

                    </form>

                </div>

            </div>

        </dialog>

    <?php endif; ?>


    <!-- ==========================================================
         PAGINAÇÃO
         ========================================================== -->

    <?php if (
        $list['pages'] > 1
    ): ?>

        <nav
            class="
                pe-no-print
                mt-3
            "
            aria-label="
                Paginação de candidatos
            "
        >

            <ul
                class="
                    pagination
                    pagination-sm
                    justify-content-end
                    flex-wrap
                "
            >

                <li
                    class="
                        page-item
                        <?= (
                            $list['page']
                            <= 1
                                ? ' disabled'
                                : ''
                        ) ?>
                    "
                >

                    <a
                        class="
                            page-link
                        "
                        href="<?= pe_h(
                            pe_candidate_page_url(
                                max(
                                    1,
                                    $list['page'] - 1
                                )
                            )
                        ) ?>"
                    >
                        Anterior
                    </a>

                </li>


                <?php

                $from =
                    max(
                        1,
                        $list['page'] - 2
                    );

                $to =
                    min(
                        $list['pages'],
                        $list['page'] + 2
                    );

                for (
                    $i = $from;
                    $i <= $to;
                    $i++
                ):

                ?>

                    <li
                        class="
                            page-item
                            <?= (
                                $i
                                === $list['page']
                                    ? ' active'
                                    : ''
                            ) ?>
                        "
                    >

                        <a
                            class="
                                page-link
                            "
                            href="<?= pe_h(
                                pe_candidate_page_url(
                                    $i
                                )
                            ) ?>"
                        >
                            <?= $i ?>
                        </a>

                    </li>

                <?php endfor; ?>


                <li
                    class="
                        page-item
                        <?= (
                            $list['page']
                            >= $list['pages']
                                ? ' disabled'
                                : ''
                        ) ?>
                    "
                >

                    <a
                        class="
                            page-link
                        "
                        href="<?= pe_h(
                            pe_candidate_page_url(
                                min(
                                    $list['pages'],
                                    $list['page'] + 1
                                )
                            )
                        ) ?>"
                    >
                        Próxima
                    </a>

                </li>

            </ul>

        </nav>

    <?php endif; ?>

</section>


<?php

$pageCustomContent =
    (string) ob_get_clean();