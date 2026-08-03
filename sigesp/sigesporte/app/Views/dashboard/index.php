<?php
declare(strict_types=1);
use Sigesp\Core\View;

$title = 'Dashboard';
$pageId = 'dashboard';
$stats = (array) ($stats ?? []);
$chart = (array) ($chart ?? []);
$charts = (array) ($charts ?? []);
$recentAthletes = (array) ($recentAthletes ?? []);
$upcomingEvents = (array) ($upcomingEvents ?? []);
$expiringDocuments = (array) ($expiringDocuments ?? []);
$activities = (array) ($activities ?? []);
$alerts = (array) ($alerts ?? []);
$ranking = (array) ($ranking ?? []);
$teams = (array) ($teams ?? []);
$bars = static function (array $values): void {
    $max = max(array_map('intval', $values) ?: [1]);
    foreach ($values as $label => $value) {
        if (is_array($value)) { $label = (string) ($value['nome'] ?? $label); $value = (int) ($value['total'] ?? 0); }
        $width = max(4, (int) round(((int) $value / max(1, $max)) * 100));
        ?><div class="demo-chart__row"><span><?= View::e((string) $label) ?></span><span class="demo-chart__track"><i class="demo-chart__bar" style="width:<?= $width ?>%"></i></span><strong><?= View::e((string) $value) ?></strong></div><?php
    }
};
?>
<?php View::component('page-header',['eyebrow'=>'Painel institucional · 03 de agosto de 2026','heading'=>'Olá, Marcos','description'=>'Acompanhe os principais indicadores fictícios da gestão esportiva municipal.','actionLabel'=>'Cadastrar atleta','actionHref'=>'/atletas/novo','secondaryLabel'=>'Gerar relatório','secondaryMessage'=>'Acesse a central de relatórios para explorar a demonstração.']); ?>
<section class="stats-grid" aria-label="Indicadores principais"><?php foreach([
    ['◉','Atletas cadastrados',$stats['total']??1248,'Base municipal'],['✓','Atletas ativos',$stats['ativos']??1102,'88,3% da base'],['+','Novos no mês',$stats['novos_mes']??84,'Agosto de 2026'],['!','Cadastros incompletos',$stats['incompletos']??32,'Precisam de revisão'],['▣','Documentos pendentes',$stats['pendentes']??47,'Aguardam análise'],['⌁','Documentos vencidos',$stats['vencidos']??18,'Exigem atualização'],['◌','Modalidades',$stats['modalidades']??14,'Em funcionamento'],['♜','Equipes',$stats['equipes']??26,'Ativas no município']
] as [$icon,$label,$value,$detail]) View::component('stats-card',compact('icon','label','value','detail')); ?></section>

<section class="dashboard-grid">
    <article class="surface"><div class="surface__header"><div><h2>Atletas por modalidade</h2><p>Distribuição dos vínculos ativos.</p></div><a href="<?= View::e(View::url('/modalidades')) ?>">Ver modalidades</a></div><div class="demo-chart"><?php $chartMap=[]; foreach($chart as $item) $chartMap[(string)($item['nome']??'Modalidade')]=(int)($item['total']??0); $bars($chartMap); ?></div></article>
    <article class="surface"><div class="surface__header"><div><h2>Evolução dos cadastros</h2><p>Novos registros por mês.</p></div></div><div class="demo-chart"><?php $bars((array)($charts['evolucao']??[])); ?></div></article>
    <article class="surface"><div class="surface__header"><div><h2>Faixa etária</h2><p>Distribuição dos atletas.</p></div></div><div class="demo-chart"><?php $bars((array)($charts['faixaEtaria']??[])); ?></div></article>
    <article class="surface"><div class="surface__header"><div><h2>Situação documental</h2><p>Regularidade da base demonstrativa.</p></div></div><div class="demo-chart"><?php $bars((array)($charts['documentos']??[])); ?></div></article>
    <article class="surface"><div class="surface__header"><div><h2>Frequência mensal</h2><p>Percentual médio de presença.</p></div></div><div class="demo-chart"><?php $bars((array)($charts['frequencia']??[])); ?></div></article>
    <article class="surface"><div class="surface__header"><div><h2>Atletas por sexo</h2><p>Composição cadastral informada.</p></div></div><div class="demo-chart"><?php $bars((array)($charts['sexo']??[])); ?></div></article>
    <article class="surface dashboard-wide"><div class="surface__header"><div><h2>Atletas por bairro</h2><p>Abrangência territorial dos programas.</p></div></div><div class="demo-chart"><?php $bars((array)($charts['bairros']??[])); ?></div></article>
</section>

