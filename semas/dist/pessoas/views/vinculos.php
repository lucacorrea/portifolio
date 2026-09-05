<?php
$sigasDisponivel = !empty($result['sigas_disponivel']);
$meta = isset($beneficios['_meta']) && is_array($beneficios['_meta']) ? $beneficios['_meta'] : array();
$quantidade = isset($meta['quantidade']) ? (int)$meta['quantidade'] : 0;

if (!$sigasDisponivel):
?>
  <span class="pc-sigas-mark pc-sigas-mark--offline" title="A integração com o SIGAS não está disponível">
    <i class="bi bi-cloud-slash"></i>
    <span>SIGAS indisponível</span>
  </span>
<?php
elseif ($quantidade <= 0):
?>
  <span class="pc-sigas-mark pc-sigas-mark--muted">
    <i class="bi bi-dash-circle"></i>
    <span>Sem vínculo</span>
  </span>
<?php
else:
    foreach ($beneficios as $key => $item):
        if ($key === '_meta' || empty($item['encontrado'])) {
            continue;
        }

        $category = strtolower(trim((string)(isset($item['categoria_status']) ? $item['categoria_status'] : '')));
        $tone = 'info';
        if ($category === 'ativo') $tone = 'success';
        elseif ($category === 'pendente') $tone = 'warning';
        elseif ($category === 'revisar' || $category === 'restrito') $tone = 'danger';
        elseif ($category === 'inativo') $tone = 'muted';

        $programa = trim((string)(isset($item['programa']) ? $item['programa'] : $key));
        $programa = str_ireplace(array('Coari Meu ', 'Coari '), '', $programa);
        $status = trim((string)(isset($item['status']) ? $item['status'] : 'Vínculo localizado'));
?>
  <span class="pc-sigas-mark pc-sigas-mark--<?= pc_h($tone) ?>">
    <i class="bi bi-circle-fill"></i>
    <span>
      <strong><?= pc_h($programa) ?></strong>
      <small><?= pc_h($status) ?></small>
    </span>
  </span>
<?php
    endforeach;
endif;
?>
