<?php include 'conexao.php'; ?>
<?php
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM membros WHERE id = ?");
$stmt->execute([$id]);
$m = $stmt->fetch();

if (!$m) {
    die('Membro não encontrado.');
}

$tipoIngresso = strtoupper((string)($m['tipo_ingresso'] ?? ''));
$badgeClass = 'badge-default';

if ($tipoIngresso === 'BATISMO') $badgeClass = 'badge-batismo';
if ($tipoIngresso === 'ACLAMACAO') $badgeClass = 'badge-aclamacao';
if ($tipoIngresso === 'MUDANCA') $badgeClass = 'badge-mudanca';

function exibir($valor, $fallback = '-')
{
    $texto = trim((string)$valor);
    return $texto !== '' ? htmlspecialchars($texto) : $fallback;
}
?>
<?php include 'includes/header.php'; ?>

<style>
    .view-page {
        margin-top: 8px;
    }

    .view-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 24px;
    }

    .view-title {
        font-size: 2rem;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.02em;
        margin: 0 0 6px;
    }

    .view-subtitle {
        color: #64748b;
        font-size: .95rem;
        margin: 0;
    }

    .profile-card,
    .info-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
        overflow: hidden;
    }

    .profile-card {
        position: sticky;
        top: 20px;
    }

    .profile-top {
        padding: 24px;
        text-align: center;
        border-bottom: 1px solid #eef2f7;
        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
    }

    .profile-photo {
        width: 210px;
        height: 250px;
        object-fit: cover;
        border-radius: 24px;
        border: 1px solid #dbe5f0;
        background: #f8fafc;
        box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
        margin-bottom: 16px;
    }

    .profile-name {
        font-size: 1.25rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 4px;
    }

    .profile-sub {
        color: #64748b;
        margin-bottom: 14px;
    }

    .ingresso-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 120px;
        padding: 8px 14px;
        border-radius: 999px;
        font-size: .78rem;
        font-weight: 800;
        border: 1px solid transparent;
        text-transform: uppercase;
        letter-spacing: .02em;
    }

    .badge-batismo {
        background: #ecfdf5;
        color: #047857;
        border-color: #a7f3d0;
    }

    .badge-aclamacao {
        background: #fff7ed;
        color: #c2410c;
        border-color: #fdba74;
    }

    .badge-mudanca {
        background: #eff6ff;
        color: #1d4ed8;
        border-color: #bfdbfe;
    }

    .badge-default {
        background: #f1f5f9;
        color: #475569;
        border-color: #cbd5e1;
    }

    .profile-stats {
        padding: 18px 20px 20px;
        display: grid;
        gap: 12px;
    }

    .profile-stat {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 12px 14px;
    }

    .profile-stat span {
        display: block;
        font-size: .78rem;
        color: #64748b;
        margin-bottom: 4px;
    }

    .profile-stat strong {
        display: block;
        color: #0f172a;
        font-size: .95rem;
        font-weight: 700;
    }

    .info-card + .info-card {
        margin-top: 18px;
    }

    .info-card-header {
        padding: 20px 22px 14px;
        border-bottom: 1px solid #eef2f7;
        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
    }

    .info-card-header h5 {
        margin: 0 0 4px;
        font-size: 1.08rem;
        font-weight: 800;
        color: #0f172a;
    }

    .info-card-header p {
        margin: 0;
        color: #64748b;
        font-size: .9rem;
    }

    .info-card-body {
        padding: 22px;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
    }

    .info-item {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 14px;
        min-height: 76px;
    }

    .info-item.full {
        grid-column: 1 / -1;
    }

    .info-item span {
        display: block;
        font-size: .78rem;
        color: #64748b;
        margin-bottom: 6px;
    }

    .info-item strong {
        display: block;
        color: #0f172a;
        font-size: .95rem;
        font-weight: 700;
        line-height: 1.4;
        word-break: break-word;
    }

    .view-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .view-actions .btn {
        border-radius: 14px;
        font-weight: 700;
        padding: 10px 16px;
    }

    @media (max-width: 1199.98px) {
        .profile-card {
            position: static;
        }
    }

    @media (max-width: 991.98px) {
        .info-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 767.98px) {
        .view-title {
            font-size: 1.55rem;
        }

        .profile-card,
        .info-card {
            border-radius: 18px;
        }

        .profile-top,
        .info-card-header,
        .info-card-body {
            padding-left: 16px;
            padding-right: 16px;
        }

        .profile-photo {
            width: 170px;
            height: 210px;
            border-radius: 18px;
        }

        .info-grid {
            grid-template-columns: 1fr;
        }

        .view-actions .btn {
            width: 100%;
        }
    }
</style>

