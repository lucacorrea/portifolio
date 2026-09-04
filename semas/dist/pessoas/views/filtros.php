<section class="card pc-filter-card mb-3">
  <div class="card-body">
    <form method="get" class="pc-filter-grid" id="pcFilters">
      <div class="pc-field pc-field--search">
        <label for="q">Pesquisa</label>
        <input class="form-control" id="q" name="q" value="<?= pc_h($filters['q']) ?>" placeholder="Nome, CPF, NIS, telefone ou responsável">
      </div>
      <div class="pc-field">
        <label for="bairro_id">Bairro</label>
        <select class="form-select" id="bairro_id" name="bairro_id">
          <option value="">Todos</option>
          <?php foreach ($bairros as $bairro): ?>
            <option value="<?= (int)$bairro['id'] ?>" <?= (string)$filters['bairro_id'] === (string)$bairro['id'] ? 'selected' : '' ?>><?= pc_h($bairro['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="pc-field">
        <label for="programa">Programa SIGAS</label>
        <select class="form-select" id="programa" name="programa" <?= !$result['sigas_disponivel'] ? 'disabled' : '' ?>>
          <option value="">Todos</option>
          <option value="primeiro_emprego" <?= $filters['programa'] === 'primeiro_emprego' ? 'selected' : '' ?>>Primeiro Emprego</option>
          <option value="comida_na_mesa" <?= $filters['programa'] === 'comida_na_mesa' ? 'selected' : '' ?>>Comida na Mesa</option>
          <option value="nenhum" <?= $filters['programa'] === 'nenhum' ? 'selected' : '' ?>>Nenhum vínculo</option>
        </select>
      </div>
      <div class="pc-field">
        <label for="beneficio_situacao">Situação do vínculo</label>
        <select class="form-select" id="beneficio_situacao" name="beneficio_situacao" <?= !$result['sigas_disponivel'] ? 'disabled' : '' ?>>
          <option value="">Todas</option>
          <option value="ativo" <?= $filters['beneficio_situacao'] === 'ativo' ? 'selected' : '' ?>>Ativo / regular</option>
          <option value="pendente" <?= $filters['beneficio_situacao'] === 'pendente' ? 'selected' : '' ?>>Pendente</option>
          <option value="revisar" <?= $filters['beneficio_situacao'] === 'revisar' ? 'selected' : '' ?>>Revisar</option>
          <option value="encerrado" <?= $filters['beneficio_situacao'] === 'encerrado' ? 'selected' : '' ?>>Encerrado / inativo</option>
        </select>
      </div>
      <div class="pc-field">
        <label for="beneficio_quantidade">Quantidade de vínculos</label>
        <select class="form-select" id="beneficio_quantidade" name="beneficio_quantidade" <?= !$result['sigas_disponivel'] ? 'disabled' : '' ?>>
          <option value="">Todas</option>
          <option value="0" <?= $filters['beneficio_quantidade'] === '0' ? 'selected' : '' ?>>Nenhum</option>
          <option value="1" <?= $filters['beneficio_quantidade'] === '1' ? 'selected' : '' ?>>1 programa</option>
          <option value="2" <?= $filters['beneficio_quantidade'] === '2' ? 'selected' : '' ?>>2 programas</option>
          <option value="3+" <?= $filters['beneficio_quantidade'] === '3+' ? 'selected' : '' ?>>3 ou mais</option>
        </select>
      </div>
      <div class="pc-filter-actions">
        <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Aplicar</button>
        <a class="btn btn-light" href="pessoasCadastradas.php"><i class="bi bi-x-lg"></i> Limpar</a>
      </div>
    </form>
    <?php if (!$result['sigas_disponivel']): ?>
      <div class="pc-integration-note"><i class="bi bi-info-circle"></i> Integração SIGAS indisponível no momento. A listagem local continua funcionando normalmente.</div>
    <?php endif; ?>
  </div>
</section>
