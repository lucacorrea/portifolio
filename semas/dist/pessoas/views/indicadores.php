<section class="pc-kpi-grid mb-3">
  <article class="pc-kpi"><span>Total cadastrados</span><strong><?= pc_h($indicadores['total']) ?></strong><small>Base ANEXO</small></article>
  <article class="pc-kpi pc-kpi--info"><span>Exibidos</span><strong><?= pc_h($result['total']) ?></strong><small>Após os filtros</small></article>
  <article class="pc-kpi pc-kpi--warning"><span>Sem CPF</span><strong><?= pc_h($indicadores['sem_cpf']) ?></strong><small>Não podem ser cruzados por CPF</small></article>
  <article class="pc-kpi pc-kpi--success"><span>Bolsa Família</span><strong><?= pc_h($indicadores['pbf']) ?></strong><small>Informado no ANEXO</small></article>
  <article class="pc-kpi"><span>BPC</span><strong><?= pc_h($indicadores['bpc']) ?></strong><small>Informado no ANEXO</small></article>
  <article class="pc-kpi"><span>Benefício municipal</span><strong><?= pc_h($indicadores['municipal']) ?></strong><small>Informado no ANEXO</small></article>
</section>
