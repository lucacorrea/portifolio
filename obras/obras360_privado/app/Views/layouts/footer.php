<?php if ($user && (($_GET['format']??'')!=='print')): ?>
  <footer class="site-footer"><span>OBRAS360 · Secretaria Municipal de Obras e Planejamento · Coari/AM</span><span>Dados de teste somente quando carregados pela instalação de demonstração</span></footer>
  </div>
 </main>
</div>
<?php endif; ?>
<script src="<?= h(asset_url('js/app.js')) ?>" defer></script>
<?php if (($_GET['r']??'')==='mapa'): ?><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous" defer></script><script src="<?= h(asset_url('js/maps.js')) ?>" defer></script><?php endif; ?>
</body></html>
