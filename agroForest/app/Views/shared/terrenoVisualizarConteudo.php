<?php
$areaTerrenos = $areaTerrenos ?? 'administrativo';
$codigoTerreno = trim((string) ($_GET['codigo'] ?? 'TER-2026-001'));
$terreno = terreno_buscar_por_codigo($codigoTerreno);
?>

<?php if (!$terreno): ?>
    <section class="card panel">
        <div class="panel-header">
            <div><h2>Terreno não encontrado</h2><p>O código informado não existe na base fictícia.</p></div>
            <a href="<?= htmlspecialchars(terreno_url($areaTerrenos, 'terrenos')) ?>" class="chip">Voltar</a>
        </div>
    </section>
<?php else: ?>
    <section class="card panel terrain-view-hero">
        <div class="panel-header">
            <div>
                <h2><?= htmlspecialchars($terreno['nome_imovel']) ?></h2>
                <p><?= htmlspecialchars($terreno['codigo']) ?> - <?= htmlspecialchars($terreno['cliente']) ?></p>
            </div>
            <div class="table-actions">
                <span class="status <?= terreno_status_classe($terreno['status']) ?>"><?= htmlspecialchars($terreno['status']) ?></span>
                <a href="<?= htmlspecialchars(terreno_url($areaTerrenos, 'terrenos')) ?>" class="chip">Voltar para terrenos</a>
            </div>
        </div>

        <div class="terrain-view-grid">
            <div class="terrain-map-preview" aria-label="Prévia fictícia do polígono do terreno">
                <div class="terrain-map-toolbar">
                    <span><?= htmlspecialchars($terreno['zona_utm']) ?></span>
                    <strong><?= htmlspecialchars($terreno['datum']) ?></strong>
                </div>
                <div class="terrain-polygon">
                    <span class="terrain-point p1">P1</span>
                    <span class="terrain-point p2">P2</span>
                    <span class="terrain-point p3">P3</span>
                    <span class="terrain-point p4">P4</span>
                </div>
            </div>

            <div class="config-grid">
                <div class="setting-block"><h3>Cliente</h3><p><?= htmlspecialchars($terreno['cliente']) ?><br><?= htmlspecialchars($terreno['documento']) ?></p></div>
                <div class="setting-block"><h3>Contato</h3><p><?= htmlspecialchars($terreno['telefone']) ?><br><?= htmlspecialchars($terreno['email']) ?></p></div>
                <div class="setting-block"><h3>Localização</h3><p><?= htmlspecialchars($terreno['endereco']) ?>, <?= htmlspecialchars($terreno['bairro']) ?>, <?= htmlspecialchars($terreno['municipio']) ?> - <?= htmlspecialchars($terreno['uf']) ?></p></div>
            </div>
        </div>
    </section>

    <section class="stats-grid stats-grid-mini compact-card">
        <article class="card stat-card"><h3><?= terreno_area_formatada((float) $terreno['area_hectares']) ?></h3><p>Área estimada</p></article>
        <article class="card stat-card"><h3><?= terreno_medida_formatada((float) $terreno['perimetro_metros']) ?></h3><p>Perímetro estimado</p></article>
        <article class="card stat-card"><h3><?= htmlspecialchars((string) count($terreno['coordenadas'])) ?></h3><p>Pontos UTM</p></article>
    </section>

    <section class="card panel compact-card">
        <div class="panel-header"><div><h2>Informações fundiárias</h2><p>Dados fictícios preparados para a futura integração com banco/GeoJSON.</p></div></div>
        <div class="info-grid">
            <div class="info-card"><strong>Proprietário</strong><p><?= htmlspecialchars($terreno['proprietario']) ?></p></div>
            <div class="info-card"><strong>Matrícula</strong><p><?= htmlspecialchars($terreno['matricula']) ?></p></div>
            <div class="info-card"><strong>CAR</strong><p><?= htmlspecialchars($terreno['car']) ?></p></div>
            <div class="info-card"><strong>CCIR</strong><p><?= htmlspecialchars($terreno['ccir']) ?></p></div>
            <div class="info-card"><strong>Uso</strong><p><?= htmlspecialchars($terreno['uso']) ?></p></div>
            <div class="info-card"><strong>Tipologia</strong><p><?= htmlspecialchars($terreno['tipologia']) ?></p></div>
        </div>
    </section>

    <section class="card panel compact-card">
        <div class="panel-header"><div><h2>Coordenadas UTM</h2><p>Pontos do polígono no formato Easting/Northing.</p></div></div>
        <div class="table-responsive">
            <table class="utm-table">
                <thead><tr><th>Ponto</th><th>Easting (E)</th><th>Northing (N)</th><th>Zona</th><th>Datum</th></tr></thead>
                <tbody>
                    <?php foreach ($terreno['coordenadas'] as $coordenada): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($coordenada['ponto']) ?></strong></td>
                            <td><?= htmlspecialchars($coordenada['easting']) ?></td>
                            <td><?= htmlspecialchars($coordenada['northing']) ?></td>
                            <td><?= htmlspecialchars($terreno['zona_utm']) ?></td>
                            <td><?= htmlspecialchars($terreno['datum']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card panel compact-card">
        <div class="panel-header"><div><h2>Confrontantes</h2><p>Limites do terreno informados no cadastro.</p></div></div>
        <div class="table-responsive">
            <table class="terrain-table terrain-table-sm">
                <thead><tr><th>Lado</th><th>Confrontante</th><th>Distância</th></tr></thead>
                <tbody>
                    <?php foreach ($terreno['confrontantes'] as $confrontante): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($confrontante['lado']) ?></strong></td>
                            <td><?= htmlspecialchars($confrontante['nome']) ?></td>
                            <td><?= htmlspecialchars($confrontante['distancia']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card panel compact-card">
        <div class="panel-header"><div><h2>Observações</h2><p>Resumo para consulta do cliente e acompanhamento interno.</p></div></div>
        <p class="terrain-observation"><?= htmlspecialchars($terreno['observacoes']) ?></p>
    </section>
<?php endif; ?>
