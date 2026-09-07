<?php

declare(strict_types=1);

use App\Core\Csrf;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/frontend/support/operational-program-access.php';

if (!sigas_operational_can('socioeconomico.visualizar')) {
    http_response_code(403);
    $errorTitle = 'Acesso não autorizado';
    $errorMessage = 'Seu perfil não possui acesso ao prontuário socioeconômico.';
    require __DIR__ . '/frontend/layouts/error-layout.php';
    return;
}

$app = sigas_operational_access_app();
$canEdit = sigas_operational_can('socioeconomico.editar');
$canImport = sigas_operational_can('socioeconomico.importar_anexo');
$csrf = Csrf::token('socioeconomico_salvar');
$initialCpf = preg_replace('/\D+/', '', (string) ($_GET['cpf'] ?? '')) ?? '';
$returnUrl = trim((string) ($_GET['retorno'] ?? 'portal.php'));
if (
    $returnUrl === ''
    || str_contains($returnUrl, "\0")
    || preg_match('/^[a-z][a-z0-9+.-]*:/i', $returnUrl) === 1
    || str_starts_with($returnUrl, '//')
    || str_starts_with($returnUrl, '/')
    || str_contains($returnUrl, '..')
) {
    $returnUrl = 'portal.php';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#176b3a">
    <title>SIGAS Coari — Prontuário Socioeconômico</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/prontuario-socioeconomico.css?v=2" rel="stylesheet">
</head>
<body class="socio-body">
<div class="socio-shell">
    <header class="socio-topbar">
        <a class="socio-brand" href="portal.php" aria-label="Voltar ao portal SIGAS">
            <i class="bi bi-shield-check"></i>
            <span><strong>SIGAS COARI</strong><small>Prontuário socioeconômico central</small></span>
        </a>
        <div class="socio-user">
            <span><?= htmlspecialchars($app['user']->nome, ENT_QUOTES, 'UTF-8') ?></span>
            <a class="btn btn-light btn-sm" href="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>
    </header>

    <main class="socio-main">
        <section class="socio-hero">
            <div>
                <span class="socio-kicker"><i class="bi bi-person-vcard"></i> Cadastro social central</span>
                <h1>Prontuário Socioeconômico</h1>
                <p>Consulte uma pessoa uma única vez, mantenha sua realidade socioeconômica atualizada e reutilize o prontuário nas solicitações dos diferentes programas do SIGAS.</p>
            </div>
            <div class="socio-rule">
                <i class="bi bi-diagram-3"></i>
                <div><strong>Uma pessoa, vários benefícios</strong><span>O prontuário descreve a situação social. A decisão de aptidão continua dentro de cada benefício.</span></div>
            </div>
        </section>

        <section class="socio-search-card" aria-labelledby="socioSearchTitle">
            <div class="socio-search-copy">
                <span>01</span>
                <div><h2 id="socioSearchTitle">Localizar pessoa</h2><p>Informe o CPF. O SIGAS consulta primeiro o cadastro central e, quando autorizado, também o ANEXO.</p></div>
            </div>
            <div class="socio-search-form">
                <div>
                    <label class="form-label" for="socioCpfSearch">CPF</label>
                    <input class="form-control form-control-lg" id="socioCpfSearch" inputmode="numeric" maxlength="14" placeholder="000.000.000-00" value="<?= htmlspecialchars($initialCpf, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                </div>
                <button class="btn btn-primary btn-lg" type="button" id="socioSearchButton"><i class="bi bi-search"></i> Consultar pessoa</button>
            </div>
            <div class="socio-search-result" id="socioSearchResult" hidden></div>
        </section>

        <section class="socio-person-card" id="socioPersonCard" hidden aria-live="polite">
            <div class="socio-person-main">
                <div class="socio-avatar" id="socioPersonInitials">—</div>
                <div class="socio-person-copy">
                    <div class="socio-person-badges" id="socioPersonBadges"></div>
                    <h2 id="socioPersonName">Pessoa localizada</h2>
                    <p id="socioPersonIdentity">—</p>
                </div>
            </div>
            <div class="socio-person-metrics" id="socioPersonMetrics"></div>
            <div class="socio-person-actions">
                <button class="btn btn-light" type="button" id="socioViewButton"><i class="bi bi-eye"></i> Visualizar prontuário</button>
                <?php if ($canEdit): ?>
                <button class="btn btn-primary" type="button" id="socioEditButton"><i class="bi bi-pencil-square"></i> <span>Editar / atualizar</span></button>
                <?php endif; ?>
                <?php if ($canImport): ?>
                <button class="btn btn-outline-primary" id="socioUseAnexo" type="button" hidden><i class="bi bi-cloud-download"></i> Usar dados do ANEXO</button>
                <?php endif; ?>
            </div>
        </section>

        <div class="socio-workspace" id="socioWorkspace" hidden>
            <section class="socio-record-shell">
                <header class="socio-record-header">
                    <div>
                        <span class="socio-record-eyebrow">Prontuário da pessoa</span>
                        <h2 id="socioRecordTitle">Visão geral</h2>
                        <p id="socioRecordSubtitle">Informações consolidadas para consulta técnica.</p>
                    </div>
                    <div class="socio-mode-badge" id="socioModeBadge"><i class="bi bi-eye"></i> Consulta</div>
                </header>

                <nav class="socio-tabs" aria-label="Seções do prontuário" id="socioTabs">
                    <button type="button" class="active" data-socio-tab="overview"><i class="bi bi-grid-1x2"></i><span>Visão geral</span></button>
                    <button type="button" data-socio-tab="identity"><i class="bi bi-person"></i><span>Identificação</span></button>
                    <button type="button" data-socio-tab="family"><i class="bi bi-people"></i><span>Família</span></button>
                    <button type="button" data-socio-tab="income"><i class="bi bi-wallet2"></i><span>Renda e trabalho</span></button>
                    <button type="button" data-socio-tab="housing"><i class="bi bi-house"></i><span>Moradia</span></button>
                    <button type="button" data-socio-tab="vulnerabilities"><i class="bi bi-shield-exclamation"></i><span>Vulnerabilidades</span></button>
                    <button type="button" data-socio-tab="benefits"><i class="bi bi-box2-heart"></i><span>Benefícios</span></button>
                    <button type="button" data-socio-tab="history"><i class="bi bi-clock-history"></i><span>Histórico</span></button>
                </nav>

                <form id="socioForm" novalidate>
                    <input type="hidden" name="cpf" id="socioCpf">
                    <input type="hidden" name="origem" id="socioOrigem" value="sigas">
                    <input type="hidden" name="anexo_solicitante_id" id="socioAnexoId">
                    <input type="hidden" name="anexo_atualizado_em" id="socioAnexoUpdated">

                    <section class="socio-tab-panel active" data-socio-panel="overview">
                        <div class="socio-overview-grid" id="socioOverviewStats"></div>

                        <div class="socio-overview-columns">
                            <article class="socio-info-card">
                                <div class="socio-info-card__head"><i class="bi bi-activity"></i><div><h3>Situação do prontuário</h3><p>Validade, origem e acompanhamento da entrevista.</p></div></div>
                                <div id="socioOverviewInterview" class="socio-detail-list"></div>
                            </article>
                            <article class="socio-info-card">
                                <div class="socio-info-card__head"><i class="bi bi-geo-alt"></i><div><h3>Contato e território</h3><p>Dados rápidos para localização e atendimento.</p></div></div>
                                <div id="socioOverviewContact" class="socio-detail-list"></div>
                            </article>
                        </div>

                        <article class="socio-info-card socio-summary-card">
                            <div class="socio-info-card__head"><i class="bi bi-journal-text"></i><div><h3>Resumo social da entrevista</h3><p>Síntese técnica compartilhada com os módulos autorizados.</p></div></div>
                            <div class="socio-read-summary" id="socioOverviewSummary">Nenhum resumo registrado.</div>
                            <div class="socio-edit-only mt-3">
                                <label class="form-label" for="socioResumo">Resumo social</label>
                                <textarea class="form-control" name="resumo_social" id="socioResumo" rows="5" maxlength="5000"></textarea>
                                <label class="form-label mt-3" for="socioObservacoes">Observações complementares</label>
                                <textarea class="form-control" name="observacoes" id="socioObservacoes" rows="3" maxlength="3000"></textarea>
                            </div>
                            <div class="socio-decision-warning"><i class="bi bi-shield-check"></i><div><strong>O prontuário não aprova benefícios.</strong><span>Aptidão, pendência ou indeferimento são registrados exclusivamente na solicitação de cada programa.</span></div></div>
                        </article>
                    </section>

                    <section class="socio-tab-panel" data-socio-panel="identity" hidden>
                        <div class="socio-section-heading"><div><span>02</span><div><h3>Identificação e endereço</h3><p>Dados básicos da pessoa e referência territorial.</p></div></div></div>
                        <div class="socio-grid">
                            <div class="col-span-8"><label class="form-label required">Nome completo</label><input class="form-control" name="nome" id="socioNome" required></div>
                            <div class="col-span-4"><label class="form-label">NIS</label><input class="form-control" name="nis" id="socioNis"></div>
                            <div class="col-span-4"><label class="form-label">RG</label><input class="form-control" name="rg" id="socioRg"></div>
                            <div class="col-span-4"><label class="form-label">Data de nascimento</label><input class="form-control" type="date" name="data_nascimento" id="socioNascimento"></div>
                            <div class="col-span-4"><label class="form-label">Telefone</label><input class="form-control" name="telefone" id="socioTelefone"></div>
                            <div class="col-span-6"><label class="form-label">E-mail</label><input class="form-control" type="email" name="email" id="socioEmail"></div>
                            <div class="col-span-6"><label class="form-label">Grupo tradicional</label><select class="form-select" name="grupo_tradicional" id="socioGrupo"><option value="">Não informado</option><option>Indígena</option><option>Quilombola</option><option>Cigano</option><option>Ribeirinho</option><option>Extrativista</option><option>Outro</option></select></div>
                        </div>
                        <div class="socio-subsection-title"><i class="bi bi-geo-alt"></i><span>Endereço e território</span></div>
                        <div class="socio-grid">
                            <div class="col-span-8"><label class="form-label">Logradouro</label><input class="form-control" name="logradouro" id="socioLogradouro"></div>
                            <div class="col-span-4"><label class="form-label">Número</label><input class="form-control" name="numero" id="socioNumero"></div>
                            <div class="col-span-6"><label class="form-label">Bairro</label><input class="form-control" name="bairro" id="socioBairro"></div>
                            <div class="col-span-6"><label class="form-label">Comunidade</label><input class="form-control" name="comunidade" id="socioComunidade"></div>
                            <div class="col-span-6"><label class="form-label">Complemento</label><input class="form-control" name="complemento" id="socioComplemento"></div>
                            <div class="col-span-6"><label class="form-label">CEP</label><input class="form-control" name="cep" id="socioCep"></div>
                            <div class="col-span-12"><label class="form-label">Ponto de referência</label><input class="form-control" name="ponto_referencia" id="socioReferencia"></div>
                        </div>
                    </section>

                    <section class="socio-tab-panel" data-socio-panel="family" hidden>
                        <div class="socio-section-heading"><div><span>03</span><div><h3>Composição familiar</h3><p>Registre quem compõe o domicílio. Dependentes não precisam possuir CPF para constar na entrevista.</p></div></div><button class="btn btn-light socio-edit-only" type="button" id="socioAddMember"><i class="bi bi-person-plus"></i> Adicionar familiar</button></div>
                        <div id="socioFamilyMembers" class="socio-family-list"></div>
                        <div class="socio-empty-state" id="socioFamilyEmpty"><i class="bi bi-people"></i><strong>Nenhum familiar informado</strong><span>O responsável familiar já é considerado na composição do domicílio.</span></div>
                    </section>

                    <section class="socio-tab-panel" data-socio-panel="income" hidden>
                        <div class="socio-section-heading"><div><span>04</span><div><h3>Renda, trabalho e escolaridade</h3><p>Informações utilizadas na leitura socioeconômica, sem decisão automática de benefício.</p></div></div></div>
                        <div class="socio-grid">
                            <div class="col-span-6"><label class="form-label">Escolaridade</label><select class="form-select" name="escolaridade" id="socioEscolaridade"><option value="">Não informado</option><option>Não alfabetizado</option><option>Fundamental incompleto</option><option>Fundamental completo</option><option>Médio incompleto</option><option>Médio completo</option><option>Superior incompleto</option><option>Superior completo</option></select></div>
                            <div class="col-span-6"><label class="form-label">Situação de trabalho</label><select class="form-select" name="situacao_trabalho" id="socioTrabalho"><option value="">Não informado</option><option>Empregado(a)</option><option>Desempregado(a)</option><option>Autônomo(a)</option><option>Trabalho informal</option><option>Aposentado(a)/Pensionista</option><option>Estudante</option><option>Outro</option></select></div>
                            <div class="col-span-6"><label class="form-label">Ocupação</label><input class="form-control" name="ocupacao" id="socioOcupacao"></div>
                            <div class="col-span-3"><label class="form-label">Renda individual</label><input class="form-control" name="renda_individual" id="socioRendaIndividual" inputmode="decimal"></div>
                            <div class="col-span-3"><label class="form-label">Renda familiar</label><input class="form-control" name="renda_familiar" id="socioRendaFamiliar" inputmode="decimal"></div>
                            <div class="col-span-4"><label class="form-label">Total de moradores</label><input class="form-control" type="number" min="1" name="quantidade_membros" id="socioMembrosTotal" value="1"></div>
                            <div class="col-span-8 socio-checkline"><label><input class="form-check-input" type="checkbox" name="possui_deficiencia" id="socioDeficiencia"> Pessoa com deficiência</label><input class="form-control" name="deficiencia_descricao" id="socioDeficienciaDescricao" placeholder="Descrição, quando aplicável"></div>
                        </div>
                    </section>

                    <section class="socio-tab-panel" data-socio-panel="housing" hidden>
                        <div class="socio-section-heading"><div><span>05</span><div><h3>Condições habitacionais</h3><p>Características da moradia, infraestrutura e exposição territorial.</p></div></div></div>
                        <div class="socio-grid">
                            <div class="col-span-6"><label class="form-label">Situação da moradia</label><select class="form-select" name="tipo_moradia" id="socioMoradia"><option value="">Não informado</option><option>Própria</option><option>Alugada</option><option>Cedida</option><option>Ocupação/invasão</option><option>Área de risco</option><option>Outro</option></select></div>
                            <div class="col-span-6"><label class="form-label">Material/condição</label><select class="form-select" name="material_moradia" id="socioMaterial"><option value="">Não informado</option><option>Alvenaria</option><option>Madeira</option><option>Mista</option><option>Precária</option><option>Outro</option></select></div>
                            <div class="col-span-3"><label class="form-label">Cômodos</label><input class="form-control" type="number" min="0" name="numero_comodos" id="socioComodos"></div>
                            <div class="col-span-3"><label class="form-label">Água</label><select class="form-select" name="abastecimento_agua" id="socioAgua"><option value="">Não informado</option><option>Rede pública</option><option>Poço</option><option>Tratada</option><option>Não tratada</option><option>Outro</option></select></div>
                            <div class="col-span-3"><label class="form-label">Energia elétrica</label><select class="form-select" name="energia_eletrica" id="socioEnergia"><option value="">Não informado</option><option>Sim</option><option>Não</option><option>Irregular</option></select></div>
                            <div class="col-span-3"><label class="form-label">Coleta de lixo</label><select class="form-select" name="coleta_lixo" id="socioLixo"><option value="">Não informado</option><option>Sim</option><option>Não</option><option>Irregular</option></select></div>
                            <div class="col-span-6"><label class="form-label">Esgotamento sanitário</label><select class="form-select" name="esgotamento_sanitario" id="socioEsgoto"><option value="">Não informado</option><option>Rede pública</option><option>Fossa séptica</option><option>Fossa rudimentar</option><option>Céu aberto</option><option>Outro</option></select></div>
                            <div class="col-span-6 socio-checkline"><label><input class="form-check-input" type="checkbox" name="area_risco" id="socioAreaRisco"> Moradia/território em área de risco</label><input class="form-control" name="area_risco_descricao" id="socioAreaRiscoDescricao" placeholder="Descreva o risco"></div>
                        </div>
                    </section>

                    <section class="socio-tab-panel" data-socio-panel="vulnerabilities" hidden>
                        <div class="socio-section-heading"><div><span>06</span><div><h3>Vulnerabilidades</h3><p>Assinale condições verificadas ou declaradas durante a entrevista social.</p></div></div></div>
                        <div class="socio-check-grid" id="socioVulnerabilidades">
                            <label><input type="checkbox" value="Renda inferior a 1/2 salário mínimo per capita"> Renda inferior a 1/2 salário mínimo per capita</label>
                            <label><input type="checkbox" value="Desemprego prolongado"> Desemprego prolongado</label>
                            <label><input type="checkbox" value="Trabalho informal ou precário"> Trabalho informal ou precário</label>
                            <label><input type="checkbox" value="Moradia em área de risco"> Moradia em área de risco</label>
                            <label><input type="checkbox" value="Situação de violência"> Situação de violência</label>
                            <label><input type="checkbox" value="Uso abusivo de álcool/drogas"> Uso abusivo de álcool/drogas</label>
                            <label><input type="checkbox" value="Pessoa com deficiência"> Pessoa com deficiência</label>
                            <label><input type="checkbox" value="Outra vulnerabilidade"> Outra vulnerabilidade</label>
                        </div>
                    </section>

                    <section class="socio-tab-panel" data-socio-panel="benefits" hidden>
                        <div class="socio-section-heading"><div><span>07</span><div><h3>Benefícios e solicitações</h3><p>Transferências declaradas no prontuário e solicitações registradas nos módulos do SIGAS.</p></div></div></div>
                        <div class="socio-subsection-title"><i class="bi bi-cash-stack"></i><span>Benefícios e transferências declarados</span></div>
                        <div class="socio-check-grid" id="socioBeneficios">
                            <label><input type="checkbox" value="Bolsa Família"> Bolsa Família</label>
                            <label><input type="checkbox" value="BPC"> BPC</label>
                            <label><input type="checkbox" value="Benefício municipal"> Benefício municipal</label>
                            <label><input type="checkbox" value="Benefício estadual"> Benefício estadual</label>
                            <label><input type="checkbox" value="Outro"> Outro</label>
                        </div>
                        <div class="socio-subsection-title mt-4"><i class="bi bi-grid"></i><span>Solicitações nos módulos</span></div>
                        <div id="socioBenefitsList" class="socio-benefit-list"><div class="socio-empty-mini">Consulte um CPF.</div></div>
                    </section>

                    <section class="socio-tab-panel" data-socio-panel="history" hidden>
                        <div class="socio-section-heading"><div><span>08</span><div><h3>Histórico do prontuário</h3><p>Alterações confirmadas, origem da informação e responsável pelo registro.</p></div></div></div>
                        <div id="socioHistoryList" class="socio-history-list"><div class="socio-empty-state"><i class="bi bi-clock-history"></i><strong>Nenhuma versão anterior</strong><span>O histórico será criado quando o prontuário for salvo ou atualizado.</span></div></div>
                    </section>

                    <?php if ($canEdit): ?>
                    <div class="socio-savebar" id="socioSavebar" hidden>
                        <div><strong id="socioSaveState">Modo de edição ativo</strong><span>Ao salvar, a versão anterior permanece registrada no histórico.</span></div>
                        <div class="socio-save-actions"><button class="btn btn-light" type="button" id="socioCancelEdit"><i class="bi bi-x-lg"></i> Cancelar</button><button class="btn btn-primary" type="submit" id="socioSaveButton"><i class="bi bi-floppy"></i> Salvar atualização</button></div>
                    </div>
                    <?php endif; ?>
                </form>
            </section>
        </div>
    </main>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" id="socioToastContainer"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.SIGAS_SOCIO = <?= json_encode([
    'csrf' => $csrf,
    'canEdit' => $canEdit,
    'canImport' => $canImport,
    'initialCpf' => $initialCpf,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="assets/js/prontuario-socioeconomico.js?v=2"></script>
</body>
</html>
