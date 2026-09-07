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
if ($returnUrl === '' || str_starts_with($returnUrl, 'http://') || str_starts_with($returnUrl, 'https://') || str_starts_with($returnUrl, '//')) {
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
    <link href="assets/css/prontuario-socioeconomico.css?v=1" rel="stylesheet">
</head>
<body class="socio-body">
<div class="socio-shell">
    <header class="socio-topbar">
        <a class="socio-brand" href="portal.php"><i class="bi bi-shield-check"></i><span><strong>SIGAS COARI</strong><small>Prontuário socioeconômico central</small></span></a>
        <div class="socio-user"><span><?= htmlspecialchars($app['user']->nome, ENT_QUOTES, 'UTF-8') ?></span><a class="btn btn-light btn-sm" href="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-arrow-left"></i> Voltar</a></div>
    </header>

    <main class="socio-main">
        <section class="socio-hero">
            <div><span class="socio-kicker"><i class="bi bi-person-vcard"></i> Cadastro único da pessoa</span><h1>Prontuário Socioeconômico</h1><p>Um único formulário para apoiar as análises dos programas e benefícios do SIGAS. A decisão de aptidão permanece exclusiva do fluxo de cada benefício.</p></div>
            <div class="socio-rule"><i class="bi bi-diagram-3"></i><div><strong>Uma pessoa, vários benefícios</strong><span>Não cadastre novamente quem já existe. Abra apenas uma nova solicitação.</span></div></div>
        </section>

        <section class="socio-search-card">
            <div class="socio-search-copy"><span>01</span><div><h2>Localizar pessoa</h2><p>Consulte o CPF no SIGAS e, quando autorizado, no ANEXO.</p></div></div>
            <div class="socio-search-form">
                <div><label class="form-label" for="socioCpfSearch">CPF</label><input class="form-control form-control-lg" id="socioCpfSearch" inputmode="numeric" maxlength="14" placeholder="000.000.000-00" value="<?= htmlspecialchars($initialCpf, ENT_QUOTES, 'UTF-8') ?>"></div>
                <button class="btn btn-primary btn-lg" type="button" id="socioSearchButton"><i class="bi bi-search"></i> Consultar</button>
            </div>
            <div class="socio-search-result" id="socioSearchResult" hidden></div>
        </section>

        <div class="socio-workspace" id="socioWorkspace" hidden>
            <div class="socio-form-column">
                <form id="socioForm" novalidate>
                    <input type="hidden" name="cpf" id="socioCpf">
                    <input type="hidden" name="origem" id="socioOrigem" value="sigas">
                    <input type="hidden" name="anexo_solicitante_id" id="socioAnexoId">
                    <input type="hidden" name="anexo_atualizado_em" id="socioAnexoUpdated">

                    <section class="socio-section">
                        <div class="socio-section-head"><span>02</span><div><h2>Identificação</h2><p>Dados básicos da pessoa. O CPF identifica o cadastro único.</p></div></div>
                        <div class="socio-grid">
                            <div class="col-span-8"><label class="form-label required">Nome completo</label><input class="form-control" name="nome" id="socioNome" required></div>
                            <div class="col-span-4"><label class="form-label">NIS</label><input class="form-control" name="nis" id="socioNis"></div>
                            <div class="col-span-4"><label class="form-label">RG</label><input class="form-control" name="rg" id="socioRg"></div>
                            <div class="col-span-4"><label class="form-label">Data de nascimento</label><input class="form-control" type="date" name="data_nascimento" id="socioNascimento"></div>
                            <div class="col-span-4"><label class="form-label">Telefone</label><input class="form-control" name="telefone" id="socioTelefone"></div>
                            <div class="col-span-6"><label class="form-label">E-mail</label><input class="form-control" type="email" name="email" id="socioEmail"></div>
                            <div class="col-span-6"><label class="form-label">Grupo tradicional</label><select class="form-select" name="grupo_tradicional" id="socioGrupo"><option value="">Não informado</option><option>Indígena</option><option>Quilombola</option><option>Cigano</option><option>Ribeirinho</option><option>Extrativista</option><option>Outro</option></select></div>
                        </div>
                    </section>

                    <section class="socio-section">
                        <div class="socio-section-head"><span>03</span><div><h2>Endereço e território</h2><p>Informações utilizadas para referência territorial e visitas.</p></div></div>
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

                    <section class="socio-section">
                        <div class="socio-section-head"><span>04</span><div><h2>Situação socioeconômica</h2><p>Renda, escolaridade, trabalho, deficiência e benefícios já recebidos.</p></div></div>
                        <div class="socio-grid">
                            <div class="col-span-6"><label class="form-label">Escolaridade</label><select class="form-select" name="escolaridade" id="socioEscolaridade"><option value="">Não informado</option><option>Não alfabetizado</option><option>Fundamental incompleto</option><option>Fundamental completo</option><option>Médio incompleto</option><option>Médio completo</option><option>Superior incompleto</option><option>Superior completo</option></select></div>
                            <div class="col-span-6"><label class="form-label">Situação de trabalho</label><select class="form-select" name="situacao_trabalho" id="socioTrabalho"><option value="">Não informado</option><option>Empregado(a)</option><option>Desempregado(a)</option><option>Autônomo(a)</option><option>Trabalho informal</option><option>Aposentado(a)/Pensionista</option><option>Estudante</option><option>Outro</option></select></div>
                            <div class="col-span-6"><label class="form-label">Ocupação</label><input class="form-control" name="ocupacao" id="socioOcupacao"></div>
                            <div class="col-span-3"><label class="form-label">Renda individual</label><input class="form-control" name="renda_individual" id="socioRendaIndividual" inputmode="decimal"></div>
                            <div class="col-span-3"><label class="form-label">Renda familiar</label><input class="form-control" name="renda_familiar" id="socioRendaFamiliar" inputmode="decimal"></div>
                            <div class="col-span-4"><label class="form-label">Total de moradores</label><input class="form-control" type="number" min="1" name="quantidade_membros" id="socioMembrosTotal" value="1"></div>
                            <div class="col-span-8 socio-checkline"><label><input class="form-check-input" type="checkbox" name="possui_deficiencia" id="socioDeficiencia"> Pessoa com deficiência</label><input class="form-control" name="deficiencia_descricao" id="socioDeficienciaDescricao" placeholder="Descrição, quando aplicável"></div>
                        </div>
                        <div class="socio-choice-block"><h3>Benefícios e transferências</h3><div class="socio-check-grid" id="socioBeneficios"><label><input type="checkbox" value="Bolsa Família"> Bolsa Família</label><label><input type="checkbox" value="BPC"> BPC</label><label><input type="checkbox" value="Benefício municipal"> Benefício municipal</label><label><input type="checkbox" value="Benefício estadual"> Benefício estadual</label><label><input type="checkbox" value="Outro"> Outro</label></div></div>
                    </section>

                    <section class="socio-section">
                        <div class="socio-section-head"><span>05</span><div><h2>Condições habitacionais</h2><p>Características da moradia e situações de risco do território.</p></div></div>
                        <div class="socio-grid">
                            <div class="col-span-6"><label class="form-label">Situação da moradia</label><select class="form-select" name="tipo_moradia" id="socioMoradia"><option value="">Não informado</option><option>Própria</option><option>Alugada</option><option>Cedida</option><option>Ocupação/invasão</option><option>Área de risco</option><option>Outro</option></select></div>
                            <div class="col-span-6"><label class="form-label">Material/condição</label><select class="form-select" name="material_moradia" id="socioMaterial"><option value="">Não informado</option><option>Alvenaria</option><option>Madeira</option><option>Mista</option><option>Precária</option><option>Outro</option></select></div>
                            <div class="col-span-3"><label class="form-label">Cômodos</label><input class="form-control" type="number" min="0" name="numero_comodos" id="socioComodos"></div>
                            <div class="col-span-3"><label class="form-label">Água</label><select class="form-select" name="abastecimento_agua" id="socioAgua"><option value="">-</option><option>Rede pública</option><option>Poço</option><option>Tratada</option><option>Não tratada</option><option>Outro</option></select></div>
                            <div class="col-span-3"><label class="form-label">Energia elétrica</label><select class="form-select" name="energia_eletrica" id="socioEnergia"><option value="">-</option><option>Sim</option><option>Não</option><option>Irregular</option></select></div>
                            <div class="col-span-3"><label class="form-label">Coleta de lixo</label><select class="form-select" name="coleta_lixo" id="socioLixo"><option value="">-</option><option>Sim</option><option>Não</option><option>Irregular</option></select></div>
                            <div class="col-span-6"><label class="form-label">Esgotamento sanitário</label><select class="form-select" name="esgotamento_sanitario" id="socioEsgoto"><option value="">-</option><option>Rede pública</option><option>Fossa séptica</option><option>Fossa rudimentar</option><option>Céu aberto</option><option>Outro</option></select></div>
                            <div class="col-span-6 socio-checkline"><label><input class="form-check-input" type="checkbox" name="area_risco" id="socioAreaRisco"> Moradia/território em área de risco</label><input class="form-control" name="area_risco_descricao" id="socioAreaRiscoDescricao" placeholder="Descreva o risco"></div>
                        </div>
                    </section>

                    <section class="socio-section">
                        <div class="socio-section-head"><span>06</span><div><h2>Vulnerabilidades</h2><p>Assinale somente condições verificadas ou declaradas na entrevista.</p></div></div>
                        <div class="socio-check-grid" id="socioVulnerabilidades"><label><input type="checkbox" value="Renda inferior a 1/2 salário mínimo per capita"> Renda inferior a 1/2 salário mínimo per capita</label><label><input type="checkbox" value="Desemprego prolongado"> Desemprego prolongado</label><label><input type="checkbox" value="Trabalho informal ou precário"> Trabalho informal ou precário</label><label><input type="checkbox" value="Moradia em área de risco"> Moradia em área de risco</label><label><input type="checkbox" value="Situação de violência"> Situação de violência</label><label><input type="checkbox" value="Uso abusivo de álcool/drogas"> Uso abusivo de álcool/drogas</label><label><input type="checkbox" value="Pessoa com deficiência"> Pessoa com deficiência</label><label><input type="checkbox" value="Outra vulnerabilidade"> Outra vulnerabilidade</label></div>
                    </section>

                    <section class="socio-section">
                        <div class="socio-section-head"><span>07</span><div><h2>Composição familiar</h2><p>Dependentes podem ser registrados mesmo sem CPF; isso não cria novos cadastros de pessoa automaticamente.</p></div></div>
                        <div id="socioFamilyMembers" class="socio-family-list"></div>
                        <button class="btn btn-light" type="button" id="socioAddMember"><i class="bi bi-person-plus"></i> Adicionar familiar</button>
                    </section>

                    <section class="socio-section">
                        <div class="socio-section-head"><span>08</span><div><h2>Resumo da entrevista</h2><p>Registro técnico que poderá ser consultado pelos módulos autorizados.</p></div></div>
                        <label class="form-label">Resumo social</label><textarea class="form-control" name="resumo_social" id="socioResumo" rows="5" maxlength="5000"></textarea>
                        <label class="form-label mt-3">Observações complementares</label><textarea class="form-control" name="observacoes" id="socioObservacoes" rows="3" maxlength="3000"></textarea>
                        <div class="socio-decision-warning"><i class="bi bi-shield-exclamation"></i><div><strong>Este formulário não aprova benefício.</strong><span>Aptidão, indeferimento ou pendência são decididos e registrados dentro da solicitação específica de cada programa.</span></div></div>
                    </section>

                    <?php if ($canEdit): ?>
                    <div class="socio-savebar"><div><strong id="socioSaveState">Prontuário ainda não salvo nesta sessão</strong><span>As alterações ficam registradas no histórico.</span></div><button class="btn btn-primary btn-lg" type="submit" id="socioSaveButton"><i class="bi bi-floppy"></i> Salvar prontuário</button></div>
                    <?php endif; ?>
                </form>
            </div>

            <aside class="socio-side-column">
                <section class="socio-side-card"><div class="socio-side-head"><i class="bi bi-database-check"></i><div><h3>Origem dos dados</h3><p id="socioSourceText">Aguardando consulta</p></div></div><?php if ($canImport): ?><button class="btn btn-outline-primary w-100" id="socioUseAnexo" type="button" hidden><i class="bi bi-cloud-download"></i> Usar dados do ANEXO</button><?php endif; ?><div class="socio-source-note">O SIGAS apenas lê o ANEXO. Nenhuma alteração é enviada para a base externa.</div></section>
                <section class="socio-side-card"><div class="socio-side-head"><i class="bi bi-clock-history"></i><div><h3>Validade da entrevista</h3><p id="socioProfileState">Sem prontuário consultado</p></div></div><div id="socioInterviewInfo" class="socio-mini-list"></div></section>
                <section class="socio-side-card"><div class="socio-side-head"><i class="bi bi-grid"></i><div><h3>Benefícios e solicitações</h3><p>A mesma pessoa pode aparecer em vários módulos.</p></div></div><div id="socioBenefitsList" class="socio-benefit-list"><div class="socio-empty-mini">Consulte um CPF.</div></div></section>
            </aside>
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
<script src="assets/js/prontuario-socioeconomico.js?v=1"></script>
</body>
</html>
