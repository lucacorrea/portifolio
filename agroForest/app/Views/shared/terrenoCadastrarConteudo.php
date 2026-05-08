<?php
$areaTerrenos = $areaTerrenos ?? 'administrativo';
$clientesMock = clientes_contratos_lista();
?>

<section class="card panel">
    <div class="panel-header">
        <div>
            <h2>Cadastro do terreno</h2>
            <p>Fluxo inspirado no webGis: dados do imóvel, confrontantes e pontos UTM do polígono.</p>
        </div>
        <a href="<?= htmlspecialchars(terreno_url($areaTerrenos, 'terrenos')) ?>" class="chip">Voltar para terrenos</a>
    </div>

    <form class="form-grid terrain-form" method="POST" action="">
        <div class="form-group form-col-2">
            <h3>Dados do cliente e imóvel</h3>
        </div>
        <div class="form-group">
            <label for="cliente">Cliente</label>
            <select id="cliente" name="cliente">
                <?php foreach ($clientesMock as $cliente): ?>
                    <option><?= htmlspecialchars($cliente['nome']) ?> - <?= htmlspecialchars($cliente['documento']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="nome_imovel">Nome do terreno/imóvel</label>
            <input type="text" id="nome_imovel" name="nome_imovel" value="Sítio Castanheira">
        </div>
        <div class="form-group">
            <label for="tipo">Tipo</label>
            <select id="tipo" name="tipo">
                <option>Privado</option>
                <option>Público</option>
            </select>
        </div>
        <div class="form-group">
            <label for="uso">Uso do terreno</label>
            <input type="text" id="uso" name="uso" value="Manejo agroflorestal">
        </div>
        <div class="form-group">
            <label for="matricula">Matrícula</label>
            <input type="text" id="matricula" name="matricula" value="MAT-45872">
        </div>
        <div class="form-group">
            <label for="car">CAR</label>
            <input type="text" id="car" name="car" value="AM-1302603-9A7B.C3D4.E5F6">
        </div>
        <div class="form-group form-col-2">
            <label for="endereco">Localização</label>
            <input type="text" id="endereco" name="endereco" value="Ramal Castanheira, km 12 - Zona Rural, Manaus - AM">
        </div>

        <div class="form-group form-col-2">
            <h3>Dados UTM</h3>
        </div>
        <div class="form-group">
            <label for="zona_utm">Zona UTM</label>
            <input type="text" id="zona_utm" name="zona_utm" value="20M">
        </div>
        <div class="form-group">
            <label for="datum">Datum</label>
            <select id="datum" name="datum">
                <option>SIRGAS 2000</option>
                <option>WGS 84</option>
            </select>
        </div>
        <div class="form-group">
            <label for="area_hectares">Área estimada (ha)</label>
            <input type="number" step="0.01" id="area_hectares" name="area_hectares" value="18.42">
        </div>
        <div class="form-group">
            <label for="perimetro_metros">Perímetro estimado (m)</label>
            <input type="number" step="0.01" id="perimetro_metros" name="perimetro_metros" value="1850.70">
        </div>

        <div class="form-group form-col-2">
            <div class="terrain-section-title">
                <h3>Coordenadas do polígono</h3>
                <button type="button" class="btn-outline" data-add-utm-point>Adicionar ponto</button>
            </div>
            <div class="table-responsive">
                <table class="utm-table" data-utm-table>
                    <thead>
                        <tr>
                            <th>Ponto</th>
                            <th>Easting (E)</th>
                            <th>Northing (N)</th>
                            <th>Observação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><input name="ponto[]" value="P1"></td><td><input name="easting[]" value="827431.55"></td><td><input name="northing[]" value="9662184.10"></td><td><input name="obs[]" value="Marco inicial"></td></tr>
                        <tr><td><input name="ponto[]" value="P2"></td><td><input name="easting[]" value="827852.05"></td><td><input name="northing[]" value="9662178.42"></td><td><input name="obs[]" value="Limite norte"></td></tr>
                        <tr><td><input name="ponto[]" value="P3"></td><td><input name="easting[]" value="827864.77"></td><td><input name="northing[]" value="9661672.90"></td><td><input name="obs[]" value="Limite leste"></td></tr>
                        <tr><td><input name="ponto[]" value="P4"></td><td><input name="easting[]" value="827429.38"></td><td><input name="northing[]" value="9661680.36"></td><td><input name="obs[]" value="Fechamento"></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="form-group form-col-2">
            <h3>Confrontantes</h3>
        </div>
        <div class="form-group"><label for="frente">Frente</label><input id="frente" name="frente" value="Ramal Castanheira - 420,50 m"></div>
        <div class="form-group"><label for="fundo">Fundo</label><input id="fundo" name="fundo" value="Reserva Particular Boa Vista - 418,30 m"></div>
        <div class="form-group"><label for="direita">Lado direito</label><input id="direita" name="direita" value="José Almeida - 506,20 m"></div>
        <div class="form-group"><label for="esquerda">Lado esquerdo</label><input id="esquerda" name="esquerda" value="Igarapé São Pedro - 505,70 m"></div>

        <div class="form-group form-col-2">
            <label for="observacoes">Observações</label>
            <textarea id="observacoes" name="observacoes" rows="4">Área com vegetação preservada e uso agroflorestal em acompanhamento.</textarea>
        </div>
        <div class="form-actions form-col-2">
            <a href="<?= htmlspecialchars(terreno_url($areaTerrenos, 'terrenos')) ?>" class="btn-secondary">Cancelar</a>
            <button type="submit" class="btn-primary">Salvar cadastro fictício</button>
        </div>
    </form>
</section>

<script>
document.querySelector('[data-add-utm-point]')?.addEventListener('click', () => {
    const tbody = document.querySelector('[data-utm-table] tbody');
    const next = tbody.querySelectorAll('tr').length + 1;
    const row = document.createElement('tr');
    row.innerHTML = `<td><input name="ponto[]" value="P${next}"></td><td><input name="easting[]" placeholder="Easting"></td><td><input name="northing[]" placeholder="Northing"></td><td><input name="obs[]" placeholder="Observação"></td>`;
    tbody.appendChild(row);
});
</script>
