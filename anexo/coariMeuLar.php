<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Prefeitura de Coari • Secretaria de Terras e Habitação</title>

    <link rel="shortcut icon" href="./dist/assets/images/logo/logo_pmc_2025.jpg" type="image/x-icon">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />

    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css" />

    <!-- Favicon -->
    <link rel="shortcut icon" href="../../assets/images/favicon.ico">

    <!-- Library / Plugin Css Build -->
    <link rel="stylesheet" href="./dist/assets/css/core/libs.min.css">

    <!-- Hope Ui Design System Css -->
    <link rel="stylesheet" href="./dist/assets/css/hope-ui.min.css?v=4.0.0">

    <!-- Custom Css -->
    <link rel="stylesheet" href="./dist/assets/css/custom.min.css?v=4.0.0">

    <!-- Dark Css -->
    <link rel="stylesheet" href="./dist/assets/css/assets/css/dark.min.css">

    <!-- Customizer Css -->
    <link rel="stylesheet" href="./dist/assets/css/customizer.min.css">

    <!-- RTL Css -->
    <link rel="stylesheet" href="./dist/assets/css/rtl.min.css">

    <link rel="stylesheet" href="./dist/assets/css/form.css">


</head>

<body>

    <!-- HEADER -->
    <header class="top-header">

        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between py-2 px-3 px-md-4">
                <div class="brand-wrap">
                    <img src="./dist/assets/images/logo/logo_pmc_2025.jpg" alt="Prefeitura de Coari" class="img-fluid">
                </div>
                <div class="brand-wrap">
                    <img src="./dist/assets/images/logo/logo-smth-2025.jpg" alt="Secretaria de Terras e Habitação"
                        class="img-fluid">
                </div>
            </div>
        </div>

    </header>
    <!-- /HEADER -->

    <!-- CONTEÚDO -->
    <main class="container my-4 flex-grow-1">

        <div>

            <div class="row">
                <div class="col-sm-12 col-lg-12">

                    <div class="card">

                        <div class="card-body">

                            <!-- FORM-WIZARD -->
                            <form id="form-wizard1" class="mt-3 text-center" action="./dist/dados/processarDados.php" method="POST" novalidate>

                                <!-- TOP-TAB-LIST -->
                                <ul id="top-tab-list" class="p-0 row list-inline">

                                    <!-- INFORMAÇÕES PESSOAIS -->
                                    <li class="mb-2 col-lg-3 col-md-6 text-start active" id="infoPessoais">
                                        <a href="javascript:void(0);" class="d-flex align-items-center">
                                            <div class="iq-icon me-3 d-flex justify-content-center align-items-center">
                                                <svg class="svg-icon icon-20" xmlns="http://www.w3.org/2000/svg" width="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
                                                </svg>
                                            </div>
                                            <span class="dark-wizard ms-2">Info. Pessoais</span>
                                        </a>
                                    </li>
                                    <!-- /INFORMAÇÕES PESSOAIS -->

                                    <!-- INFORMAÇÕES ADICIONAIS -->
                                    <li id="infoAdicionais" class="mb-2 col-lg-3 col-md-6 text-start">
                                        <a href="javascript:void(0);" class="d-flex align-items-center">
                                            <div class="iq-icon me-3 d-flex justify-content-center align-items-center">
                                                <svg class="svg-icon icon-20" xmlns="http://www.w3.org/2000/svg" width="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 20a8 8 0 100-16 8 8 0 000 16z" />
                                                </svg>
                                            </div>
                                            <span class="dark-wizard ms-2">Info. Adicionais</span>
                                        </a>
                                    </li>
                                    <!-- /INFORMAÇÕES PESSOAIS -->

                                    <!-- CONTATO E ENDEREÇO -->
                                    <li id="contEndereco" class="mb-2 col-lg-3 col-md-6 text-start">
                                        <a href="javascript:void(0);" class="d-flex align-items-center">
                                            <div class="iq-icon me-3 d-flex justify-content-center align-items-center">
                                                <svg class="svg-icon icon-20" xmlns="http://www.w3.org/2000/svg" width="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                            <span class="dark-wizard ms-2">Contato e Endereço</span>
                                        </a>
                                    </li>
                                    <!-- /CONTATO E ENDEREÇO -->

                                    <!-- COMPOSIÇÃO FAMILIAR -->
                                    <li id="compFamiliar" class="mb-2 col-lg-3 col-md-6 text-start">
                                        <a href="javascript:void(0);" class="d-flex align-items-center">
                                            <div class="iq-icon me-3 d-flex justify-content-center align-items-center">
                                                <svg class="svg-icon icon-20" xmlns="http://www.w3.org/2000/svg" width="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 17a4 4 0 01-8 0M12 7a4 4 0 110 8 4 4 0 010-8zm6 7v2a2 2 0 01-2 2h-2m-4 0H6a2 2 0 01-2-2v-2" />
                                                </svg>
                                            </div>
                                            <span class="dark-wizard ms-2">Comp. Familiar</span>
                                        </a>
                                    </li>
                                    <!-- /COMPOSIÇÃO FAMILIAR -->

                                    <!-- RESUMO -->
                                    <li id="confirm" class="mb-2 col-lg-3 col-md-6 text-start">
                                        <a href="javascript:void(0);" class="d-flex align-items-center">
                                            <div class="iq-icon me-3">
                                                <svg class="svg-icon icon-20" xmlns="http://www.w3.org/2000/svg" width="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </div>
                                            <span class="dark-wizard ms-2">Resumo</span>
                                        </a>
                                    </li>
                                    <!-- /RESUMO -->

                                </ul>
                                <!-- /TOP-TAB-LIST -->

                                <!-- STEP 1: DADOS PRINCIPAIS -->
                                <fieldset>
                                    <div class="form-card text-start">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label for="p_nome" class="form-label">Nome Completo <sup class="text-danger">*</sup></label>
                                                <input id="p_nome" name="p_nome" type="text" maxlength="100"
                                                    class="form-control text-uppercase" wire:model="nome" required autofocus autocomplete="name">
                                            </div>

                                            <div class="col-md-8">
                                                <label for="p_nome_social" class="form-label">Nome Social</label>
                                                <input id="p_nome_social" name="p_nome_social" type="text" maxlength="60"
                                                    class="form-control" wire:model="nome_social">
                                            </div>

                                            <div class="col-md-4">
                                                <label for="p_cpf" class="form-label">CPF <sup class="text-danger">*</sup></label>
                                                <input id="p_cpf" name="p_cpf" type="text" class="form-control"
                                                    wire:model="cpf" x-mask="999.999.999-99" maxlength="14" placeholder="000.000.000-00" required>
                                            </div>

                                            <div class="col-md-3">
                                                <label for="p_rg" class="form-label">RG</label>
                                                <input id="p_rg" name="p_rg" type="text" class="form-control"
                                                    wire:model="rg" x-mask="9999999999" maxlength="10">
                                            </div>

                                            <div class="col-md-3">
                                                <label for="p_orgao_emissor_rg" class="form-label">Órgão Emissor</label>
                                                <input id="p_orgao_emissor_rg" name="p_orgao_emissor_rg" type="text"
                                                    maxlength="20" class="form-control" wire:model="orgao_emissor_rg">
                                            </div>

                                            <div class="col-md-3">
                                                <label for="p_uf_emissor_rg" class="form-label">UF</label>
                                                <select id="p_uf_emissor_rg" name="p_uf_emissor_rg" class="form-select"
                                                    wire:model="uf_emissor_rg">
                                                    <option value="">Selecione...</option>
                                                    <option value="AC">Acre</option>
                                                    <option value="AL">Alagoas</option>
                                                    <option value="AP">Amapá</option>
                                                    <option value="AM">Amazonas</option>
                                                    <option value="BA">Bahia</option>
                                                    <option value="CE">Ceará</option>
                                                    <option value="DF">Distrito Federal</option>
                                                    <option value="ES">Espirito Santo</option>
                                                    <option value="GO">Goiás</option>
                                                    <option value="MA">Maranhão</option>
                                                    <option value="MT">Mato Grosso</option>
                                                    <option value="MS">Mato Grosso do Sul</option>
                                                    <option value="MG">Minas Gerais</option>
                                                    <option value="PR">Paraná</option>
                                                    <option value="PB">Paraíba</option>
                                                    <option value="PA">Pará</option>
                                                    <option value="PE">Pernambuco</option>
                                                    <option value="PI">Piauí</option>
                                                    <option value="RN">Rio Grande do Norte</option>
                                                    <option value="RS">Rio Grande do Sul</option>
                                                    <option value="RJ">Rio de Janeiro</option>
                                                    <option value="RO">Rondonia</option>
                                                    <option value="RR">Roraima</option>
                                                    <option value="SC">Santa Catarina</option>
                                                    <option value="SE">Sergipe</option>
                                                    <option value="SP">São Paulo</option>
                                                    <option value="TO">Tocantins</option>
                                                </select>
                                            </div>

                                            <div class="col-md-3">
                                                <label for="p_data_emissao_rg" class="form-label">Data Emissão</label>
                                                <input id="p_data_emissao_rg" name="p_data_emissao_rg" type="text"
                                                    wire:model="data_emissao_rg" class="form-control"
                                                    x-mask="99/99/9999" placeholder="dd/mm/yyyy" maxlength="10">
                                            </div>

                                            <div class="col-md-3">
                                                <label for="p_data_nascimento" class="form-label">Nascimento <sup class="text-danger">*</sup></label>
                                                <input id="p_data_nascimento" name="p_data_nascimento" type="text"
                                                    class="form-control" wire:model="data_nascimento"
                                                    x-mask="99/99/9999" maxlength="10" placeholder="dd/mm/yyyy" required>
                                            </div>

                                            <div class="col-md-3">
                                                <label for="p_raca_id" class="form-label">Raça <sup class="text-danger">*</sup></label>
                                                <select id="p_raca_id" name="p_raca_id" wire:model="raca_id" class="form-select" required>
                                                    <option value="">Selecione...</option>
                                                    <option value="1">Branca</option>
                                                    <option value="2">Preta</option>
                                                    <option value="3">Amarela</option>
                                                    <option value="4">Parda</option>
                                                    <option value="5">Indígena</option>
                                                </select>
                                            </div>

                                            <div class="col-md-3">
                                                <label for="p_sexo" class="form-label">Sexo <sup class="text-danger">*</sup></label>
                                                <select id="p_sexo" name="p_sexo" wire:model="sexo" class="form-select" required>
                                                    <option value="">Selecione...</option>
                                                    <option value="M">Masculino</option>
                                                    <option value="F">Feminino</option>
                                                </select>
                                            </div>

                                            <div class="col-md-3">
                                                <label for="p_genero_id" class="form-label">Gênero <sup class="text-danger">*</sup></label>
                                                <select id="p_genero_id" name="p_genero_id" wire:model="genero_id" class="form-select" required>
                                                    <option value="">Selecione...</option>
                                                    <option value="1">Mulher cisgênero</option>
                                                    <option value="2">Homem cisgênero</option>
                                                    <option value="3">Mulher transgênero</option>
                                                    <option value="4">Homem transgênero</option>
                                                    <option value="5">Não sei responder</option>
                                                    <option value="6">Prefiro não responder</option>
                                                </select>
                                            </div>

                                            <div class="col-md-6">
                                                <label for="p_estado_civil_id" class="form-label">Estado Civil <sup class="text-danger">*</sup></label>
                                                <select id="p_estado_civil_id" name="p_estado_civil_id" wire:model="estado_civil_id" class="form-select" required>
                                                    <option value="">Selecione...</option>
                                                    <option value="1">Solteiro</option>
                                                    <option value="2">Casado</option>
                                                    <option value="3">Divorciado</option>
                                                    <option value="4">Desquitado</option>
                                                    <option value="5">Viúvo</option>
                                                    <option value="6">União Estável</option>
                                                    <option value="7">Separação Judicial</option>
                                                    <option value="8">Separação Consensual</option>
                                                    <option value="9">Divorciado Consensual</option>
                                                    <option value="10">Outros</option>
                                                </select>
                                            </div>

                                            <div class="col-md-6">
                                                <label for="p_nacionalidade_id" class="form-label">Nacionalidade <sup class="text-danger">*</sup></label>
                                                <select id="p_nacionalidade_id" name="p_nacionalidade_id" wire:model="nacionalidade_id" class="form-select" required>
                                                    <option value="">Selecione...</option>
                                                    <option value="1">Brasileiro(a)</option>
                                                    <option value="2">Estrangeiro(a) Naturalizado(a)</option>
                                                    <option value="3">Estrangeiro</option>
                                                </select>
                                            </div>

                                            <div class="col-md-3">
                                                <label for="p_estado" class="form-label">Estado</label>
                                                <select id="p_estado" name="p_estado" class="form-select" wire:model="estado">
                                                    <option value="">Selecione...</option>
                                                    <option value="AC">Acre</option>
                                                    <option value="AL">Alagoas</option>
                                                    <option value="AP">Amapá</option>
                                                    <option value="AM">Amazonas</option>
                                                    <option value="BA">Bahia</option>
                                                    <option value="CE">Ceará</option>
                                                    <option value="DF">Distrito Federal</option>
                                                    <option value="ES">Espirito Santo</option>
                                                    <option value="GO">Goiás</option>
                                                    <option value="MA">Maranhão</option>
                                                    <option value="MT">Mato Grosso</option>
                                                    <option value="MS">Mato Grosso do Sul</option>
                                                    <option value="MG">Minas Gerais</option>
                                                    <option value="PR">Paraná</option>
                                                    <option value="PB">Paraíba</option>
                                                    <option value="PA">Pará</option>
                                                    <option value="PE">Pernambuco</option>
                                                    <option value="PI">Piauí</option>
                                                    <option value="RN">Rio Grande do Norte</option>
                                                    <option value="RS">Rio Grande do Sul</option>
                                                    <option value="RJ">Rio de Janeiro</option>
                                                    <option value="RO">Rondonia</option>
                                                    <option value="RR">Roraima</option>
                                                    <option value="SC">Santa Catarina</option>
                                                    <option value="SE">Sergipe</option>
                                                    <option value="SP">São Paulo</option>
                                                    <option value="TO">Tocantins</option>
                                                </select>
                                            </div>

                                            <div class="col-md-3">
                                                <label for="p_cidade" class="form-label">Cidade</label>
                                                <input id="p_cidade" name="p_cidade" type="text" maxlength="40" class="form-control" wire:model="cidade">
                                            </div>

                                            <div class="col-md-3 d-none" id="box-pais">
                                                <label for="p_pais_origem" class="form-label">País <sup class="text-danger">*</sup></label>
                                                <input id="p_pais_origem" name="p_pais_origem" type="text" maxlength="40" class="form-control" wire:model="pais_origem">
                                            </div>

                                            <div class="col-md-6">
                                                <label for="p_escolaridade_id" class="form-label">Escolaridade <sup class="text-danger">*</sup></label>
                                                <select id="p_escolaridade_id" name="p_escolaridade_id" wire:model="escolaridade_id" class="form-select" required>
                                                    <option value="">Selecione...</option>
                                                    <option value="1">Analfabeto</option>
                                                    <option value="2">Fundamental Incompleto</option>
                                                    <option value="3">Fundamental Completo</option>
                                                    <option value="4">Médio Incompleto</option>
                                                    <option value="5">Médio Completo</option>
                                                    <option value="6">Técnico e/ou Tecnólogo</option>
                                                    <option value="7">Superior Incompleto</option>
                                                    <option value="8">Superior Completo</option>
                                                    <option value="9">Especialização</option>
                                                    <option value="10">Mestrado</option>
                                                    <option value="11">Doutorado</option>
                                                    <option value="12">Pós-doutorado</option>
                                                </select>
                                            </div>

                                            <div class="col-md-12">
                                                <label for="p_profissao" class="form-label">Profissão/Ocupação <sup class="text-danger">*</sup></label>
                                                <input id="p_profissao" name="p_profissao" type="text" class="form-control" wire:model="profissao" required>
                                            </div>

                                            <div class="col-md-12">
                                                <label for="p_nome_mae" class="form-label">Nome da Mãe <sup class="text-danger">*</sup></label>
                                                <input id="p_nome_mae" name="p_nome_mae" type="text" class="form-control text-uppercase" wire:model="nome_mae" required>
                                            </div>

                                            <div class="col-md-12">
                                                <label for="p_nome_pai" class="form-label">Nome do Pai</label>
                                                <input id="p_nome_pai" name="p_nome_pai" type="text" class="form-control text-uppercase" wire:model="nome_pai">
                                            </div>

                                            <div class="col-md-3">
                                                <label for="p_titulo_eleitor" class="form-label">Título de Eleitor</label>
                                                <input id="p_titulo_eleitor" name="p_titulo_eleitor" type="text" maxlength="20" class="form-control" wire:model="titulo_eleitor">
                                            </div>

                                            <div class="col-md-3">
                                                <label for="p_zona_eleitoral" class="form-label">Zona Eleitoral</label>
                                                <input id="p_zona_eleitoral" name="p_zona_eleitoral" type="text" class="form-control" wire:model="zona_eleitoral" maxlength="4" x-mask="9999">
                                            </div>

                                            <div class="col-md-3">
                                                <label for="p_secao_eleitoral" class="form-label">Seção Eleitoral</label>
                                                <input id="p_secao_eleitoral" name="p_secao_eleitoral" type="text" class="form-control" wire:model="secao_eleitoral" maxlength="4" x-mask="9999">
                                            </div>

                                            <!-- (7) NIS - ADICIONADO SEM DUPLICAR -->
                                            <div class="col-md-3">
                                                <label for="p_nis" class="form-label">NIS (opcional)</label>
                                                <input id="p_nis" name="p_nis" type="text" class="form-control" wire:model="nis" maxlength="20" placeholder="PIS/PASEP/NIS">
                                            </div>
                                        </div>
                                    </div>

                                    <button type="button" name="next" class="btn btn-primary next action-button float-end mt-4" value="Next">Continuar</button>
                                </fieldset>
                                <!-- /STEP 1: DADOS PRINCIPAIS -->

                                <!-- STEP 2: INFORMAÇÕES ADICIONAIS -->
                                <fieldset>
                                    <div class="form-card text-start">

                                        <!-- 11. Situação do imóvel atual -->
                                        <div class="section mb-3">
                                            <label for="situacao_imovel" class="form-label">
                                                Situação do imóvel atual <sup class="text-danger">*</sup>
                                            </label>
                                            <select id="situacao_imovel" name="situacao_imovel" class="form-select" required>
                                                <option value="">Selecione...</option>
                                                <option value="proprio">Próprio</option>
                                                <option value="alugado">Alugado</option>
                                                <option value="cedido">Cedido</option>
                                                <option value="ocupacao">Ocupação</option>
                                                <option value="outros">Outros</option>
                                            </select>
                                        </div>

                                        <!-- 12. Quantas pessoas moram na residência -->
                                        <div class="section mb-3">
                                            <label for="qtd_moradores" class="form-label">
                                                Quantas pessoas moram na residência? <sup class="text-danger">*</sup>
                                            </label>
                                            <input id="qtd_moradores" name="qtd_moradores" type="number" min="1" class="form-control" placeholder="0"
                                                required>
                                        </div>

                                        <!-- 13. Relação dos membros da família -->
                                        <div class="section mb-3">
                                            <label for="membros_familia" class="form-label">
                                                Relação dos membros da família <small class="text-muted">(nome / idade / renda)</small>
                                            </label>
                                            <textarea id="membros_familia" name="membros_familia" class="form-control" rows="3"
                                                placeholder="Ex.: Maria - 35 anos - R$ 1.200; João - 12 anos - estudante"></textarea>
                                        </div>

                                        <!-- 14. Renda do responsável -->
                                        <div class="section mb-3">
                                            <label for="renda_responsavel" class="form-label">
                                                Renda do responsável (R$) <sup class="text-danger">*</sup>
                                            </label>
                                            <input id="renda_responsavel" name="renda_responsavel" type="number" min="0" step="0.01" class="form-control"
                                                placeholder="0,00" required>
                                        </div>

                                        <!-- 15. Renda familiar total -->
                                        <div class="section mb-3">
                                            <label for="renda_familiar_total" class="form-label">
                                                Renda familiar total (R$) <sup class="text-danger">*</sup>
                                            </label>
                                            <input id="renda_familiar_total" name="renda_familiar_total" type="number" min="0" step="0.01"
                                                class="form-control" placeholder="0,00" required>
                                        </div>

                                        <!-- 16. Recebe benefício social -->
                                        <div class="section mb-3">
                                            <label class="form-label d-block">Recebe benefício social? (marque os que se aplicam)</label>
                                            <div class="row gy-2">
                                                <div class="col-md-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="beneficios[]" value="Bolsa Família" id="ben_bf">
                                                        <label class="form-check-label" for="ben_bf">Bolsa Família</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="beneficios[]" value="BPC/LOAS" id="ben_bpc">
                                                        <label class="form-check-label" for="ben_bpc">BPC/LOAS</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="beneficios[]" value="Auxílio (estadual/municipal)"
                                                            id="ben_aux">
                                                        <label class="form-check-label" for="ben_aux">Auxílio (estadual/municipal)</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="beneficios[]" value="Cadastro Único" id="ben_cad">
                                                        <label class="form-check-label" for="ben_cad">Cadastro Único</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="beneficios[]" value="Nenhum" id="ben_ning">
                                                        <label class="form-check-label" for="ben_ning">Nenhum</label>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2 mt-1">
                                                        <label class="form-label m-0" for="beneficios_outros">Outros:</label>
                                                        <input id="beneficios_outros" name="beneficios_outros" type="text" class="form-control form-control-sm"
                                                            placeholder="Quais?">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 17. Já possui imóvel registrado -->
                                        <div class="section mb-3">
                                            <label for="possui_imovel" class="form-label">Já possui imóvel registrado em seu nome?</label>
                                            <select id="possui_imovel" name="possui_imovel" class="form-select">
                                                <option value="">Selecione...</option>
                                                <option value="sim">Sim</option>
                                                <option value="nao">Não</option>
                                            </select>
                                        </div>

                                        <!-- 18. Mora em área de risco -->
                                        <div class="section mb-3">
                                            <label for="area_risco" class="form-label">Mora em área de risco?</label>
                                            <select id="area_risco" name="area_risco" class="form-select">
                                                <option value="">Selecione...</option>
                                                <option value="sim">Sim</option>
                                                <option value="nao">Não</option>
                                            </select>
                                        </div>

                                        <!-- 19. Condição atual da moradia -->
                                        <div class="section mb-3">
                                            <label for="condicao_moradia" class="form-label">Condição atual da moradia</label>
                                            <select id="condicao_moradia" name="condicao_moradia" class="form-select">
                                                <option value="">Selecione...</option>
                                                <option value="regular">Regular</option>
                                                <option value="irregular">Irregular</option>
                                                <option value="desabrigado">Desabrigado</option>
                                                <option value="risco">Área de risco</option>
                                                <option value="assentamento">Assentamento</option>
                                                <option value="outros">Outros</option>
                                            </select>
                                        </div>

                                        <!-- Documentos anexados (OBRIGATÓRIO) -->
                                        <div class="section mb-3" id="anexosSection">
                                            <label for="anexos" class="form-label">
                                                Documentos anexados <sup class="text-danger">*</sup>
                                                <small class="text-muted d-block">Formatos aceitos: PDF, JPG, PNG. Selecione um ou mais arquivos.</small>
                                            </label>
                                            <input id="anexos" name="anexos[]" type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" multiple
                                                required>
                                            <div class="form-text" id="anexos-list"></div>
                                            <div class="invalid-feedback"></div>
                                        </div>

                                        <!-- Foto obrigatória (com modal) -->
                                        <style>
                                            .cam-preview {
                                                max-width: 100%;
                                                border-radius: .35rem
                                            }

                                            .foto-modal-wrap {
                                                width: 100%;
                                                max-height: 70vh;
                                                display: flex;
                                                align-items: center;
                                                justify-content: center;
                                                background: #000;
                                                border-radius: .5rem;
                                                overflow: hidden
                                            }

                                            .foto-modal-img {
                                                max-width: 100%;
                                                max-height: 70vh;
                                                display: block
                                            }
                                        </style>

                                        <div class="section mb-3" id="fotoSection">
                                            <label class="form-label">Foto do candidato (obrigatória) <sup class="text-danger">*</sup></label>
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal"
                                                    data-bs-target="#modalCamera" id="btnOpenCam">Iniciar câmera</button>

                                                <!-- Fica oculto até existir foto -->
                                                <button type="button" id="btnViewPhoto" class="btn btn-outline-secondary btn-sm d-none">Ver foto</button>
                                                <button type="button" id="btnClearPhoto" class="btn btn-outline-danger btn-sm" disabled>Remover foto</button>
                                            </div>

                                            <!-- Mantemos o <img>, mas SEM mostrar automaticamente -->
                                            <img id="camPreview" class="cam-preview d-none" alt="Prévia da foto" />

                                            <div class="mt-2">
                                                <label for="foto_file" class="form-label mb-1">Ou selecione uma foto (caso a câmera não funcione)</label>
                                                <input id="foto_file" name="foto_file" type="file" class="form-control" accept="image/*" capture="environment">
                                            </div>

                                            <input type="hidden" id="foto_capturada" name="foto_capturada" required>
                                            <div id="fotoError" class="invalid-feedback"></div>

                                            <small class="text-muted d-block mt-1">Na modal é possível alternar entre câmera frontal e traseira.</small>
                                        </div>

                                        <!-- 20. Quais documentos está apresentando -->
                                        <div class="section mb-3">
                                            <label class="form-label d-block">Quais documentos está apresentando?</label>
                                            <div class="row gy-2">
                                                <div class="col-md-6">
                                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="docs[]" value="RG"
                                                            id="doc_rg"><label class="form-check-label" for="doc_rg">RG</label></div>
                                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="docs[]" value="CPF"
                                                            id="doc_cpf"><label class="form-check-label" for="doc_cpf">CPF</label></div>
                                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="docs[]"
                                                            value="Comprovante de residência" id="doc_cr"><label class="form-check-label" for="doc_cr">Comprovante de
                                                            residência</label></div>
                                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="docs[]"
                                                            value="Certidão (nasc./casamento)" id="doc_ce"><label class="form-check-label" for="doc_ce">Certidão
                                                            (nasc./casamento)</label></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="docs[]" value="NIS"
                                                            id="doc_nis"><label class="form-check-label" for="doc_nis">NIS</label></div>
                                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="docs[]"
                                                            value="Título de eleitor" id="doc_te"><label class="form-check-label" for="doc_te">Título de
                                                            eleitor</label></div>
                                                    <div class="d-flex align-items-center gap-2 mt-1">
                                                        <label class="form-label m-0" for="docs_outros">Outros:</label>
                                                        <input id="docs_outros" name="docs_outros" type="text" class="form-control form-control-sm"
                                                            placeholder="Quais?">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>

                                    <button type="button" name="previous"
                                        class="btn btn-dark previous action-button-previous float-start me-2">Voltar</button>
                                    <button type="button" name="next" class="btn btn-primary next action-button float-end">Continuar</button>
                                </fieldset>
                                <!-- /STEP 2: INFORMAÇÕES ADICIONAIS -->


                                <!-- STEP 3: CONTATO E ENDEREÇO -->
                                <fieldset>
                                    <div class="form-card text-start">

                                        <!-- Local onde você mora atualmente -->
                                        <h5 class="mb-1">Local onde você mora atualmente</h5>
                                        <small class="form-text text-muted mb-3">
                                            Informe o endereço e as características do local onde você reside atualmente.
                                        </small>

                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label for="tipo_moradia" class="form-label">Tipo de Moradia <sup class="text-danger">*</sup></label>
                                                <select id="tipo_moradia" name="tipo_moradia" class="form-select" wire:model="tipo_moradia" required>
                                                    <option value="">Selecione...</option>
                                                    <option value="alugada">Alugada</option>
                                                    <option value="cedida">Cedida</option>
                                                    <option value="propria">Própria</option>
                                                    <option value="ocupacao">Ocupação</option>
                                                    <option value="outros">Outros</option>
                                                </select>
                                            </div>

                                            <div class="col-md-4">
                                                <label for="valor_aluguel" class="form-label">Valor do Aluguel</label>
                                                <input id="valor_aluguel" name="valor_aluguel" type="number" min="0" step="0.01" class="form-control" wire:model="valor_aluguel" placeholder="0,00">
                                            </div>

                                            <div class="col-md-4">
                                                <label for="cep" class="form-label">CEP</label>
                                                <input id="cep" name="cep" type="text" class="form-control" wire:model="cep" x-mask="99999-999" placeholder="00000-000">
                                            </div>

                                            <div class="col-md-6">
                                                <label for="logradouro" class="form-label">Logradouro <sup class="text-danger">*</sup></label>
                                                <input id="logradouro" name="logradouro" type="text" class="form-control" wire:model="logradouro" required>
                                            </div>

                                            <div class="col-md-3">
                                                <label for="numero" class="form-label">Número <sup class="text-danger">*</sup></label>
                                                <input id="numero" name="numero" type="text" class="form-control" wire:model="numero" required>
                                            </div>

                                            <div class="col-md-3">
                                                <?php
                                                require_once __DIR__ . '/dist/assets/conexao.php';

                                                try {
                                                    $stmt = $pdo->query("SELECT id, nome FROM bairros ORDER BY nome ASC");
                                                    $bairros = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                } catch (PDOException $e) {
                                                    echo '<label for="bairro_id" class="form-label">Bairro</label>';
                                                    echo '<select id="bairro_id" name="bairro_id" class="form-select" wire:model="bairro_id"><option value="">Erro ao conectar</option></select>';
                                                    $bairros = [];
                                                }
                                                ?>
                                                <label for="bairro_id" class="form-label">Bairro</label>
                                                <select id="bairro_id" name="bairro_id" class="form-select" wire:model="bairro_id">
                                                    <option value="">Selecione...</option>
                                                    <?php foreach ($bairros as $bairro): ?>
                                                        <option value="<?= htmlspecialchars($bairro['id']) ?>"><?= htmlspecialchars($bairro['nome']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="col-md-12">
                                                <label for="complemento" class="form-label">Complemento</label>
                                                <input id="complemento" name="complemento" type="text" class="form-control" wire:model="complemento">
                                            </div>

                                            <div class="col-md-4">
                                                <label for="estado_end" class="form-label">Estado <sup class="text-danger">*</sup></label>
                                                <select id="estado_end" name="estado_end" class="form-select" wire:model="estado_end" required>
                                                    <option value="">Selecione...</option>
                                                    <option value="AC">AC</option>
                                                    <option value="AL">AL</option>
                                                    <option value="AP">AP</option>
                                                    <option value="AM">AM</option>
                                                    <option value="BA">BA</option>
                                                    <option value="CE">CE</option>
                                                    <option value="DF">DF</option>
                                                    <option value="ES">ES</option>
                                                    <option value="GO">GO</option>
                                                    <option value="MA">MA</option>
                                                    <option value="MT">MT</option>
                                                    <option value="MS">MS</option>
                                                    <option value="MG">MG</option>
                                                    <option value="PA">PA</option>
                                                    <option value="PB">PB</option>
                                                    <option value="PR">PR</option>
                                                    <option value="PE">PE</option>
                                                    <option value="PI">PI</option>
                                                    <option value="RJ">RJ</option>
                                                    <option value="RN">RN</option>
                                                    <option value="RS">RS</option>
                                                    <option value="RO">RO</option>
                                                    <option value="RR">RR</option>
                                                    <option value="SC">SC</option>
                                                    <option value="SE">SE</option>
                                                    <option value="SP">SP</option>
                                                    <option value="TO">TO</option>
                                                </select>
                                            </div>

                                            <div class="col-md-4">
                                                <label for="municipio_id" class="form-label">Município <sup class="text-danger">*</sup></label>
                                                <input id="municipio_id" name="municipio_id" type="text" class="form-control" wire:model="municipio_id" required>
                                            </div>

                                            <div class="col-md-4">
                                                <label for="referencia" class="form-label">Ponto de Referência</label>
                                                <input id="referencia" name="referencia" type="text" class="form-control" wire:model="referencia">
                                            </div>
                                        </div>

                                        <!-- Contato -->
                                        <hr class="my-3">
                                        <h6 class="mb-1">Contato</h6>
                                        <small class="form-text text-muted mb-3">Informe seu telefone de contato e e-mail.</small>

                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label for="celular" class="form-label">Celular</label>
                                                <input id="celular" name="celular" type="text" class="form-control" wire:model="celular" x-mask="(99) 9 9999-9999">
                                            </div>
                                            <div class="col-md-3">
                                                <label for="email" class="form-label">E-mail</label>
                                                <input id="email" name="email" type="email" class="form-control" wire:model="email" placeholder="email@exemplo.com">
                                            </div>
                                            <div class="col-md-3">
                                                <label for="contato1" class="form-label">Contato 1</label>
                                                <input id="contato1" name="contato1" type="text" class="form-control" wire:model="contato1">
                                            </div>
                                            <div class="col-md-3">
                                                <label for="contato2" class="form-label">Contato 2</label>
                                                <input id="contato2" name="contato2" type="text" class="form-control" wire:model="contato2">
                                            </div>
                                        </div>

                                        <!-- (12) Qtd moradores — ADICIONADO -->
                                        <div class="row g-3 mt-1">
                                            <div class="col-md-4">
                                                <label for="qtd_moradores" class="form-label">Quantas pessoas moram na residência?</label>
                                                <input id="qtd_moradores" name="qtd_moradores" type="number" min="1" step="1" class="form-control" wire:model="qtd_moradores">
                                            </div>
                                        </div>

                                        <!-- Área de risco / insalubre / desabrigada (já existia) -->
                                        <hr class="my-3">
                                        <h6 class="mb-1">Minha família reside em área de risco, insalubre, ou está desabrigada?</h6>
                                        <small class="form-text text-muted mb-3">
                                            Se você se enquadra nessa situação, informe o bairro, o tipo e o número da ocorrência. Caso contrário, deixe em branco.
                                        </small>

                                        <div class="row g-3 mb-3">
                                            <div class="col-md-4">
                                                <?php
                                                require_once __DIR__ . '/dist/assets/conexao.php';

                                                try {
                                                    $stmt = $pdo->query("SELECT id, nome FROM bairros ORDER BY nome ASC");
                                                    $bairros = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                } catch (PDOException $e) {
                                                    echo '<label for="risco_bairro_id" class="form-label">Bairro</label>';
                                                    echo '<select id="risco_bairro_id" name="risco_bairro_id" class="form-select" wire:model="risco_bairro_id"><option value="">Erro ao conectar</option></select>';
                                                    $bairros = [];
                                                }
                                                ?>
                                                <label for="risco_bairro_id" class="form-label">Bairro</label>
                                                <select id="risco_bairro_id" name="risco_bairro_id" class="form-select" wire:model="risco_bairro_id">
                                                    <option value="">Selecione...</option>
                                                    <?php foreach ($bairros as $bairro): ?>
                                                        <option value="<?= htmlspecialchars($bairro['id']) ?>"><?= htmlspecialchars($bairro['nome']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="risco_tipo_ocorrencia_id" class="form-label">Tipo de Ocorrência</label>
                                                <select id="risco_tipo_ocorrencia_id" name="risco_tipo_ocorrencia_id" class="form-select" wire:model="risco_tipo_ocorrencia_id">
                                                    <option value="">Selecione...</option>
                                                    <option value="acidente">Acidente</option>
                                                    <option value="incendio">Incêndio</option>
                                                    <option value="inundacao">Inundação</option>
                                                    <option value="desabamento">Desabamento</option>
                                                    <option value="roubo">Roubo / Furto</option>
                                                    <option value="agressao">Agressão</option>
                                                    <option value="outros">Outros</option>
                                                </select>
                                            </div>

                                            <div class="col-md-4">
                                                <label for="risco_numero_ocorrencia" class="form-label">Número da Ocorrência</label>
                                                <input id="risco_numero_ocorrencia" name="risco_numero_ocorrencia" type="text" class="form-control" wire:model="risco_numero_ocorrencia">
                                            </div>

                                        </div>

                                    </div>

                                    <button type="button" name="previous" class="btn btn-dark previous action-button-previous float-start me-1">Voltar</button>
                                    <button type="button" name="next" class="btn btn-primary next action-button float-end">Continuar</button>
                                </fieldset>
                                <!-- /STEP 3: CONTATO E ENDREÇO -->

                                <!-- STEP 4: COMPOSIÇÃO FAMILIAR -->
                                <fieldset id="step-composicao-familiar">
                                    <div class="form-card text-start">
                                        <div id="familia-list"></div>

                                        <div class="text-center mt-4">
                                            <button type="button" id="btn-add-membro" class="btn btn-primary text-white btn-add-membro-responsive">
                                                ADICIONAR OUTRO MEMBRO DA FAMÍLIA
                                            </button>
                                        </div>

                                    </div>

                                    <div class="d-flex justify-content-between mt-4">
                                        <button type="button" name="previous" class="btn btn-dark previous action-button-previous">VOLTAR</button>
                                        <button type="button" name="next" class="btn btn-primary next action-button">CONTINUAR</button>
                                    </div>
                                </fieldset>

                                <!-- TEMPLATE (não renderiza; clonado via JS) -->
                                <template id="tpl-membro-familiar">
                                    <div class="card mb-3 membro-card" data-index="{idx}">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="mb-0 titulo-membro">Membro {num}</h6>
                                                <!-- Botão remover com SVG inline (não depende de Bootstrap Icons) -->
                                                <button type="button" class="btn btn-link text-danger p-0 btn-remove-membro" title="Remover membro" aria-label="Remover membro">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 16 16" fill="currentColor" role="img" aria-hidden="true">
                                                        <path d="M6.5 1h3a.5.5 0 0 1 .5.5V2h3a.5.5 0 1 1 0 1h-.538l-.853 10.243A2 2 0 0 1 9.616 15H6.384a2 2 0 0 1-1.993-1.757L3.538 3H3a.5.5 0 0 1 0-1h3v-.5A.5.5 0 0 1 6.5 1M4.54 3l.846 10.157A1 1 0 0 0 6.384 14h3.232a1 1 0 0 0 .998-.843L11.46 3zm2.958 2.646a.5.5 0 0 1 .5.5v6.708a.5.5 0 0 1-1 0V6.146a.5.5 0 0 1 .5-.5M9.5 5.646a.5.5 0 0 1 .5.5v6.708a.5.5 0 0 1-1 0V6.146a.5.5 0 0 1 .5-.5" />
                                                    </svg>
                                                </button>
                                            </div>

                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label" for="listaFamiliares[{idx}][nome]">Nome Completo <sup class="text-danger">*</sup></label>
                                                    <input type="text" class="form-control text-uppercase"
                                                        id="listaFamiliares[{idx}][nome]"
                                                        name="listaFamiliares[{idx}][nome]"
                                                        wire:model="listaFamiliares.{idx}.nome" required>
                                                </div>

                                                <div class="col-md-3">
                                                    <label class="form-label" for="listaFamiliares[{idx}][data_nascimento]">Data de Nascimento <sup class="text-danger">*</sup></label>
                                                    <input type="text" class="form-control"
                                                        id="listaFamiliares[{idx}][data_nascimento]"
                                                        name="listaFamiliares[{idx}][data_nascimento]"
                                                        wire:model="listaFamiliares.{idx}.data_nascimento"
                                                        placeholder="dd/mm/aaaa" maxlength="10" required>
                                                </div>

                                                <div class="col-md-3">
                                                    <label class="form-label" for="listaFamiliares[{idx}][cpf]">CPF</label>
                                                    <input type="text" class="form-control"
                                                        id="listaFamiliares[{idx}][cpf]"
                                                        name="listaFamiliares[{idx}][cpf]"
                                                        wire:model="listaFamiliares.{idx}.cpf"
                                                        placeholder="000.000.000-00" maxlength="14">
                                                </div>

                                                <div class="col-md-3">
                                                    <label class="form-label" for="listaFamiliares[{idx}][sexo]">Sexo <sup class="text-danger">*</sup></label>
                                                    <select class="form-select"
                                                        id="listaFamiliares[{idx}][sexo]"
                                                        name="listaFamiliares[{idx}][sexo]"
                                                        wire:model="listaFamiliares.{idx}.sexo" required>
                                                        <option value="">Selecione...</option>
                                                        <option value="M">Masculino</option>
                                                        <option value="F">Feminino</option>
                                                    </select>
                                                </div>

                                                <div class="col-md-3">
                                                    <label class="form-label" for="listaFamiliares[{idx}][genero_id]">Gênero <sup class="text-danger">*</sup></label>
                                                    <select class="form-select"
                                                        id="listaFamiliares[{idx}][genero_id]"
                                                        name="listaFamiliares[{idx}][genero_id]"
                                                        wire:model="listaFamiliares.{idx}.genero_id" required>
                                                        <option value="">Selecione...</option>
                                                        <option value="1">Mulher cisgênero - Pessoa que foi designada como mulher no nascimento e se identifica como mulher</option>
                                                        <option value="2">Homem cisgênero - Pessoa que foi designada como homem no nascimento e se identifica como homem</option>
                                                        <option value="3">Mulher transgênero - Pessoa que foi designada como homem no nascimento e se identifica como mulher</option>
                                                        <option value="4">Homem transgênero - Pessoa que foi designada como mulher no nascimento e se identifica como homem</option>
                                                        <option value="5">Não sei responder</option>
                                                        <option value="6">Prefiro não responder</option>
                                                    </select>
                                                </div>

                                                <div class="col-md-3">
                                                    <label class="form-label" for="listaFamiliares[{idx}][grau_parentesco_id]">Grau de Parentesco <sup class="text-danger">*</sup></label>
                                                    <select class="form-select grau-parentesco"
                                                        id="listaFamiliares[{idx}][grau_parentesco_id]"
                                                        name="listaFamiliares[{idx}][grau_parentesco_id]"
                                                        wire:model="listaFamiliares.{idx}.grau_parentesco_id" required>
                                                        <option value="">Selecione...</option>
                                                        <option value="1">O próprio</option>
                                                        <option value="2">Cônjuge ou Companheiro (a)</option>
                                                        <option value="3">Filho (a)</option>
                                                        <option value="4">Enteado (a), Sobrinho (A)</option>
                                                        <option value="5">Neto (a), Bisneto (a)</option>
                                                        <option value="6">Pai ou Mãe</option>
                                                        <option value="7">Irmã ou Irmão</option>
                                                        <option value="8">Sogro (a)</option>
                                                        <option value="9">Genro ou Nora</option>
                                                        <option value="10">Avó ou Avô</option>
                                                        <option value="11">Outro parente</option>
                                                        <option value="12">Não parente</option>
                                                    </select>
                                                    <!-- quando desabilitar o select (Membro 1), este hidden garante o POST -->
                                                    <input type="hidden" class="grau-parentesco-hidden" value="">
                                                </div>

                                                <div class="col-md-3 d-flex align-items-center">
                                                    <div class="form-check mt-4">
                                                        <input class="form-check-input"
                                                            type="checkbox"
                                                            id="listaFamiliares[{idx}][pcd]"
                                                            name="listaFamiliares[{idx}][pcd]"
                                                            wire:model="listaFamiliares.{idx}.pcd">
                                                        <label class="form-check-label" for="listaFamiliares[{idx}][pcd]">
                                                            É portador de necessidade especial?
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <!-- /STEP 4: COMPOSIÇÃO FAMILIAR -->

                                <!-- STEP 5: RESUMO (tabelas no estilo dos prints, label em cima do valor) -->
                                <fieldset id="step-resumo">
                                    <div class="form-card">

                                        <!-- ESTILO do resumo -->
                                        <style>
                                            #resumo-container {
                                                background: #fff;
                                                border: 1px solid #e9ecef;
                                                border-radius: .5rem;
                                                padding: 1rem
                                            }

                                            .resumo-section-title {
                                                color: #0d6efd;
                                                font-size: .95rem;
                                                margin-bottom: .25rem
                                            }

                                            .resumo-sub {
                                                color: #6c757d;
                                                font-size: .8rem;
                                                margin-bottom: .5rem;
                                                display: block
                                            }

                                            .resumo-item {
                                                padding: .6rem 0;
                                                border-bottom: 1px solid #edf0f3
                                            }

                                            .resumo-row:last-child .resumo-item {
                                                border-bottom: 0
                                            }

                                            .resumo-label {
                                                display: block;
                                                font-size: .8rem;
                                                color: #6c757d;
                                                margin-bottom: .15rem
                                            }

                                            .resumo-valor {
                                                display: block;
                                                font-size: .95rem;
                                                word-break: break-word
                                            }

                                            @media (max-width: 767.98px) {
                                                .resumo-col {
                                                    flex: 0 0 100%;
                                                    max-width: 100%
                                                }
                                            }
                                        </style>

                                        <div id="resumo-container" class="container mt-2"></div>

                                        <!-- Declaração legal (21) – Coari -->
                                        <div class="mt-4 p-3 border rounded bg-light text-start" style="font-size:0.9rem; line-height:1.4;">
                                            <p>
                                                <strong>Declaro</strong>, sob as penas da lei (<em>Art. 299 do Código Penal</em>), que as declarações contidas neste formulário correspondem à verdade e comprometo-me a procurar a
                                                <strong>Secretaria Municipal de Terras e Habitação (SEMTH)</strong> do Município de <strong>Coari</strong> para atualizá-las sempre que houver mudanças em relação às informações prestadas por mim neste cadastro.
                                            </p>
                                            <p>
                                                Concordo que li a informação acima e estou ciente de que sou responsável pelos dados pessoais informados.
                                            </p>

                                        </div>

                                        <div class="form-check mt-3 text-start mb-3">
                                            <input class="form-check-input" type="checkbox" id="declaracao" name="declaracao">
                                            <label class="form-check-label fw-bold" for="declaracao">
                                                Estou ciente e concordo com os termos acima.
                                            </label>
                                        </div>

                                        <div class="d-flex justify-content-between mt-4">
                                            <button type="button" name="previous" class="btn btn-dark previous action-button-previous">VOLTAR</button>
                                            <button type="submit" class="btn btn-success action-button">FINALIZAR</button>
                                        </div>
                                    </div>
                                </fieldset>


                                <!-- Forçar backdrop escuro e camadas acima de qualquer layout do tema -->
                                <style id="fix-modal-backdrop">
                                    :root {
                                        --bs-backdrop-bg: #000;
                                        --bs-backdrop-opacity: .75
                                    }

                                    .modal-backdrop,
                                    .modal-backdrop.fade,
                                    .modal-backdrop.show {
                                        background-color: #000 !important;
                                        opacity: .75 !important
                                    }

                                    .modal-backdrop {
                                        z-index: 2050 !important
                                    }

                                    .modal {
                                        z-index: 2060 !important
                                    }

                                    .has-open-modal .app,
                                    .has-open-modal .app-content,
                                    .has-open-modal .main-content,
                                    .has-open-modal .page-content,
                                    .has-open-modal .sidebar,
                                    .has-open-modal header,
                                    .has-open-modal .navbar,
                                    .has-open-modal .layout,
                                    .has-open-modal .content {
                                        transform: none !important;
                                        will-change: auto !important;
                                    }
                                </style>

                                <!-- ========== MODAL DA CÂMERA ========== -->
                                <div class="modal fade" id="modalCamera" tabindex="-1" aria-labelledby="modalCameraLabel" aria-hidden="true"
                                    data-bs-backdrop="static" data-bs-keyboard="false">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content">

                                            <div class="modal-header">
                                                <h5 class="modal-title" id="modalCameraLabel">Câmera</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                            </div>

                                            <div class="modal-body">
                                                <div id="camAlert" class="alert alert-warning d-none"></div>
                                                <video id="camVideo" class="w-100 bg-dark rounded" style="max-height:360px" autoplay playsinline muted></video>
                                                <canvas id="camCanvas" class="d-none"></canvas>
                                            </div>

                                            <div class="modal-footer justify-content-between">
                                                <div>
                                                    <button type="button" id="btnCamToggle" class="btn btn-outline-secondary" disabled>Alternar câmera</button>
                                                </div>
                                                <div>
                                                    <button type="button" id="btnCamShot" class="btn btn-primary" disabled>Capturar</button>
                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                                <!-- ========== /MODAL DA CÂMERA ========== -->

                                <!-- ========== MODAL: VISUALIZAÇÃO DA FOTO ========== -->
                                <div class="modal fade" id="modalFotoPreview" tabindex="-1" aria-labelledby="modalFotoPreviewLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content">

                                            <div class="modal-header">
                                                <h5 class="modal-title" id="modalFotoPreviewLabel">Foto capturada</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                            </div>

                                            <div class="modal-body">
                                                <div class="foto-modal-wrap">
                                                    <img id="fotoPreviewModalImg" class="foto-modal-img" alt="Foto do candidato">
                                                </div>
                                            </div>

                                            <div class="modal-footer">
                                                <a id="btnDownloadPhoto" class="btn btn-outline-secondary" download="foto_candidato.jpg" href="#">Baixar</a>
                                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                                <!-- ========== /MODAL: VISUALIZAÇÃO DA FOTO ========== -->

                                <!-- ====== JS DO WIZARD / RESUMO / CÂMERA + VALIDAÇÃO ====== -->
                                <script>
                                    (function() {
                                        "use strict";
                                        const $id = (id) => document.getElementById(id);

                                        // ===== Helpers
                                        const safe = (v, d = '-') => (v != null && String(v).trim() !== '') ? String(v) : d;
                                        const txtSelect = (id) => {
                                            const el = $id(id);
                                            if (!el) return '-';
                                            if (el.tagName?.toLowerCase() === 'select') {
                                                const o = el.options[el.selectedIndex];
                                                return safe(o ? (o.text || o.value) : '');
                                            }
                                            return safe(el.value, '-');
                                        };
                                        const val = (id) => {
                                            const el = $id(id);
                                            return el ? safe(el.value) : '-';
                                        };
                                        const joinChecks = (name) => {
                                            const list = document.querySelectorAll('input[name="' + name + '"]:checked');
                                            return list.length ? Array.from(list).map(x => x.value).join(', ') : '-';
                                        };
                                        const moedaBR = (v) => {
                                            if (v == null || String(v).trim() === '') return '-';
                                            const n = Number(v);
                                            return isNaN(n) ? '-' : n.toLocaleString('pt-BR', {
                                                style: 'currency',
                                                currency: 'BRL'
                                            });
                                        };

                                        // ===== Wizard
                                        const form = $id('form-wizard1');
                                        let currentTab = 0;
                                        const fieldsets = () => Array.from(form.getElementsByTagName('fieldset'));

                                        function ActiveTab(n) {
                                            const P = $id('infoPessoais'),
                                                A = $id('infoAdicionais'),
                                                C = $id('contEndereco'),
                                                F = $id('compFamiliar'),
                                                R = $id('confirm');
                                            [P, A, C, F, R].forEach(el => el?.classList.remove('active'));
                                            if (n >= 1) P?.classList.add('done');
                                            if (n >= 2) A?.classList.add('done');
                                            if (n >= 3) C?.classList.add('done');
                                            if (n >= 4) F?.classList.add('done');
                                            [P, A, C, F, R][n]?.classList.add('active');
                                        }

                                        function showTab(n) {
                                            const x = fieldsets();
                                            currentTab = Math.max(0, Math.min(n, x.length - 1));
                                            x.forEach((fs, i) => fs.style.display = (i === currentTab ? 'block' : 'none'));
                                            ActiveTab(currentTab);
                                        }

                                        // ===== Validação
                                        function setInvalid(el, msg = 'Campo obrigatório') {
                                            if (!el) return;
                                            el.classList.add('is-invalid');
                                            let fb = el.parentElement?.querySelector('.invalid-feedback');
                                            if (!fb) {
                                                fb = document.createElement('div');
                                                fb.className = 'invalid-feedback';
                                                el.parentElement?.appendChild(fb);
                                            }
                                            fb.textContent = msg;
                                        }

                                        function clearInvalid(el) {
                                            if (!el) return;
                                            el.classList.remove('is-invalid');
                                            const fb = el.parentElement?.querySelector('.invalid-feedback');
                                            if (fb) {
                                                fb.textContent = '';
                                            }
                                        }

                                        const fotoHidden = $id('foto_capturada');
                                        const fotoErrorBox = $id('fotoError');
                                        const fotoSection = $id('fotoSection');
                                        const anexosInput = $id('anexos');

                                        function validateField(el) {
                                            if (!el) return true;

                                            if (el === fotoHidden) {
                                                const ok = !!(fotoHidden.value && String(fotoHidden.value).trim() !== '');
                                                if (!ok) {
                                                    if (fotoErrorBox) {
                                                        fotoErrorBox.textContent = 'Campo obrigatório: capture ou selecione uma foto.';
                                                    }
                                                    fotoSection?.classList.add('has-error');
                                                } else {
                                                    if (fotoErrorBox) {
                                                        fotoErrorBox.textContent = '';
                                                    }
                                                    fotoSection?.classList.remove('has-error');
                                                }
                                                return ok;
                                            }

                                            if (el === anexosInput) {
                                                const ok = (anexosInput.files && anexosInput.files.length > 0);
                                                if (!ok) {
                                                    setInvalid(anexosInput, 'Campo obrigatório: anexe ao menos um documento.');
                                                } else {
                                                    clearInvalid(anexosInput);
                                                }
                                                return ok;
                                            }

                                            let ok = true;
                                            const tag = el.tagName.toLowerCase();
                                            if (el.required) {
                                                if (tag === 'select') {
                                                    ok = !!el.value;
                                                } else if (el.type === 'number') {
                                                    ok = String(el.value).trim() !== '' && el.checkValidity();
                                                } else if (el.type === 'file') {
                                                    ok = (el.files && el.files.length > 0);
                                                } else {
                                                    ok = String(el.value).trim() !== '';
                                                }
                                            }
                                            if (el.required && !ok) setInvalid(el);
                                            else clearInvalid(el);
                                            return ok;
                                        }

                                        function validateStep(i) {
                                            const fs = fieldsets()[i];
                                            if (!fs) return true;
                                            if (fotoErrorBox) fotoErrorBox.textContent = '';
                                            const inputs = fs.querySelectorAll('input,select,textarea');
                                            let ok = true,
                                                firstInvalid = null;
                                            inputs.forEach(el => {
                                                if (el.disabled) return;
                                                const pass = validateField(el);
                                                if (!pass) {
                                                    ok = false;
                                                    firstInvalid = firstInvalid || el;
                                                }
                                            });
                                            if (!ok) {
                                                if (firstInvalid && firstInvalid.type !== 'hidden') {
                                                    firstInvalid.focus({
                                                        preventScroll: true
                                                    });
                                                    firstInvalid.scrollIntoView({
                                                        behavior: 'smooth',
                                                        block: 'center'
                                                    });
                                                } else if (fotoSection) {
                                                    fotoSection.scrollIntoView({
                                                        behavior: 'smooth',
                                                        block: 'center'
                                                    });
                                                }
                                            }
                                            return ok;
                                        }

                                        form?.addEventListener('input', (e) => {
                                            const t = e.target;
                                            if (!(t instanceof HTMLElement)) return;
                                            clearInvalid(t);
                                        });
                                        form?.addEventListener('change', (e) => {
                                            const t = e.target;
                                            if (!(t instanceof HTMLElement)) return;
                                            if (t === anexosInput) validateField(anexosInput);
                                            else clearInvalid(t);
                                        });

                                        document.addEventListener('click', (e) => {
                                            const next = e.target.closest?.('.next');
                                            const prev = e.target.closest?.('.previous');
                                            if (!next && !prev) return;
                                            e.preventDefault();
                                            if (next) {
                                                if (!validateStep(currentTab)) return;
                                                showTab(currentTab + 1);
                                            } else {
                                                showTab(currentTab - 1);
                                            }
                                        }, true);

                                        form?.addEventListener('keydown', e => {
                                            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') e.preventDefault();
                                        });

                                        form?.addEventListener('submit', e => {
                                            if (!validateStep(currentTab) || currentTab !== fieldsets().length - 1) {
                                                e.preventDefault();
                                                return;
                                            }
                                            const dec = $id('declaracao');
                                            if (dec && !dec.checked) {
                                                e.preventDefault();
                                                alert('Você precisa aceitar a declaração para finalizar.');
                                                dec.focus();
                                            }
                                        });

                                        showTab(0);

                                        // ===== Lista de anexos
                                        const anexosList = $id('anexos-list');
                                        anexosInput?.addEventListener('change', () => {
                                            if (!anexosInput.files?.length) {
                                                anexosList.textContent = '';
                                                return;
                                            }
                                            anexosList.innerHTML = Array.from(anexosInput.files).map(f => '• ' + f.name).join('<br>');
                                        });

                                        // ===== Foto (arquivo + visualização apenas na modal)
                                        const fileFoto = $id('foto_file');
                                        const hiddenFoto = $id('foto_capturada');
                                        const imgPreview = $id('camPreview'); // mantemos, porém SEM mostrar
                                        const btnClear = $id('btnClearPhoto');
                                        const btnView = $id('btnViewPhoto');

                                        const modalEl = $id('modalCamera');
                                        const videoEl = $id('camVideo');
                                        const canvasEl = $id('camCanvas');
                                        const btnShot = $id('btnCamShot');
                                        const btnToggle = $id('btnCamToggle');
                                        const camAlert = $id('camAlert');
                                        const btnOpen = $id('btnOpenCam');

                                        const modalViewEl = $id('modalFotoPreview');
                                        const modalImg = $id('fotoPreviewModalImg');
                                        const btnDown = $id('btnDownloadPhoto');

                                        [modalEl, modalViewEl].forEach(el => {
                                            if (el && el.parentElement !== document.body) document.body.appendChild(el);
                                        });
                                        const getModal = (el) => window.bootstrap?.Modal.getOrCreateInstance(el, {
                                            backdrop: 'static',
                                            keyboard: false
                                        }) ?? null;
                                        try {
                                            window.bootstrap?.Modal.getOrCreateInstance(modalEl, {
                                                backdrop: 'static',
                                                keyboard: false,
                                                focus: true
                                            });
                                        } catch {}

                                        function updateHasOpenModal() {
                                            const anyOpen = document.querySelectorAll('.modal.show').length > 0;
                                            document.body.classList.toggle('has-open-modal', anyOpen);
                                        }
                                        ['shown.bs.modal', 'hidden.bs.modal'].forEach(evt => {
                                            modalEl?.addEventListener(evt, updateHasOpenModal);
                                            modalViewEl?.addEventListener(evt, updateHasOpenModal);
                                        });

                                        function setPreview(dataURL) {
                                            hiddenFoto.value = dataURL || '';
                                            const has = !!dataURL;

                                            // Nunca mostrar inline: garantir que permaneça escondido
                                            if (imgPreview) {
                                                imgPreview.src = dataURL || '';
                                                imgPreview.classList.add('d-none');
                                            }

                                            if (has) {
                                                if (btnClear) btnClear.disabled = false;
                                                if (btnView) btnView.classList.remove('d-none'); // aparece só quando tiver foto
                                                if (modalImg) modalImg.src = dataURL;
                                                if (btnDown) {
                                                    btnDown.href = dataURL;
                                                    btnDown.setAttribute('download', 'foto_candidato.jpg');
                                                }
                                                if (fotoErrorBox) fotoErrorBox.textContent = '';
                                            } else {
                                                if (btnClear) btnClear.disabled = true;
                                                if (btnView) btnView.classList.add('d-none'); // esconde se remover
                                                if (modalImg) modalImg.removeAttribute('src');
                                                if (btnDown) btnDown.removeAttribute('href');
                                            }
                                        }

                                        fileFoto?.addEventListener('change', () => {
                                            const f = fileFoto.files?.[0];
                                            if (!f) {
                                                setPreview('');
                                                return;
                                            }
                                            const r = new FileReader();
                                            r.onload = e => setPreview(String(e.target.result || ''));
                                            r.readAsDataURL(f);
                                        });

                                        btnClear?.addEventListener('click', () => {
                                            setPreview('');
                                            if (fileFoto) fileFoto.value = '';
                                        });

                                        btnView?.addEventListener('click', () => {
                                            if (!hiddenFoto.value) return;
                                            if (modalImg) modalImg.src = hiddenFoto.value;
                                            getModal(modalViewEl)?.show();
                                        });

                                        // ===== Câmera
                                        let stream = null,
                                            facing = 'environment',
                                            devices = [],
                                            deviceIndex = 0,
                                            usingDeviceId = false;
                                        const isSecureOrigin = (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1');

                                        function setAlert(msg) {
                                            camAlert.textContent = msg;
                                            camAlert.classList.remove('d-none');
                                        }

                                        function clearAlert() {
                                            camAlert.classList.add('d-none');
                                            camAlert.textContent = '';
                                        }

                                        function stopCamera() {
                                            if (stream) {
                                                stream.getTracks().forEach(t => t.stop());
                                                stream = null;
                                            }
                                            if (videoEl) {
                                                videoEl.srcObject = null;
                                            }
                                        }

                                        async function loadDevices() {
                                            try {
                                                const all = await navigator.mediaDevices.enumerateDevices();
                                                devices = all.filter(d => d.kind === 'videoinput');
                                                if (devices.length) deviceIndex = deviceIndex % devices.length;
                                            } catch {
                                                devices = [];
                                            }
                                        }
                                        async function attachStream(s) {
                                            stream = s;
                                            videoEl.srcObject = s;
                                            videoEl.setAttribute('playsinline', 'true');
                                            videoEl.setAttribute('muted', 'true');
                                            videoEl.muted = true;
                                            try {
                                                await videoEl.play();
                                            } catch {}
                                            btnShot.disabled = false;
                                        }
                                        async function startCameraByFacing() {
                                            stopCamera();
                                            const s = await navigator.mediaDevices.getUserMedia({
                                                audio: false,
                                                video: {
                                                    facingMode: {
                                                        ideal: facing
                                                    },
                                                    width: {
                                                        ideal: 1280
                                                    },
                                                    height: {
                                                        ideal: 720
                                                    }
                                                }
                                            });
                                            usingDeviceId = false;
                                            await attachStream(s);
                                        }
                                        async function startCameraByDevice() {
                                            await loadDevices();
                                            if (!devices.length) throw new Error('Sem câmeras detectadas');
                                            const chosen = devices[deviceIndex % devices.length];
                                            stopCamera();
                                            const s = await navigator.mediaDevices.getUserMedia({
                                                audio: false,
                                                video: {
                                                    deviceId: {
                                                        exact: chosen.deviceId
                                                    },
                                                    width: {
                                                        ideal: 1280
                                                    },
                                                    height: {
                                                        ideal: 720
                                                    }
                                                }
                                            });
                                            usingDeviceId = true;
                                            await attachStream(s);
                                        }
                                        async function startCamera() {
                                            clearAlert();
                                            btnShot.disabled = true;
                                            btnToggle.disabled = true;
                                            if (!isSecureOrigin) {
                                                setAlert('Para usar a câmera, abra via HTTPS (cadeado) ou em localhost.');
                                                stopCamera();
                                                return;
                                            }
                                            if (!navigator.mediaDevices?.getUserMedia) {
                                                setAlert('Seu navegador não suporta a câmera. Use a seleção de arquivo abaixo.');
                                                stopCamera();
                                                return;
                                            }
                                            try {
                                                await startCameraByFacing();
                                                await loadDevices();
                                                btnToggle.disabled = devices.length <= 1 && (facing !== 'user' && facing !== 'environment');
                                            } catch (err1) {
                                                try {
                                                    await startCameraByDevice();
                                                    btnToggle.disabled = devices.length <= 1;
                                                } catch (err2) {
                                                    let msg = 'Não foi possível iniciar a câmera. ';
                                                    if (err1?.name === 'NotAllowedError') msg += 'Permissão negada: habilite o acesso à câmera.';
                                                    else if (err1?.name === 'NotFoundError') msg += 'Nenhuma câmera encontrada.';
                                                    else msg += 'Verifique permissões, HTTPS e tente novamente.';
                                                    setAlert(msg);
                                                    stopCamera();
                                                    return;
                                                }
                                            }
                                        }
                                        async function toggleCamera() {
                                            if (usingDeviceId) {
                                                if (devices.length > 1) {
                                                    deviceIndex = (deviceIndex + 1) % devices.length;
                                                    try {
                                                        await startCameraByDevice();
                                                    } catch {}
                                                }
                                            } else {
                                                facing = (facing === 'user') ? 'environment' : 'user';
                                                try {
                                                    await startCameraByFacing();
                                                } catch {
                                                    try {
                                                        await startCameraByDevice();
                                                    } catch {
                                                        setAlert('Não foi possível alternar a câmera.');
                                                        stopCamera();
                                                    }
                                                }
                                            }
                                        }

                                        function hardCloseAnyBackdrops() {
                                            // failsafe para não ficar tela escura/travada
                                            setTimeout(() => {
                                                document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
                                                document.body.classList.remove('modal-open');
                                                document.body.style.removeProperty('padding-right');
                                                updateHasOpenModal();
                                            }, 150);
                                        }

                                        function capture() {
                                            const w = videoEl.videoWidth,
                                                h = videoEl.videoHeight;
                                            if (!w || !h) return;
                                            canvasEl.width = w;
                                            canvasEl.height = h;
                                            const ctx = canvasEl.getContext('2d');
                                            ctx.drawImage(videoEl, 0, 0, w, h);
                                            const dataURL = canvasEl.toDataURL('image/jpeg', 0.92);
                                            setPreview(dataURL);

                                            // Fecha a modal de câmera e garante que não fique backdrop
                                            try {
                                                const inst = window.bootstrap?.Modal.getInstance(modalEl) || window.bootstrap?.Modal.getOrCreateInstance(modalEl);
                                                inst?.hide();
                                            } catch {}
                                            stopCamera();
                                            hardCloseAnyBackdrops();
                                        }

                                        $id('btnOpenCam')?.addEventListener('click', () => {
                                            startCamera();
                                        }, {
                                            once: false
                                        });
                                        modalEl?.addEventListener('shown.bs.modal', () => {
                                            if (!stream) startCamera();
                                        });
                                        modalEl?.addEventListener('hidden.bs.modal', () => {
                                            stopCamera();
                                            hardCloseAnyBackdrops();
                                        });
                                        $id('btnCamShot')?.addEventListener('click', capture);
                                        $id('btnCamToggle')?.addEventListener('click', toggleCamera);

                                        // ===== Resumo (Step 5)
                                        function linha(l1, v1, l2, v2) {
                                            return '<div class="row resumo-row">' +
                                                '<div class="col-md-6 resumo-col resumo-item"><span class="resumo-label">' + l1 + '</span><span class="resumo-valor">' + v1 + '</span></div>' +
                                                '<div class="col-md-6 resumo-col resumo-item"><span class="resumo-label">' + l2 + '</span><span class="resumo-valor">' + v2 + '</span></div>' +
                                                '</div>';
                                        }

                                        function renderResumo() {
                                            const wrap = $id('resumo-container');
                                            if (!wrap) return;
                                            const anexosNomes = (!anexosInput?.files?.length) ? '-' : Array.from(anexosInput.files).map(f => f.name).join(', ');
                                            const fotoStatus = hiddenFoto.value ? 'Capturada/Selecionada' : '-';

                                            const pessoais =
                                                '<h6 class="resumo-section-title mb-1">Informações Pessoais</h6>' +
                                                '<span class="resumo-sub">Dados Pessoais do Candidato</span>' +
                                                linha('Nome Completo', val('p_nome'), 'Nome Social', val('p_nome_social')) +
                                                linha('CPF', val('p_cpf'), 'RG', val('p_rg')) +
                                                linha('Órgão Emissor / UF', val('p_orgao_emissor_rg') + ' / ' + txtSelect('p_uf_emissor_rg'), 'Data de Emissão', val('p_data_emissao_rg')) +
                                                linha('Data de Nascimento', val('p_data_nascimento'), 'Raça', txtSelect('p_raca_id')) +
                                                linha('Sexo', txtSelect('p_sexo'), 'Gênero', txtSelect('p_genero_id')) +
                                                linha('Estado Civil', txtSelect('p_estado_civil_id'), 'Nacionalidade', txtSelect('p_nacionalidade_id')) +
                                                linha('Estado', txtSelect('p_estado'), 'Cidade', val('p_cidade')) +
                                                linha('Escolaridade', txtSelect('p_escolaridade_id'), 'Profissão/Ocupação', val('p_profissao')) +
                                                linha('Nome da Mãe', val('p_nome_mae'), 'Nome do Pai', val('p_nome_pai')) +
                                                linha('NIS (opcional)', val('p_nis'), '', '');

                                            const adicionais =
                                                '<h6 class="resumo-section-title mt-3 mb-1">Informações Adicionais</h6>' +
                                                '<span class="resumo-sub">Habitação, renda e documentos</span>' +
                                                linha('Situação do imóvel', txtSelect('situacao_imovel'), 'Qtd. moradores', val('qtd_moradores')) +
                                                '<div class="resumo-item"><span class="resumo-label">Relação dos membros da família</span><span class="resumo-valor">' + safe(val('membros_familia')) + '</span></div>' +
                                                linha('Renda do responsável', moedaBR($id('renda_responsavel')?.value), 'Renda familiar total', moedaBR($id('renda_familiar_total')?.value)) +
                                                linha('Possui imóvel registrado?', txtSelect('possui_imovel'), 'Mora em área de risco?', txtSelect('area_risco')) +
                                                linha('Condição da moradia', txtSelect('condicao_moradia'), 'Benefícios sociais', joinChecks('beneficios[]')) +
                                                linha('Documentos anexados', anexosNomes, 'Foto', fotoStatus);

                                            const endereco =
                                                '<h6 class="resumo-section-title mt-3 mb-1">Contato e Endereço</h6>' +
                                                '<span class="resumo-sub">Informações de contato e endereço</span>' +
                                                linha('Tipo de Moradia', txtSelect('tipo_moradia'), 'Valor do Aluguel', moedaBR($id('valor_aluguel')?.value)) +
                                                linha('CEP', val('cep'), 'Logradouro', val('logradouro')) +
                                                linha('Número', val('numero'), 'Bairro', txtSelect('bairro_id')) +
                                                linha('Complemento', val('complemento'), 'Estado', txtSelect('estado_end')) +
                                                linha('Município', val('municipio_id'), 'Ponto de Referência', val('referencia')) +
                                                linha('Celular', val('celular'), 'E-mail', val('email'));

                                            wrap.innerHTML = pessoais + adicionais + endereco;
                                        }
                                        form?.addEventListener('input', renderResumo);
                                        form?.addEventListener('change', renderResumo);
                                        renderResumo();

                                    })();
                                </script>

                                <!-- /STEP 5: RESUMO -->

                            </form>
                            <!-- /FORM-WIZARD -->

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </main>
    <!-- /CONTEÚDO -->

    <!-- FOOTER -->
    <footer>

        Rua Rio Aroã, Santa Efigênia — Coari/AM
        <a href="mailto:habitacao@coari.am.gov.br"></a>
        <div class="mt-1">Versão v1.0.0</div>

    </footer>
    <!-- /FOOTER -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Library Bundle Script -->
    <script src="./dist/assets/js/core/libs.min.js"></script>

    <!-- External Library Bundle Script -->
    <script src="./dist/assets/js/core/external.min.js"></script>

    <!-- Widgetchart Script -->
    <script src="./dist/assets/js/charts/widgetcharts.js"></script>

    <!-- mapchart Script -->
    <script src="./dist/assets/js/charts/vectore-chart.js"></script>
    <script src="./dist/assets/js/charts/dashboard.js"></script>

    <!-- fslightbox Script -->
    <script src="./dist/assets/js/plugins/fslightbox.js"></script>

    <!-- Settings Script -->
    <script src="./dist/assets/js/plugins/setting.js"></script>

    <!-- Slider-tab Script -->
    <script src="./dist/assets/js/plugins/slider-tabs.js"></script>

    <!-- App Script -->
    <script src="./dist/assets/js/hope-ui.js" defer></script>

</body>

</html>