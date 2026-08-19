<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/repository.php';

$pageDefinition = [
    'title' => 'Triagem social',
    'description' => 'Ficha de cadastro do Programa Meu Primeiro Emprego, conforme o formulário utilizado pela Assistência Social.',
    'actions' => [['label' => 'Ver candidatos', 'icon' => 'people', 'href' => 'primeiro-emprego/candidatos.php']],
    'states' => ['success', 'error'],
    'demo' => false,
    'show_states' => false,
    'modal' => ['title' => 'Cadastro'],
];

$message = null;
$dbReady = pe_db_ready() && pe_schema_ready();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pe_action']) && $_POST['pe_action'] === 'save_triage') {
    try {
        pe_verify_csrf();
        $id = pe_save_triage(pe_db(), $_POST);
        $message = ['type' => 'success', 'text' => 'Triagem cadastrada com sucesso. Registro #' . $id . '.'];
        $_POST = [];
    } catch (Throwable $e) {
        $message = ['type' => 'danger', 'text' => $e->getMessage()];
    }
}

ob_start();
?>
<section class="content-card pe-form-card">
    <?php if (!$dbReady): ?><?= pe_db_notice() ?><?php endif; ?>
    <?php if ($message): ?><div class="alert alert-<?= pe_h($message['type']) ?>"><?= pe_h($message['text']) ?></div><?php endif; ?>

    <div class="pe-form-header">
        <div>
            <div class="card-kicker">Meu Primeiro Emprego</div>
            <h2>Ficha de Triagem Social</h2>
            <p>Os dados desta ficha alimentam automaticamente a visita social, a ficha cadastral e os relatórios.</p>
        </div>
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir ficha</button>
    </div>

    <form method="post" class="pe-real-form" autocomplete="off" novalidate>
        <?= pe_csrf_field() ?>
        <input type="hidden" name="pe_action" value="save_triage">

        <fieldset <?= !$dbReady ? 'disabled' : '' ?>>
            <div class="pe-section-title"><span>1</span><div><strong>Dados de identificação</strong><small>Dados pessoais e da entrevista.</small></div></div>
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label required">Data da entrevista</label><input class="form-control" type="date" name="data_entrevista" value="<?= pe_h($_POST['data_entrevista'] ?? date('Y-m-d')) ?>" required></div>
                <div class="col-md-9"><label class="form-label required">Nome completo do(a) beneficiário(a)</label><input class="form-control" name="nome" maxlength="160" value="<?= pe_h($_POST['nome'] ?? '') ?>" required></div>
                <div class="col-md-3"><label class="form-label">Sexo</label><select class="form-select" name="sexo"><option value="">Selecione</option><?php foreach (['Feminino','Masculino','Outro/Não informar'] as $v): ?><option<?= (($_POST['sexo'] ?? '') === $v) ? ' selected' : '' ?>><?= pe_h($v) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Data de nascimento</label><input class="form-control" type="date" name="data_nascimento" value="<?= pe_h($_POST['data_nascimento'] ?? '') ?>"><small class="text-muted">Se não for informada, o candidato será salvo em Revisar Data de Nascimento.</small></div>
                <div class="col-md-3"><label class="form-label">RG</label><input class="form-control" name="rg" maxlength="40" value="<?= pe_h($_POST['rg'] ?? '') ?>"></div>
                <div class="col-md-3"><label class="form-label">CPF</label><input class="form-control" name="cpf" inputmode="numeric" maxlength="14" placeholder="000.000.000-00" value="<?= pe_h($_POST['cpf'] ?? '') ?>"><small class="text-muted">Ausente, inconsistente ou duplicado não impede o cadastro; será enviado para revisão.</small></div>
                <div class="col-md-3"><label class="form-label">Estado civil</label><input class="form-control" name="estado_civil" maxlength="40" value="<?= pe_h($_POST['estado_civil'] ?? '') ?>"></div>
                <div class="col-md-3"><label class="form-label">NIS/CadÚnico</label><input class="form-control" name="nis" maxlength="32" value="<?= pe_h($_POST['nis'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Se declara</label><div class="pe-inline-options"><?php foreach (['Branca','Indígena','Preta','Parda','Amarela'] as $v): ?><label><input type="radio" name="cor_raca" value="<?= pe_h($v) ?>"<?= (($_POST['cor_raca'] ?? '') === $v) ? ' checked' : '' ?>> <?= pe_h($v) ?></label><?php endforeach; ?></div></div>
            </div>

            <div class="pe-section-title"><span>2</span><div><strong>Endereço e contato</strong><small>Localização do candidato e canais de contato.</small></div></div>
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label">Rua</label><input class="form-control" name="rua" maxlength="180" value="<?= pe_h($_POST['rua'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Bairro</label><input class="form-control" name="bairro" maxlength="100" value="<?= pe_h($_POST['bairro'] ?? '') ?>"></div>
                <div class="col-md-8"><label class="form-label">Ponto de referência</label><input class="form-control" name="ponto_referencia" maxlength="180" value="<?= pe_h($_POST['ponto_referencia'] ?? '') ?>"></div>
                <div class="col-md-2"><label class="form-label">Município</label><input class="form-control" name="municipio" maxlength="100" value="<?= pe_h($_POST['municipio'] ?? 'Coari') ?>"></div>
                <div class="col-md-2"><label class="form-label">CEP</label><input class="form-control" name="cep" inputmode="numeric" maxlength="9" value="<?= pe_h($_POST['cep'] ?? '') ?>"></div>
                <div class="col-md-3"><label class="form-label">Contato</label><input class="form-control" name="telefone" inputmode="tel" maxlength="20" value="<?= pe_h($_POST['telefone'] ?? '') ?>"><small class="text-muted">Sem telefone ou fora do padrão será marcado como Revisar Telefone.</small></div>
                <div class="col-md-3"><label class="form-label">WhatsApp</label><input class="form-control" name="whatsapp" inputmode="tel" maxlength="16" value="<?= pe_h($_POST['whatsapp'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">E-mail</label><input class="form-control" type="email" name="email" maxlength="160" value="<?= pe_h($_POST['email'] ?? '') ?>"></div>
            </div>

            <div class="pe-section-title"><span>3</span><div><strong>Informações familiares</strong><small>Composição e renda familiar.</small></div></div>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Nome do responsável familiar</label><input class="form-control" name="responsavel_familiar" maxlength="160" value="<?= pe_h($_POST['responsavel_familiar'] ?? '') ?>"></div>
                <div class="col-md-2"><label class="form-label">Membros da família</label><input class="form-control" type="number" min="0" max="99" name="total_membros_familia" value="<?= pe_h($_POST['total_membros_familia'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Atividade dos pais/responsáveis</label><input class="form-control" name="atividade_responsaveis" maxlength="220" value="<?= pe_h($_POST['atividade_responsaveis'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Renda familiar mensal (R$)</label><input class="form-control" name="renda_familiar_mensal" inputmode="decimal" placeholder="0,00" value="<?= pe_h($_POST['renda_familiar_mensal'] ?? '') ?>"></div>
            </div>

            <div class="pe-section-title"><span>4</span><div><strong>Escolaridade</strong><small>Situação escolar atual.</small></div></div>
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">Matriculado e frequentando?</label><select class="form-select" name="matriculado"><option value="">Selecione</option><option<?= (($_POST['matriculado'] ?? '') === 'Sim') ? ' selected' : '' ?>>Sim</option><option<?= (($_POST['matriculado'] ?? '') === 'Não') ? ' selected' : '' ?>>Não</option></select></div>
                <div class="col-md-5"><label class="form-label">Nome da unidade de ensino</label><input class="form-control" name="instituicao_ensino" maxlength="180" value="<?= pe_h($_POST['instituicao_ensino'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Escolaridade</label><select class="form-select" name="escolaridade"><option value="">Selecione</option><?php foreach (['Ensino Fundamental Incompleto','Ensino Fundamental Completo','Ensino Médio Incompleto','Ensino Médio Completo','Ensino Superior Incompleto','Ensino Superior Completo'] as $v): ?><option<?= (($_POST['escolaridade'] ?? '') === $v) ? ' selected' : '' ?>><?= pe_h($v) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Situação escolar</label><select class="form-select" name="situacao_escolar"><option value="">Selecione</option><option>Cursando</option><option>Concluído</option></select></div>
                <div class="col-md-3"><label class="form-label">Turno de estudo</label><select class="form-select" name="turno_estudo"><option value="">Selecione</option><option>Matutino</option><option>Vespertino</option><option>Noturno</option><option>Integral</option></select></div>
            </div>

            <div class="pe-section-title"><span>5</span><div><strong>Situação habitacional</strong><small>Características básicas da moradia.</small></div></div>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Situação</label><select class="form-select" name="situacao_habitacional"><option value="">Selecione</option><option>Casa própria</option><option>Área de risco</option><option>Alugada</option><option>Ocupação / invasão</option></select></div>
                <div class="col-md-4"><label class="form-label">Condições da moradia</label><select class="form-select" name="condicao_moradia"><option value="">Selecione</option><option>Alvenaria</option><option>Madeira</option><option>Mista</option><option>Precária</option></select></div>
                <div class="col-md-2"><label class="form-label">Nº de cômodos</label><input class="form-control" type="number" min="0" max="99" name="numero_comodos" value="<?= pe_h($_POST['numero_comodos'] ?? '') ?>"></div>
                <div class="col-md-2"><label class="form-label">Água tratada</label><select class="form-select" name="agua_tratada"><option value="">-</option><option>Sim</option><option>Não</option></select></div>
                <div class="col-md-2"><label class="form-label">Energia elétrica</label><select class="form-select" name="energia_eletrica"><option value="">-</option><option>Sim</option><option>Não</option></select></div>
                <div class="col-md-2"><label class="form-label">Coleta de lixo</label><select class="form-select" name="coleta_lixo"><option value="">-</option><option>Sim</option><option>Não</option></select></div>
            </div>

            <div class="pe-section-title"><span>6</span><div><strong>Vulnerabilidades sociais</strong><small>Assinale as situações que se aplicam.</small></div></div>
            <div class="pe-choice-grid pe-choice-grid--2">
                <?php $selectedV = isset($_POST['vulnerabilidades']) && is_array($_POST['vulnerabilidades']) ? $_POST['vulnerabilidades'] : []; foreach (['Renda inferior a 1/2 salário mínimo per capita','Desemprego prolongado','Trabalho informal ou precário','Moradia em área de risco','Situação de violência (doméstica, urbana, etc.)','Uso de substâncias químicas (álcool/drogas)','Presença de pessoa com deficiência'] as $v): ?>
                    <label><input class="form-check-input" type="checkbox" name="vulnerabilidades[]" value="<?= pe_h($v) ?>"<?= in_array($v, $selectedV, true) ? ' checked' : '' ?>><span><?= pe_h($v) ?></span></label>
                <?php endforeach; ?>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-md-8"><label class="form-label">Outro</label><input class="form-control" name="vulnerabilidade_outro" maxlength="220" value="<?= pe_h($_POST['vulnerabilidade_outro'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Técnico responsável pela triagem</label><input class="form-control" name="tecnico_triagem" maxlength="160" value="<?= pe_h($_POST['tecnico_triagem'] ?? '') ?>"></div>
            </div>

            <div class="pe-declaration">
                <strong>Declaração</strong>
                <p>Declaro que as informações prestadas nesta ficha são verdadeiras e autorizo o uso dos dados para fins de inclusão no Programa Meu Primeiro Emprego, observadas as normas aplicáveis de proteção de dados.</p>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4 pe-no-print">
                <button class="btn btn-light" type="reset">Limpar</button>
                <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Salvar triagem</button>
            </div>
        </fieldset>
    </form>
</section>
<?php
$pageCustomContent = (string) ob_get_clean();
