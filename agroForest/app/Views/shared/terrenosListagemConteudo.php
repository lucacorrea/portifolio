<?php
$areaTerrenos = $areaTerrenos ?? 'administrativo';
$terrenos = terrenos_lista();
$indicadoresTerrenos = terrenos_indicadores($terrenos);
?>

<section class="stats-grid">
    <article class="card stat-card">
        <div class="stat-top"><div class="stat-icon soft-primary">🗺️</div><span class="trend up">base</span></div>
        <h3><?= htmlspecialchars((string) $indicadoresTerrenos['total']) ?></h3>
        <p>Terrenos cadastrados</p>
    </article>
    <article class="card stat-card">
        <div class="stat-top"><div class="stat-icon soft-success">📍</div><span class="trend up">UTM</span></div>
        <h3><?= htmlspecialchars((string) $indicadoresTerrenos['georreferenciados']) ?></h3>
        <p>Georreferenciados</p>
    </article>
    <article class="card stat-card">
        <div class="stat-top"><div class="stat-icon soft-accent">⏳</div><span class="trend warn">conferência</span></div>
        <h3><?= htmlspecialchars((string) $indicadoresTerrenos['pendentes']) ?></h3>
        <p>Em atenção</p>
    </article>
    <article class="card stat-card">
        <div class="stat-top"><div class="stat-icon soft-info">📐</div><span class="trend up">área</span></div>
        <h3><?= terreno_area_formatada((float) $indicadoresTerrenos['area_total']) ?></h3>
        <p>Área total fictícia</p>
    </article>
</section>

<section class="card panel">
    <div class="panel-header">
        <div>
            <h2>Terrenos cadastrados</h2>
            <p>Dados fictícios no padrão do webGis: cliente, imóvel, status, área e pontos UTM.</p>
        </div>
        <a href="<?= htmlspecialchars(terreno_url($areaTerrenos, 'terrenoCadastrar')) ?>" class="btn-primary">Cadastrar coordenadas UTM</a>
    </div>

    <div class="table-responsive">
        <table class="terrain-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Cliente</th>
                    <th>Imóvel</th>
                    <th>Localização</th>
                    <th>Área</th>
                    <th>Zona UTM</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($terrenos as $terreno): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($terreno['codigo']) ?></strong></td>
                        <td>
                            <div class="client-name"><?= htmlspecialchars($terreno['cliente']) ?></div>
                            <div class="client-sub"><?= htmlspecialchars($terreno['documento']) ?></div>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($terreno['nome_imovel']) ?></strong><br>
                            <span class="client-sub"><?= htmlspecialchars($terreno['uso']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($terreno['bairro']) ?>, <?= htmlspecialchars($terreno['municipio']) ?> - <?= htmlspecialchars($terreno['uf']) ?></td>
                        <td><?= terreno_area_formatada((float) $terreno['area_hectares']) ?></td>
                        <td><?= htmlspecialchars($terreno['zona_utm']) ?><br><span class="client-sub"><?= htmlspecialchars($terreno['datum']) ?></span></td>
                        <td><span class="status <?= terreno_status_classe($terreno['status']) ?>"><?= htmlspecialchars($terreno['status']) ?></span></td>
                        <td>
                            <div class="table-actions">
                                <a href="<?= htmlspecialchars(terreno_url($areaTerrenos, 'terrenoVisualizar', $terreno['codigo'])) ?>" class="btn-outline">Ver terreno</a>
                                <a href="<?= htmlspecialchars(terreno_url($areaTerrenos, 'terrenoCadastrar')) ?>" class="btn-primary">Editar UTM</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