<section class="dashboard-grid dashboard-section">
    <article class="surface"><div class="surface__header"><div><h2>Atletas recentes</h2><p>Últimos cadastros demonstrativos.</p></div><a href="<?= View::e(View::url('/atletas')) ?>">Ver todos</a></div><div class="mini-list"><?php foreach($recentAthletes as $athlete): ?><div class="mini-list__item"><?php View::component('avatar',['name'=>(string)($athlete['nome']??'Atleta')]); ?><div><strong><?= View::e((string)($athlete['nome']??'Atleta')) ?></strong><small><?= View::e((string)($athlete['modalidade']??'Modalidade')) ?> · <?= View::e((string)($athlete['codigo']??'')) ?></small></div><a href="<?= View::e(View::url('/atletas/'.(int)($athlete['id']??1))) ?>">Perfil</a></div><?php endforeach; ?></div></article>
    <article class="surface"><div class="surface__header"><div><h2>Próximos eventos</h2><p>Agenda esportiva municipal.</p></div><a href="<?= View::e(View::url('/eventos')) ?>">Ver agenda</a></div><div class="mini-list"><?php foreach($upcomingEvents as $event): ?><div class="mini-list__item"><span class="avatar">★</span><div><strong><?= View::e((string)($event['nome']??'Evento')) ?></strong><small><?= View::e((string)($event['data']??'Agendado')) ?> · <?= View::e((string)($event['local']??'Espaço municipal')) ?></small></div><?php View::component('badge',['label'=>(string)($event['status']??'Agendado'),'tone'=>'warning']); ?></div><?php endforeach; ?></div></article>
    <article class="surface"><div class="surface__header"><div><h2>Documentos vencendo</h2><p>Acompanhamento prioritário.</p></div><a href="<?= View::e(View::url('/documentos')) ?>">Analisar</a></div><div class="mini-list"><?php foreach($expiringDocuments as $document): ?><div class="mini-list__item"><span class="avatar">▣</span><div><strong><?= View::e((string)($document['atleta']??'Atleta')) ?></strong><small><?= View::e((string)($document['tipo']??'Documento')) ?> · <?= View::e((string)($document['validade']??'Sem validade')) ?></small></div></div><?php endforeach; ?></div></article>
    <article class="surface"><div class="surface__header"><div><h2>Alertas</h2><p>Itens que merecem atenção.</p></div></div><div class="mini-list"><?php foreach($alerts as $alert): ?><div class="mini-list__item"><span class="avatar">!</span><div><strong><?= View::e((string)($alert['title']??'Alerta')) ?></strong><small><?= View::e((string)($alert['detail']??'')) ?></small></div></div><?php endforeach; ?></div></article>
    <article class="surface"><div class="surface__header"><div><h2>Atividades recentes</h2><p>Movimentações simuladas.</p></div></div><?php View::component('activity-timeline',['items'=>$activities]); ?></article>
    <article class="surface"><div class="surface__header"><div><h2>Ranking de modalidades</h2><p>Atletas vinculados.</p></div></div><div class="demo-chart"><?php $bars($ranking); ?></div></article>
    <article class="surface dashboard-wide"><div class="surface__header"><div><h2>Resumo de equipes</h2><p>Elencos e situação da operação.</p></div><a href="<?= View::e(View::url('/equipes')) ?>">Ver equipes</a></div><div class="module-highlights"><?php foreach($teams as $team): ?><article class="module-highlight"><strong><?= View::e((string)($team['nome']??'Equipe')) ?></strong><span><?= View::e((string)($team['modalidade']??'Modalidade')) ?> · <?= View::e((string)($team['atletas']??0)) ?> atletas · <?= View::e((string)($team['treinador']??'Treinador')) ?></span><a href="<?= View::e(View::url('/equipes/perfil')) ?>">Abrir equipe</a></article><?php endforeach; ?></div></article>
    <article class="surface dashboard-wide"><div class="surface__header"><div><h2>Atalhos</h2><p>Fluxos mais usados na demonstração.</p></div></div><?php View::component('quick-actions',['actions'=>[['href'=>'/atletas/novo','icon'=>'+','label'=>'Novo atleta'],['href'=>'/frequencias/registrar','icon'=>'✓','label'=>'Registrar frequência'],['href'=>'/eventos/novo','icon'=>'★','label'=>'Criar evento'],['href'=>'/reservas/novo','icon'=>'◴','label'=>'Reservar espaço'],['href'=>'/beneficios/novo','icon'=>'♥','label'=>'Conceder benefício'],['href'=>'/relatorios','icon'=>'▥','label'=>'Gerar relatório']]]); ?></article>
</section>
