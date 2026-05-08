<?php
$areaContrato = $areaContrato ?? 'recepcao';
$numeroContrato = trim((string) ($_GET['numero'] ?? ''));
$contrato = contrato_buscar_por_numero($numeroContrato);
$voltarUrl = route_url($areaContrato, 'clientes');
?>

<?php if (!$contrato): ?>
    <section class="card panel">
        <div class="panel-header">
            <div>
                <h2>Contrato não encontrado</h2>
                <p>O contrato informado não está disponível na listagem atual.</p>
            </div>
            <a href="<?= htmlspecialchars($voltarUrl) ?>" class="chip">Voltar para clientes</a>
        </div>
    </section>
<?php else: ?>
    <?php $cliente = $contrato['cliente']; ?>

    <section class="card panel contract-view-hero">
        <div class="panel-header">
            <div>
                <h2><?= htmlspecialchars($contrato['numero']) ?></h2>
                <p><?= htmlspecialchars($contrato['titulo']) ?></p>
            </div>
            <div class="table-actions">
                <span class="status <?= contrato_status_classe($contrato['status']) ?>">
                    <?= htmlspecialchars($contrato['status']) ?>
                </span>
                <a href="<?= htmlspecialchars($voltarUrl) ?>" class="chip">Voltar para clientes</a>
            </div>
        </div>

        <div class="info-grid contract-detail-grid">
            <div class="info-card">
                <strong>Cliente</strong>
                <p><?= htmlspecialchars($cliente['nome']) ?><br><?= htmlspecialchars($cliente['documento']) ?></p>
            </div>
            <div class="info-card">
                <strong>Vigência</strong>
                <p><?= htmlspecialchars($contrato['vigencia']) ?></p>
            </div>
            <div class="info-card">
                <strong>Valor contratado</strong>
                <p><?= contrato_valor_formatado((float) $contrato['valor']) ?></p>
            </div>
            <div class="info-card">
                <strong>Responsável</strong>
                <p><?= htmlspecialchars($contrato['responsavel']) ?></p>
            </div>
        </div>
    </section>

    <section class="card panel compact-card">
        <div class="panel-header">
            <div>
                <h2>Detalhes do contrato</h2>
                <p>Resumo operacional para consulta do atendimento, administrativo e gestão.</p>
            </div>
        </div>

        <div class="config-grid">
            <div class="setting-block">
                <h3>Objeto</h3>
                <p>Prestação de serviço vinculada ao cliente <?= htmlspecialchars($cliente['nome']) ?>: <?= htmlspecialchars($contrato['titulo']) ?>.</p>
            </div>
            <div class="setting-block">
                <h3>Condições financeiras</h3>
                <p>Valor total de <?= contrato_valor_formatado((float) $contrato['valor']) ?>, com acompanhamento financeiro pelo setor responsável.</p>
            </div>
            <div class="setting-block">
                <h3>Contato do cliente</h3>
                <p><?= htmlspecialchars($cliente['telefone']) ?> - <?= htmlspecialchars($cliente['email']) ?></p>
            </div>
        </div>
    </section>

    <section class="card panel compact-card">
        <div class="panel-header">
            <div>
                <h2>Acompanhamento</h2>
                <p>Etapas principais para controle do contrato.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="responsive-data-table">
                <thead>
                    <tr>
                        <th>Etapa</th>
                        <th>Setor</th>
                        <th>Status</th>
                        <th>Observação</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td data-label="Etapa"><strong>Cadastro do contrato</strong></td>
                        <td data-label="Setor">Recepção</td>
                        <td data-label="Status"><span class="status ok">Concluído</span></td>
                        <td data-label="Observação">Dados básicos conferidos com o cadastro do cliente.</td>
                    </tr>
                    <tr>
                        <td data-label="Etapa"><strong>Análise administrativa</strong></td>
                        <td data-label="Setor">Administrativo</td>
                        <td data-label="Status"><span class="status <?= contrato_status_classe($contrato['status']) ?>"><?= htmlspecialchars($contrato['status']) ?></span></td>
                        <td data-label="Observação">Validação de vigência, valor e documentação vinculada.</td>
                    </tr>
                    <tr>
                        <td data-label="Etapa"><strong>Acompanhamento gerencial</strong></td>
                        <td data-label="Setor">Dono</td>
                        <td data-label="Status"><span class="status progress">Monitorado</span></td>
                        <td data-label="Observação">Contrato disponível para consulta em todos os níveis de usuário.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