<div class="view-page">
    <div class="view-header">
        <div>
            <h2 class="view-title">Visualizar membro</h2>
            <p class="view-subtitle">Informações completas do cadastro do membro.</p>
        </div>

        <div class="view-actions">
            <a href="editar.php?id=<?= $m['id'] ?>" class="btn btn-warning">
                <i class="fas fa-pen me-2"></i>Editar
            </a>
            <a href="ficha.php?id=<?= $m['id'] ?>" class="btn btn-outline-secondary">
                <i class="fas fa-file-alt me-2"></i>Ver ficha
            </a>
            <a href="listar.php" class="btn btn-outline-dark">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="profile-card">
                <div class="profile-top">
                    <?php if (!empty($m['foto'])): ?>
                        <img src="uploads/<?= htmlspecialchars($m['foto']) ?>" class="profile-photo" alt="foto">
                    <?php else: ?>
                        <img src="https://via.placeholder.com/210x250?text=Sem+Foto" class="profile-photo" alt="foto">
                    <?php endif; ?>

                    <div class="profile-name"><?= htmlspecialchars($m['nome_completo']) ?></div>
                    <div class="profile-sub"><?= exibir($m['congregacao']) ?></div>

                    <span class="ingresso-badge <?= $badgeClass ?>">
                        <?= exibir($tipoIngresso, 'NÃO INFORMADO') ?>
                    </span>
                </div>

                <div class="profile-stats">
                    <div class="profile-stat">
                        <span>Telefone</span>
                        <strong><?= exibir($m['telefone']) ?></strong>
                    </div>

                    <div class="profile-stat">
                        <span>Área</span>
                        <strong><?= exibir($m['area']) ?></strong>
                    </div>

                    <div class="profile-stat">
                        <span>Núcleo</span>
                        <strong><?= exibir($m['nucleo']) ?></strong>
                    </div>

                    <div class="profile-stat">
                        <span>Cadastro</span>
                        <strong>#<?= (int)$m['id'] ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="info-card">
                <div class="info-card-header">
                    <h5>Dados pessoais</h5>
                    <p>Informações pessoais e familiares do membro.</p>
                </div>

                <div class="info-card-body">
                    <div class="info-grid">
                        <div class="info-item"><span>Sexo</span><strong><?= exibir($m['sexo']) ?></strong></div>
                        <div class="info-item"><span>Nascimento</span><strong><?= !empty($m['data_nascimento']) ? date('d/m/Y', strtotime($m['data_nascimento'])) : '-' ?></strong></div>
                        <div class="info-item"><span>Nacionalidade</span><strong><?= exibir($m['nacionalidade']) ?></strong></div>

                        <div class="info-item"><span>Naturalidade</span><strong><?= exibir($m['naturalidade']) ?></strong></div>
                        <div class="info-item"><span>UF</span><strong><?= exibir($m['estado_uf']) ?></strong></div>
                        <div class="info-item"><span>Escolaridade</span><strong><?= exibir($m['escolaridade']) ?></strong></div>

                        <div class="info-item"><span>Profissão</span><strong><?= exibir($m['profissao']) ?></strong></div>
                        <div class="info-item"><span>CPF</span><strong><?= exibir($m['cpf']) ?></strong></div>
                        <div class="info-item"><span>Identidade</span><strong><?= exibir($m['identidade']) ?></strong></div>

                        <div class="info-item"><span>Pai</span><strong><?= exibir($m['pai']) ?></strong></div>
                        <div class="info-item"><span>Mãe</span><strong><?= exibir($m['mae']) ?></strong></div>
                        <div class="info-item"><span>Estado civil</span><strong><?= exibir($m['estado_civil']) ?></strong></div>

                        <div class="info-item"><span>Cônjuge</span><strong><?= exibir($m['conjuge']) ?></strong></div>
                        <div class="info-item"><span>Filhos</span><strong><?= exibir($m['filhos']) ?></strong></div>
                        <div class="info-item"><span>Telefone</span><strong><?= exibir($m['telefone']) ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <div class="info-card-header">
                    <h5>Endereço residencial</h5>
                    <p>Dados de localização e contato do membro.</p>
                </div>

                <div class="info-card-body">
                    <div class="info-grid">
                        <div class="info-item"><span>Rua</span><strong><?= exibir($m['rua']) ?></strong></div>
                        <div class="info-item"><span>Número</span><strong><?= exibir($m['numero']) ?></strong></div>
                        <div class="info-item"><span>Bairro</span><strong><?= exibir($m['bairro']) ?></strong></div>

                        <div class="info-item"><span>CEP</span><strong><?= exibir($m['cep']) ?></strong></div>
                        <div class="info-item"><span>Cidade</span><strong><?= exibir($m['cidade']) ?></strong></div>
                        <div class="info-item"><span>Estado</span><strong><?= exibir($m['estado']) ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <div class="info-card-header">
                    <h5>Dados eclesiásticos</h5>
                    <p>Informações de ingresso, origem e vínculo com a igreja.</p>
                </div>

                <div class="info-card-body">
                    <div class="info-grid">
                        <div class="info-item"><span>Tipo de ingresso</span><strong><?= exibir($m['tipo_ingresso']) ?></strong></div>
                        <div class="info-item"><span>Data da decisão</span><strong><?= !empty($m['data_decisao']) ? date('d/m/Y', strtotime($m['data_decisao'])) : '-' ?></strong></div>
                        <div class="info-item"><span>Procedência</span><strong><?= exibir($m['procedencia']) ?></strong></div>

                        <div class="info-item"><span>Congregação</span><strong><?= exibir($m['congregacao']) ?></strong></div>
                        <div class="info-item"><span>Área</span><strong><?= exibir($m['area']) ?></strong></div>
                        <div class="info-item"><span>Núcleo</span><strong><?= exibir($m['nucleo']) ?></strong></div>

                        <div class="info-item full">
                            <span>Observação</span>
                            <strong><?= !empty(trim((string)$m['observacao'])) ? nl2br(htmlspecialchars($m['observacao'])) : '-' ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>