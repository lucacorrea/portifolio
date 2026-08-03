<?php
declare(strict_types=1);
use Sigesp\Core\View;

$athlete = is_array($atleta ?? null) ? $atleta : [];
$editing = $athlete !== [];
$title = $editing ? 'Editar atleta' : 'Novo atleta';
$pageId = 'atletas-form';
$value = static fn (string $key, string $fallback = ''): string => (string) ($athlete[$key] ?? $fallback);
?>
<?php View::component('page-header', ['eyebrow'=>'Gestão de atletas · Demonstração','heading'=>$title,'description'=>'Percorra as seis etapas. Nenhuma informação será enviada ou armazenada.']); ?>
<form class="form form-panel" data-demo-form data-demo-redirect="/atletas/1" data-demo-message="Cadastro simulado com sucesso no ambiente de demonstração.">
    <div class="stepper" data-stepper role="tablist" aria-label="Etapas do cadastro">
        <?php foreach (['Dados pessoais','Endereço','Responsável','Perfil esportivo','Documentos','Revisão'] as $index=>$step): ?><button type="button" data-step role="tab"><span><?= $index+1 ?></span><?= View::e($step) ?></button><?php endforeach; ?>
    </div>
    <section data-step-panel role="tabpanel">
        <fieldset class="form-section"><legend>Dados pessoais</legend><p>Use somente informações fictícias nesta demonstração.</p><div class="form-grid">
            <label class="field">Nome completo<input name="nome" required maxlength="180" value="<?= View::e($value('nome')) ?>" placeholder="Nome fictício"></label>
            <label class="field">Nome social<input name="nome_social" maxlength="180" value="<?= View::e($value('nome_social')) ?>"></label>
            <label class="field">CPF<input name="cpf" inputmode="numeric" data-cpf value="<?= View::e($value('cpf','000.000.000-00')) ?>" aria-describedby="cpf-demo"><small id="cpf-demo">Utilize apenas CPF fictício ou mascarado.</small></label>
            <label class="field">Data de nascimento<input name="nascimento" type="date" required value="<?= View::e($value('nascimento','2008-05-14')) ?>"></label>
            <label class="field">Sexo<select name="sexo"><option>Feminino</option><option>Masculino</option><option>Não informado</option></select></label>
            <label class="field">Telefone<input name="telefone" inputmode="tel" value="<?= View::e($value('telefone','(97) 99999-0000')) ?>"></label>
            <label class="field">E-mail<input name="email" type="email" value="<?= View::e($value('email','atleta@demonstracao.local')) ?>"></label>
            <div><?php View::component('upload-area',['label'=>'Foto do atleta','hint'=>'Preview local · JPG ou PNG','accept'=>'image/jpeg,image/png']); ?></div>
        </div></fieldset>
    </section>
    <section data-step-panel role="tabpanel" hidden>
        <fieldset class="form-section"><legend>Endereço</legend><p>Endereço genérico usado somente na apresentação.</p><div class="form-grid">
            <label class="field">CEP<input inputmode="numeric" value="69000-000"></label><label class="field">Município<input value="Município Demonstrativo"></label><label class="field">Bairro<input value="<?= View::e($value('bairro','Centro')) ?>"></label><label class="field">Logradouro<input value="Rua das Quadras"></label><label class="field">Número<input value="100"></label><label class="field">Complemento<input value="Próximo ao ginásio"></label>
        </div></fieldset>
    </section>
    <section data-step-panel role="tabpanel" hidden>
        <fieldset class="form-section"><legend>Responsável legal</legend><p>Exibido para atletas menores de idade.</p><div class="form-grid">
            <label class="field">Nome<input value="Juliana Martins"></label><label class="field">CPF mascarado<input value="***.***.***-10"></label><label class="field">Parentesco<select><option>Mãe</option><option>Pai</option><option>Responsável legal</option></select></label><label class="field">Telefone<input value="(97) 99999-0010"></label><label class="field">E-mail<input type="email" value="responsavel@demonstracao.local"></label><label class="field">Autorização<select><option>Assinada</option><option>Pendente</option></select></label>
        </div></fieldset>
    </section>
    <section data-step-panel role="tabpanel" hidden>
        <fieldset class="form-section"><legend>Perfil esportivo</legend><p>Vínculos esportivos simulados para a apresentação.</p><div class="form-grid">
            <label class="field">Modalidade<select><option><?= View::e($value('modalidade','Voleibol')) ?></option><option>Futsal</option><option>Atletismo</option><option>Natação</option></select></label><label class="field">Categoria<select><option><?= View::e($value('categoria','Sub-20')) ?></option><option>Sub-17</option><option>Adulto</option></select></label><label class="field">Equipe<select><option><?= View::e($value('equipe','Seleção Municipal')) ?></option><option>Projeto Centro</option></select></label><label class="field">Posição<input value="Ponteira"></label><label class="field">Altura<input value="1,72 m"></label><label class="field">Peso<input value="64 kg"></label>
        </div></fieldset>
    </section>
    <section data-step-panel role="tabpanel" hidden>
        <fieldset class="form-section"><legend>Documentos</legend><p>Arquivos permanecem no navegador e podem ser removidos antes da simulação.</p><div class="form-grid form-grid--two"><?php View::component('upload-area',['label'=>'Documento de identificação','hint'=>'PDF, JPG ou PNG','accept'=>'.pdf,image/jpeg,image/png']); ?><?php View::component('upload-area',['label'=>'Comprovante de residência','hint'=>'PDF, JPG ou PNG','accept'=>'.pdf,image/jpeg,image/png']); ?><?php View::component('upload-area',['label'=>'Atestado médico','hint'=>'PDF, JPG ou PNG','accept'=>'.pdf,image/jpeg,image/png']); ?><?php View::component('upload-area',['label'=>'Autorização do responsável','hint'=>'PDF, JPG ou PNG','accept'=>'.pdf,image/jpeg,image/png']); ?></div></fieldset>
    </section>
    <section data-step-panel role="tabpanel" hidden>
        <fieldset class="form-section"><legend>Revisão</legend><p>Confirme a simulação do cadastro. Nenhum dado ou arquivo será transmitido ao servidor.</p><div class="alert alert--success" role="status">✓ Etapas preenchidas para fins demonstrativos. Você ainda pode voltar e revisar qualquer seção.</div><label><input type="checkbox" required> Confirmo que esta operação é apenas uma simulação com dados fictícios.</label></fieldset>
    </section>
    <footer class="form-actions"><a class="button button--secondary" href="<?= View::e(View::url('/atletas')) ?>">Cancelar</a><button class="button button--secondary" type="button" data-demo-action="draft">Salvar rascunho</button><button class="button" type="submit"><?= $editing?'Salvar alterações':'Finalizar cadastro' ?></button></footer>
</form>
