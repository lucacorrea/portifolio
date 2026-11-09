<section class="card pc-table-card">
  <div class="pc-table-head">
    <div>
      <h5>Lista de pessoas cadastradas</h5><span><?= pc_h($result['total']) ?> registro(s) encontrado(s)</span>
    </div>
    <div class="pc-table-help"><i class="bi bi-cursor"></i> Clique em uma linha para abrir as ações</div>
  </div>
  <div class="table-responsive">
    <table class="table pc-table mb-0" id="pcPeopleTable">
      <thead>
        <tr>
          <th>Pessoa</th>
          <th>CPF / NIS</th>
          <th>Contato</th>
          <th>Bairro</th>
          <th>Vínculos</th>
          <th>Última ajuda</th>
          <th>Cadastro</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$result['rows']): ?>
          <tr>
            <td colspan="7" class="text-center py-5 text-muted">Nenhuma pessoa encontrada para os filtros aplicados.</td>
          </tr>
          <?php else: foreach ($result['rows'] as $row): ?>
            <?php
            $beneficios = isset($row['beneficios_sigas']) ? $row['beneficios_sigas'] : array();
            $fotoPath = isset($row['foto_path']) ? $row['foto_path'] : '';
            $fotoUrl = pc_photo_url($fotoPath);
            $temFoto = pc_has_photo($fotoPath);
            ?>
            <tr class="pc-row" tabindex="0" data-person-id="<?= (int)$row['id'] ?>" data-person-name="<?= pc_h($row['nome']) ?>" data-person-photo="<?= pc_h($fotoUrl) ?>" data-person-cpf="<?= pc_h(pc_only_digits($row['cpf'])) ?>">
              <td>
                <div class="pc-person">
                  <span class="pc-avatar <?= $temFoto ? 'pc-avatar--photo' : '' ?>">
                    <?php if ($temFoto): ?>
                      <img src="<?= pc_h($fotoUrl) ?>" alt="Foto de <?= pc_h($row['nome']) ?>" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';">
                      <span class="pc-avatar-fallback" style="display:none"><?= pc_h(pc_initial($row['nome'])) ?></span>
                    <?php else: ?>
                      <?= pc_h(pc_initial($row['nome'])) ?>
                    <?php endif; ?>
                  </span>
                  <div><strong><?= pc_h($row['nome']) ?></strong><small>#<?= (int)$row['id'] ?> · <?= pc_h($row['responsavel_cadastro']) ?></small></div>
                </div>
              </td>
              <td><strong><?= pc_h(pc_cpf($row['cpf'])) ?></strong><small class="d-block text-muted">NIS: <?= pc_h($row['nis'] ?: '—') ?></small></td>
              <td><?= pc_h(pc_phone($row['telefone'])) ?></td>
              <td><?= pc_h($row['bairro_nome']) ?></td>
              <td><?php require __DIR__ . '/vinculos.php'; ?></td>
              <td class="pc-last-help-cell">
                <?php if (!empty($row['ultima_ajuda_data'])): ?>
                  <div class="pc-last-help" title="Última ajuda atribuída">
                    <strong><?= pc_h($row['ultima_ajuda_nome'] ?: 'Ajuda não identificada') ?></strong>
                    <small><i class="bi bi-calendar3"></i> <?= pc_h(pc_date($row['ultima_ajuda_data'])) ?></small>
                  </div>
                <?php else: ?>
                  <span class="pc-last-help-empty">Nenhuma</span>
                <?php endif; ?>
              </td>
              <td><span class="pc-date"><?= pc_h(pc_date($row['created_at'], true)) ?></span></td>
            </tr>
        <?php endforeach;
        endif; ?>
      </tbody>
    </table>
  </div>
</section>