<?php

declare(strict_types=1);

$pageDefinition = [
    'title' => 'Novo candidato',
    'description' => 'Cadastro visual em nove etapas independentes, sem envio ou persistência de dados.',
    'actions' => [['label' => 'Salvar rascunho', 'icon' => 'floppy']],
    'states' => ['loading', 'error', 'success', 'blocked'],
    'modal' => ['title' => 'Revisão do cadastro'],
];

ob_start();
?>
<section class="content-card pe-wizard-card" data-pe-wizard>
    <div class="pe-wizard-heading">
        <div>
            <div class="card-kicker">Cadastro demonstrativo</div>
            <h2>Nove etapas do candidato</h2>
            <p>Preencha cada painel para avançar. Nada será enviado ao servidor.</p>
        </div>
        <strong data-pe-progress-text>Etapa 1 de 9</strong>
    </div>
    <div class="progress pe-wizard-progress" role="progressbar" aria-label="Progresso do cadastro" aria-valuemin="1" aria-valuemax="9" aria-valuenow="1">
        <div class="progress-bar" data-pe-progress style="width:11.11%"></div>
    </div>
    <ol class="pe-wizard-steps" aria-label="Etapas do cadastro">
        <?php foreach (['Identificação', 'Contato', 'Endereço', 'Escolaridade', 'Experiência', 'Áreas de interesse', 'Disponibilidade', 'Documentos', 'Revisão'] as $index => $step): ?>
            <li><button type="button" data-pe-step-indicator="<?= $index ?>"<?= $index === 0 ? ' aria-current="step"' : '' ?>><span><?= $index + 1 ?></span><?= sigas_frontend_escape($step) ?></button></li>
        <?php endforeach; ?>
    </ol>
    <form data-pe-wizard-form novalidate>
        <section data-pe-step>
            <h3 tabindex="-1">1. Identificação</h3>
            <p>Dados básicos do perfil demonstrativo.</p>
            <div class="row g-3">
                <div class="col-md-7"><label class="form-label required" for="candidateName">Nome completo</label><input class="form-control" id="candidateName" name="candidate_name" autocomplete="name" required maxlength="120"></div>
                <div class="col-md-5"><label class="form-label required" for="candidateBirth">Data de nascimento</label><input class="form-control" id="candidateBirth" name="candidate_birth" type="date" autocomplete="bday" required></div>
                <div class="col-md-6"><label class="form-label required" for="candidateCpf">CPF</label><input class="form-control" id="candidateCpf" name="candidate_cpf" inputmode="numeric" placeholder="000.000.000-00" required pattern="[0-9.\\-]{11,14}"></div>
                <div class="col-md-6"><label class="form-label" for="candidateSocialName">Nome social</label><input class="form-control" id="candidateSocialName" name="candidate_social_name" maxlength="120"></div>
            </div>
        </section>
        <section data-pe-step hidden>
            <h3 tabindex="-1">2. Contato</h3>
            <p>Formas de contato preferenciais.</p>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label required" for="candidatePhone">Telefone</label><input class="form-control" id="candidatePhone" name="candidate_phone" type="tel" autocomplete="tel" required maxlength="20"></div>
                <div class="col-md-6"><label class="form-label" for="candidateEmail">E-mail</label><input class="form-control" id="candidateEmail" name="candidate_email" type="email" autocomplete="email" maxlength="160"></div>
                <div class="col-12"><label class="form-label" for="preferredContact">Contato preferencial</label><select class="form-select" id="preferredContact" name="preferred_contact"><option>Telefone</option><option>Mensagem</option><option>E-mail</option></select></div>
            </div>
        </section>
        <section data-pe-step hidden>
            <h3 tabindex="-1">3. Endereço</h3>
            <p>Referência territorial do candidato.</p>
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label required" for="candidateAddress">Endereço</label><input class="form-control" id="candidateAddress" name="candidate_address" autocomplete="street-address" required maxlength="180"></div>
                <div class="col-md-4"><label class="form-label required" for="candidateNeighborhood">Bairro</label><input class="form-control" id="candidateNeighborhood" name="candidate_neighborhood" required maxlength="80"></div>
                <div class="col-md-4"><label class="form-label" for="candidateZone">Zona</label><select class="form-select" id="candidateZone" name="candidate_zone"><option>Urbana</option><option>Rural</option></select></div>
                <div class="col-md-8"><label class="form-label" for="candidateReference">Ponto de referência</label><input class="form-control" id="candidateReference" name="candidate_reference" maxlength="140"></div>
            </div>
        </section>
        <section data-pe-step hidden>
            <h3 tabindex="-1">4. Escolaridade</h3>
            <p>Situação educacional atual.</p>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label required" for="candidateEducation">Escolaridade</label><select class="form-select" id="candidateEducation" name="candidate_education" required><option value="">Selecione</option><option>Ensino fundamental</option><option>Ensino médio</option><option>Superior incompleto</option><option>Superior completo</option></select></div>
                <div class="col-md-6"><label class="form-label" for="candidateSchool">Instituição de ensino</label><input class="form-control" id="candidateSchool" name="candidate_school" maxlength="140"></div>
                <div class="col-md-6"><label class="form-label" for="candidateCourse">Curso</label><input class="form-control" id="candidateCourse" name="candidate_course" maxlength="100"></div>
                <div class="col-md-6"><label class="form-label" for="candidateStudyShift">Turno</label><select class="form-select" id="candidateStudyShift" name="candidate_study_shift"><option>Não se aplica</option><option>Manhã</option><option>Tarde</option><option>Noite</option></select></div>
            </div>
        </section>
        <section data-pe-step hidden>
            <h3 tabindex="-1">5. Experiência</h3>
            <p>Vivências profissionais e competências.</p>
            <div class="row g-3">
                <div class="col-md-5"><label class="form-label required" for="candidateExperience">Experiência anterior</label><select class="form-select" id="candidateExperience" name="candidate_experience" required><option value="">Selecione</option><option>Sem experiência</option><option>Até 1 ano</option><option>Mais de 1 ano</option></select></div>
                <div class="col-md-7"><label class="form-label" for="candidateLastRole">Última função</label><input class="form-control" id="candidateLastRole" name="candidate_last_role" maxlength="100"></div>
                <div class="col-12"><label class="form-label" for="candidateSkills">Competências e atividades realizadas</label><textarea class="form-control" id="candidateSkills" name="candidate_skills" rows="3" maxlength="500"></textarea></div>
            </div>
        </section>
        <section data-pe-step hidden>
            <h3 tabindex="-1">6. Áreas de interesse</h3>
            <p>Selecione ao menos uma área profissional.</p>
            <fieldset class="pe-choice-grid">
                <legend class="visually-hidden">Áreas profissionais</legend>
                <?php foreach (['Administrativo', 'Comércio', 'Serviços', 'Tecnologia', 'Atendimento', 'Logística'] as $index => $area): ?>
                    <label><input class="form-check-input" type="checkbox" name="candidate_areas[]" value="<?= sigas_frontend_escape($area) ?>" data-pe-area><span><?= sigas_frontend_escape($area) ?></span></label>
                <?php endforeach; ?>
            </fieldset>
        </section>
        <section data-pe-step hidden>
            <h3 tabindex="-1">7. Disponibilidade</h3>
            <p>Horários e condições para participação.</p>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label required" for="candidateAvailability">Disponibilidade</label><select class="form-select" id="candidateAvailability" name="candidate_availability" required><option value="">Selecione</option><option>Integral</option><option>Manhã</option><option>Tarde</option></select></div>
                <div class="col-md-6"><label class="form-label required" for="candidateHours">Carga horária</label><select class="form-select" id="candidateHours" name="candidate_hours" required><option value="">Selecione</option><option>20 horas</option><option>30 horas</option><option>40 horas</option></select></div>
                <div class="col-12"><label class="form-label" for="candidateRestriction">Restrições ou observações</label><textarea class="form-control" id="candidateRestriction" name="candidate_restriction" rows="3" maxlength="400"></textarea></div>
            </div>
        </section>
        <section data-pe-step hidden>
            <h3 tabindex="-1">8. Documentos</h3>
            <p>Marque os documentos apresentados para conferência visual.</p>
            <div class="pe-document-checklist">
                <?php foreach (['Documento de identificação', 'CPF', 'Comprovante de residência', 'Comprovante de escolaridade', 'Currículo'] as $document): ?>
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="candidate_documents[]" value="<?= sigas_frontend_escape($document) ?>"><span class="form-check-label"><?= sigas_frontend_escape($document) ?></span></label>
                <?php endforeach; ?>
            </div>
            <div class="frontend-demo-notice mt-3"><i class="bi bi-info-circle"></i><div><strong>Envio reservado para integração futura</strong><span>Nenhum arquivo é solicitado ou transmitido nesta etapa visual.</span></div></div>
        </section>
        <section data-pe-step hidden>
            <h3 tabindex="-1">9. Revisão</h3>
            <p>Confira o resumo antes da confirmação visual.</p>
            <dl class="pe-review-grid" data-pe-review></dl>
            <div class="frontend-demo-notice"><i class="bi bi-shield-check"></i><div><strong>Confirmação demonstrativa</strong><span>A finalização não cria cadastro nem altera dados.</span></div></div>
        </section>
        <div class="pe-wizard-actions">
            <button class="btn btn-light" type="button" data-pe-prev disabled><i class="bi bi-arrow-left"></i>Anterior</button>
            <button class="btn btn-outline-primary" type="button" data-pe-save-draft><i class="bi bi-floppy"></i>Salvar rascunho</button>
            <button class="btn btn-primary" type="button" data-pe-next>Próximo<i class="bi bi-arrow-right"></i></button>
        </div>
    </form>
</section>
<?php
$pageCustomContent = (string) ob_get_clean();
