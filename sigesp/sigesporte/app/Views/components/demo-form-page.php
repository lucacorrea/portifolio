<?php
declare(strict_types=1);
use Sigesp\Core\View;

$page = is_array($page ?? null) ? $page : [];
$module = (string) ($page['module'] ?? 'modulo');
$columns = is_array($page['columns'] ?? null) ? $page['columns'] : [];
$heading = (string) ($page['action'] ?? 'Novo registro');
$fieldType = static function (string $key): string {
    if (str_contains($key, 'data') || str_contains($key, 'validade') || str_contains($key, 'inicio')) return 'date';
    if (str_contains($key, 'email')) return 'email';
    if (str_contains($key, 'valor') || str_contains($key, 'idade') || str_contains($key, 'atletas') || str_contains($key, 'estoque')) return 'number';
    return 'text';
};
?>
<?php View::component('page-header',['eyebrow'=>'SIGESP · Operação simulada','heading'=>$heading,'description'=>'Preencha os campos para visualizar o fluxo. Nenhuma informação será enviada ou armazenada.']); ?>
<form class="form form-panel" data-demo-form data-demo-redirect="/<?= View::e($module) ?>" data-demo-message="Operação simulada com sucesso no ambiente de demonstração.">
    <fieldset class="form-section"><legend>Informações principais</legend><p>Todos os campos pertencem somente à interface demonstrativa.</p><div class="form-grid">
        <?php foreach(array_slice($columns,0,6,true) as $key=>$label): ?>
            <?php if($key==='status'): ?><label class="field"><?= View::e((string)$label) ?><select name="<?= View::e((string)$key) ?>"><option>Ativo</option><option>Pendente</option><option>Concluído</option><option>Inativo</option></select></label>
            <?php else: ?><label class="field"><?= View::e((string)$label) ?><input type="<?= View::e($fieldType((string)$key)) ?>" name="<?= View::e((string)$key) ?>" placeholder="Informação fictícia" <?= $key===array_key_first($columns)?'required':'' ?>></label><?php endif; ?>
        <?php endforeach; ?>
        <label class="field">Observações<textarea name="observacoes" rows="4" placeholder="Observação demonstrativa"></textarea></label>
    </div></fieldset>
    <?php if(in_array($module,['equipes','eventos','espacos-esportivos','materiais','usuarios'],true)): ?><fieldset class="form-section"><legend>Imagem</legend><p>O arquivo ficará somente neste navegador.</p><?php View::component('upload-area',['label'=>'Selecionar imagem','hint'=>'Preview local · arquivo não enviado','accept'=>'image/jpeg,image/png,image/webp']); ?></fieldset><?php endif; ?>
    <footer class="form-actions"><a class="button button--secondary" href="<?= View::e(View::url('/'.$module)) ?>">Cancelar</a><button class="button button--secondary" type="button" data-demo-action="draft">Salvar rascunho</button><button class="button" type="submit">Confirmar simulação</button></footer>
</form>
