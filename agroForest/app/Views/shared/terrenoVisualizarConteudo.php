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
    <?php
    $croquiPoints = terreno_croqui_points($terreno['coordenadas']);
    $firstCroquiPoint = sprintf(
        '%s,%s',
        (float) ($terreno['coordenadas'][0]['x'] ?? 50),
        (float) ($terreno['coordenadas'][0]['y'] ?? 50)
    );
    ?>

    <section class="terrain-report-actions no-print">
        <a href="<?= htmlspecialchars(terreno_url($areaTerrenos, 'terrenos')) ?>" class="chip">Voltar para terrenos</a>
        <button type="button" class="btn-primary" data-print-terrain>Baixar PDF / Imprimir</button>
    </section>

    <section class="terrain-report-sheet" id="terrain-report">
        <header class="terrain-report-header">
            <div class="terrain-report-brand">
                <div class="terrain-report-logo">AFA</div>
                <div>
                    <strong>Agro Forest Amazon</strong>
                    <span>Gestão ambiental e territorial</span>
                </div>
            </div>
            <h1>Detalhes do Imóvel</h1>
            <div class="terrain-report-brand terrain-report-brand-right">
                <div class="terrain-report-logo soft">UTM</div>
                <div>
                    <strong>Cadastro Territorial</strong>
                    <span><?= htmlspecialchars($terreno['datum']) ?> - Zona <?= htmlspecialchars($terreno['zona_utm']) ?></span>
                </div>
            </div>
        </header>

        <div class="terrain-report-divider"></div>

        <div class="terrain-report-details">
            <div><span>Cliente</span><strong><?= htmlspecialchars($terreno['cliente']) ?></strong></div>
            <div><span>Proprietário</span><strong><?= htmlspecialchars($terreno['proprietario']) ?></strong></div>
            <div><span>Status</span><strong class="terrain-report-status"><?= htmlspecialchars($terreno['status']) ?></strong></div>
            <div><span>Imóvel</span><strong><?= htmlspecialchars($terreno['nome_imovel']) ?></strong></div>
            <div><span>Uso</span><strong><?= htmlspecialchars($terreno['uso']) ?></strong></div>
            <div><span>Tipologia</span><strong><?= htmlspecialchars($terreno['tipologia']) ?></strong></div>
            <div><span>Localização</span><strong><?= htmlspecialchars($terreno['endereco']) ?></strong></div>
            <div><span>Bairro</span><strong><?= htmlspecialchars($terreno['bairro']) ?></strong></div>
            <div><span>Município</span><strong><?= htmlspecialchars($terreno['municipio']) ?> - <?= htmlspecialchars($terreno['uf']) ?></strong></div>
        </div>

        <div class="terrain-report-metrics">
            <div><span>Código</span><strong><?= htmlspecialchars($terreno['codigo']) ?></strong></div>
            <div><span>Área</span><strong><?= terreno_area_formatada((float) $terreno['area_hectares']) ?></strong></div>
            <div><span>Perímetro</span><strong><?= terreno_medida_formatada((float) $terreno['perimetro_metros']) ?></strong></div>
            <div><span>Matrícula</span><strong><?= htmlspecialchars($terreno['matricula']) ?></strong></div>
            <div><span>CAR</span><strong><?= htmlspecialchars($terreno['car']) ?></strong></div>
            <div><span>CCIR</span><strong><?= htmlspecialchars($terreno['ccir']) ?></strong></div>
        </div>

        <h2 class="terrain-report-title">Pontos (UTM e Coordenadas Geográficas)</h2>
        <div class="table-responsive terrain-report-table-wrap">
            <table class="terrain-report-table">
                <thead>
                    <tr>
                        <th>Ponto</th>
                        <th>Easting</th>
                        <th>Northing</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($terreno['coordenadas'] as $coordenada): ?>
                        <tr>
                            <td><?= htmlspecialchars($coordenada['ponto']) ?></td>
                            <td><?= htmlspecialchars($coordenada['easting']) ?></td>
                            <td><?= htmlspecialchars($coordenada['northing']) ?></td>
                            <td><?= htmlspecialchars($coordenada['latitude']) ?></td>
                            <td><?= htmlspecialchars($coordenada['longitude']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h2 class="terrain-report-title">Croqui</h2>
        <div class="terrain-report-croqui">
            <svg viewBox="0 0 100 100" role="img" aria-label="Croqui do terreno">
                <polygon points="<?= htmlspecialchars($croquiPoints) ?>" class="croqui-fill"></polygon>
                <polyline points="<?= htmlspecialchars($croquiPoints . ' ' . $firstCroquiPoint) ?>" class="croqui-line"></polyline>
                <?php foreach ($terreno['coordenadas'] as $coordenada): ?>
                    <circle cx="<?= htmlspecialchars((string) $coordenada['x']) ?>" cy="<?= htmlspecialchars((string) $coordenada['y']) ?>" r="1.25" class="croqui-dot"></circle>
                    <text x="<?= htmlspecialchars((string) ((float) $coordenada['x'] + 1.8)) ?>" y="<?= htmlspecialchars((string) ((float) $coordenada['y'] - 1.6)) ?>" class="croqui-label"><?= htmlspecialchars($coordenada['ponto']) ?></text>
                <?php endforeach; ?>
            </svg>
        </div>

        <h2 class="terrain-report-title">Confrontantes</h2>
        <div class="table-responsive terrain-report-table-wrap">
            <table class="terrain-report-table terrain-report-table-compact">
                <thead><tr><th>Lado</th><th>Confrontante</th><th>Distância</th></tr></thead>
                <tbody>
                    <?php foreach ($terreno['confrontantes'] as $confrontante): ?>
                        <tr>
                            <td><?= htmlspecialchars($confrontante['lado']) ?></td>
                            <td><?= htmlspecialchars($confrontante['nome']) ?></td>
                            <td><?= htmlspecialchars($confrontante['distancia']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="terrain-report-observation">
            <strong>Observações</strong>
            <p><?= htmlspecialchars($terreno['observacoes']) ?></p>
        </div>
    </section>

    <script>
    document.querySelector('[data-print-terrain]')?.addEventListener('click', () => window.print());
    </script>
<?php endif; ?>
