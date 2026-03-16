<?php include 'conexao.php'; ?>
<?php
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM membros WHERE id = ?");
$stmt->execute([$id]);
$m = $stmt->fetch();

if (!$m) {
    die('Membro não encontrado.');
}

function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function data_br($v): string
{
    if (empty($v)) return '';
    $t = strtotime((string)$v);
    return $t ? date('d/m/Y', $t) : '';
}

function marcado(string $valor, string $esperado): string
{
    return strtoupper(trim($valor)) === strtoupper(trim($esperado)) ? 'X' : ' ';
}

$sexo = strtoupper((string)($m['sexo'] ?? ''));
$ingresso = strtoupper((string)($m['tipo_ingresso'] ?? ''));
$escolaridade = strtoupper((string)($m['escolaridade'] ?? ''));

$logradouro = trim((string)($m['rua'] ?? ''));
$numero = trim((string)($m['numero'] ?? ''));
$bairro = trim((string)($m['bairro'] ?? ''));
$cidade = trim((string)($m['cidade'] ?? ''));
$estado = trim((string)($m['estado'] ?? ''));
$cep = trim((string)($m['cep'] ?? ''));
$telefone = trim((string)($m['telefone'] ?? ''));

$fotoSistema = !empty($m['foto']) ? 'uploads/' . $m['foto'] : '';
$logoIgreja = 'WhatsApp Image 2026-02-17 at 10.34.10.jpeg'; // ajuste se necessário
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ficha de Integração à Membresia</title>
<style>
    * { box-sizing: border-box; }

    body {
        margin: 0;
        background: #ececec;
        font-family: Arial, Helvetica, sans-serif;
        color: #222;
    }

    .page-wrap {
        max-width: 1020px;
        margin: 14px auto;
        padding: 0 8px;
    }

    .no-print {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        gap: 10px;
    }

    .btn {
        display: inline-block;
        border: 1px solid #333;
        background: #fff;
        color: #111;
        padding: 9px 14px;
        text-decoration: none;
        font-size: 14px;
        border-radius: 6px;
        cursor: pointer;
    }

    .btn-dark {
        background: #111;
        color: #fff;
    }

    .sheet {
        background: #fff;
        border: 1px solid #b7b7b7;
        padding: 12px 16px 18px;
    }

    .top-header {
        position: relative;
        text-align: center;
        padding-top: 4px;
        padding-bottom: 10px;
        border-bottom: 2px solid #8f8f8f;
        margin-bottom: 10px;
        min-height: 86px;
    }

    .top-logo {
        position: absolute;
        left: 0;
        top: 0;
        width: 93px;
        height: 82px;
        border: 1px solid #8f8f8f;
        overflow: hidden;
        background: #fff;
    }

    .top-logo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .church-title {
        margin: 0;
        font-family: "Times New Roman", Georgia, serif;
        font-size: 33px;
        font-weight: 700;
        color: #5d5d5d;
        letter-spacing: .4px;
        text-transform: uppercase;
        line-height: 1.05;
    }

    .church-sub {
        margin-top: 8px;
        font-size: 17px;
        color: #5f5f5f;
        font-weight: 600;
    }

    .church-cnpj {
        margin-top: 6px;
        font-size: 18px;
        color: #5f5f5f;
        font-weight: 700;
    }

    .main-grid {
        display: grid;
        grid-template-columns: 130px 1fr;
        gap: 14px;
        align-items: start;
    }

    .photo-box {
        border: 1px solid #8f8f8f;
        min-height: 195px;
        text-align: center;
        padding: 10px 8px;
    }

    .photo-box .placeholder {
        margin-top: 44px;
        font-size: 18px;
        color: #666;
        font-weight: 700;
        line-height: 1.8;
    }

    .photo-box img {
        width: 100%;
        max-width: 112px;
        height: 145px;
        object-fit: cover;
        margin-top: 8px;
        border: 1px solid #999;
        background: #fafafa;
    }

    .main-content {
        padding-top: 2px;
    }

    .form-title {
        margin: 4px 0 10px;
        text-align: center;
        font-family: Georgia, "Times New Roman", serif;
        font-size: 39px;
        font-weight: 800;
        color: #5c5c5c;
        text-transform: uppercase;
        letter-spacing: .3px;
    }

    .choice-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin: 4px 0 10px;
        padding: 0 6px;
    }

    .choice-item {
        font-size: 18px;
        font-weight: 700;
        color: #5a5a5a;
        white-space: nowrap;
        text-align: center;
    }

    .section-title {
        text-align: center;
        font-family: Georgia, "Times New Roman", serif;
        font-size: 28px;
        font-weight: 800;
        color: #5b5b5b;
        text-transform: uppercase;
        text-decoration: underline;
        margin: 10px 0 8px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 8px 8px;
    }

    .field {
        border: 1px solid #8f8f8f;
        min-height: 34px;
        padding: 2px 7px 5px;
        background: #fff;
    }

    .field.tall {
        min-height: 42px;
    }

    .field-label {
        display: block;
        font-size: 10px;
        color: #5d5d5d;
        font-weight: 700;
        margin-bottom: 3px;
        line-height: 1.05;
    }

    .field-value {
        min-height: 14px;
        font-size: 13px;
        line-height: 1.2;
        color: #222;
        word-break: break-word;
    }

    .field-inline {
        font-size: 12px;
        line-height: 1.2;
    }

    .field-note {
        font-size: 10px;
        color: #5d5d5d;
        line-height: 1.05;
        margin-top: 2px;
    }

    .declaracao-text {
        text-align: center;
        max-width: 800px;
        margin: 8px auto 20px;
        font-size: 14px;
        line-height: 1.42;
        font-weight: 700;
        color: #4d4d4d;
    }

    .data-local {
        text-align: right;
        margin: 4px 28px 22px 0;
        font-size: 16px;
        font-weight: 700;
        color: #4d4d4d;
    }

    .footer-grid {
        display: grid;
        grid-template-columns: 1fr 280px;
        gap: 28px;
        align-items: end;
    }

    .signature-name {
        min-height: 28px;
        text-align: center;
        margin-bottom: 4px;
        font-family: "Brush Script MT", cursive;
        font-size: 18px;
        color: #4257a8;
    }

    .signature-line {
        border-top: 1px solid #444;
        text-align: center;
        padding-top: 6px;
        font-size: 13px;
        font-weight: 700;
        color: #444;
        margin-top: 24px;
    }

    .secretaria-box {
        min-height: 138px;
        border: 1px solid #6f92be;
        background: linear-gradient(180deg, #8eb5df 0%, #7fa7d4 100%);
        color: #fff;
        text-align: center;
        padding: 16px 12px;
        font-weight: 700;
    }

    .secretaria-top {
        margin-top: 2px;
        margin-bottom: 56px;
        font-size: 13px;
    }

    .secretaria-bottom {
        font-size: 13px;
    }

    .mt-8 { margin-top: 8px; }
    .mt-12 { margin-top: 12px; }

    @media print {
        body {
            background: #fff;
        }

        .page-wrap {
            max-width: 100%;
            margin: 0;
            padding: 0;
        }

        .no-print {
            display: none !important;
        }

        .sheet {
            border: none;
            padding: 8px 10px 12px;
        }
    }

    @media (max-width: 900px) {
        .church-title {
            font-size: 24px;
            margin-left: 90px;
            margin-right: 0;
        }

        .form-title {
            font-size: 28px;
        }

        .choice-row {
            grid-template-columns: 1fr;
            gap: 6px;
        }

        .footer-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 700px) {
        .main-grid {
            grid-template-columns: 1fr;
        }

        .top-logo {
            position: static;
            margin: 0 auto 8px;
        }

        .church-title {
            margin: 0;
        }
    }
</style>
</head>
<body>
<div class="page-wrap">
    <div class="no-print">
        <a href="visualizar.php?id=<?= (int)$m['id'] ?>" class="btn">Voltar</a>
        <button onclick="window.print()" class="btn btn-dark">Imprimir</button>
    </div>

    <div class="sheet">
        <div class="top-header">
            <div class="top-logo">
                <img src="<?= h($logoIgreja) ?>" alt="Logo da igreja">
            </div>

            <h1 class="church-title">IGREJA DE DEUS NASCER DE NOVO</h1>
            <div class="church-sub">Avenida Joanico 195 Urucu CEP: 69460-000</div>
            <div class="church-cnpj">CNPJ: 26.938.216/0001-96</div>
        </div>

        <div class="main-grid">
            <div class="photo-box">
                <?php if ($fotoSistema): ?>
                    <img src="<?= h($fotoSistema) ?>" alt="Foto 3x4">
                <?php else: ?>
                    <div class="placeholder">Foto<br>3x4</div>
                <?php endif; ?>
            </div>

            <div class="main-content">
                <div class="form-title">INTEGRAÇÃO À MEMBRESIA</div>

                <div class="choice-row">
                    <div class="choice-item">( <?= marcado($ingresso, 'MUDANCA') ?> ) MUDANÇA</div>
                    <div class="choice-item">( <?= marcado($ingresso, 'ACLAMACAO') ?> ) ACLAMAÇÃO</div>
                    <div class="choice-item">( <?= marcado($ingresso, 'BATISMO') ?> ) BATISMO</div>
                </div>

                <div class="section-title">DADOS PESSOAIS</div>

                <div class="form-grid">
                    <div class="field" style="grid-column: span 8;">
                        <span class="field-label">Nome Completo</span>
                        <div class="field-value"><?= h($m['nome_completo']) ?></div>
                    </div>

                    <div class="field" style="grid-column: span 2;">
                        <span class="field-label">Sexo</span>
                        <div class="field-inline">
                            ( <?= $sexo === 'M' ? 'X' : ' ' ?> ) Masc.
                            ( <?= $sexo === 'F' ? 'X' : ' ' ?> ) Fem.
                        </div>
                    </div>

                    <div class="field" style="grid-column: span 2;">
                        <span class="field-label">Tipo Sanguíneo</span>
                        <div class="field-value"></div>
                    </div>

                    <div class="field" style="grid-column: span 2;">
                        <span class="field-label">Nascimento</span>
                        <div class="field-value"><?= data_br($m['data_nascimento'] ?? '') ?></div>
                    </div>

                    <div class="field" style="grid-column: span 3;">
                        <span class="field-label">Nacionalidade</span>
                        <div class="field-value"><?= h($m['nacionalidade']) ?></div>
                    </div>

                    <div class="field" style="grid-column: span 1;">
                        <span class="field-label">Cidade:</span>
                        <div class="field-value"></div>
                    </div>

                    <div class="field" style="grid-column: span 3;">
                        <span class="field-label">Naturalidade</span>
                        <div class="field-value"><?= h($m['naturalidade']) ?></div>
                    </div>

                    <div class="field" style="grid-column: span 3;">
                        <span class="field-label">Estado (UF)</span>
                        <div class="field-value"><?= h($m['estado_uf']) ?></div>
                    </div>

                    <div class="field" style="grid-column: span 6;">
                        <span class="field-label">Escolaridade</span>
                        <div class="field-inline">
                            ( <?= marcado($escolaridade, 'FUNDAMENTAL') ?> ) Fundamental
                            &nbsp;&nbsp;
                            ( <?= marcado($escolaridade, 'MEDIO') ?> ) Médio
                            &nbsp;&nbsp;
                            ( <?= marcado($escolaridade, 'SUPERIOR') ?> ) Superior
                        </div>
                    </div>

                    <div class="field" style="grid-column: span 6;">
                        <span class="field-label">Profissão</span>
                        <div class="field-value"><?= h($m['profissao']) ?></div>
                    </div>

                    <div class="field" style="grid-column: span 3;">
                        <span class="field-label">Identidade</span>
                        <div class="field-value"><?= h($m['identidade']) ?></div>
                    </div>

                    <div class="field" style="grid-column: span 2;">
                        <span class="field-label">CPF</span>
                        <div class="field-value"><?= h($m['cpf']) ?></div>
                    </div>

                    <div class="field" style="grid-column: span 2;">
                        <span class="field-label">Título</span>
                        <div class="field-value"></div>
                    </div>

                    <div class="field" style="grid-column: span 2;">
                        <span class="field-label">CTPS</span>
                        <div class="field-value"></div>
                    </div>

                    <div class="field" style="grid-column: span 3;">
                        <span class="field-label">CDI</span>
                        <div class="field-value"></div>
                    </div>

                    <div class="field" style="grid-column: span 6;">
                        <span class="field-label">Filiação</span>
                        <div class="field-value">
                            <strong style="font-size:11px;color:#5d5d5d;">Pai:</strong> <?= h($m['pai']) ?>
                        </div>
                    </div>

                    <div class="field" style="grid-column: span 6;">
                        <span class="field-label">&nbsp;</span>
                        <div class="field-value">
                            <strong style="font-size:11px;color:#5d5d5d;">Mãe:</strong> <?= h($m['mae']) ?>
                        </div>
                    </div>

                    <div class="field" style="grid-column: span 3;">
                        <span class="field-label">Estado Civil</span>
                        <div class="field-value"><?= h($m['estado_civil']) ?></div>
                        <div class="field-note">
                            ( ) solteiro (a) &nbsp; ( ) divorciado (a)<br>
                            ( ) casado (a) &nbsp; ( ) viúvo (a)
                        </div>
                    </div>

                    <div class="field" style="grid-column: span 7;">
                        <span class="field-label">Cônjuge</span>
                        <div class="field-value"><?= h($m['conjuge']) ?></div>
                    </div>

                    <div class="field" style="grid-column: span 2;">
                        <span class="field-label">Filhos</span>
                        <div class="field-value"><?= h($m['filhos']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-title mt-12">ENDEREÇO RESIDÊNCIAL</div>

        <div class="form-grid">
            <div class="field" style="grid-column: span 9;">
                <span class="field-label">Endereço (rua, número e bairro)</span>
                <div class="field-value">
                    Rua: <?= h($logradouro) ?>
                    <?php if ($numero !== ''): ?> &nbsp;&nbsp; N°:<?= h($numero) ?><?php endif; ?>
                    <?php if ($bairro !== ''): ?> &nbsp;&nbsp; Bairro: <?= h($bairro) ?><?php endif; ?>
                </div>
            </div>

            <div class="field" style="grid-column: span 3;">
                <span class="field-label">Telefone</span>
                <div class="field-value"><?= h($telefone) ?></div>
            </div>

            <div class="field" style="grid-column: span 3;">
                <span class="field-label">CEP</span>
                <div class="field-value"><?= h($cep) ?></div>
            </div>

            <div class="field" style="grid-column: span 9;">
                <span class="field-label">Cidade/UF</span>
                <div class="field-value"><?= h(trim($cidade . ' ' . $estado)) ?></div>
            </div>
        </div>

        <div class="section-title mt-12">DADOS ECLESIÁSTICOS</div>

        <div class="form-grid">
            <div class="field" style="grid-column: span 3;">
                <span class="field-label">Decisão</span>
                <div class="field-value"><?= data_br($m['data_decisao'] ?? '') ?></div>
            </div>

            <div class="field" style="grid-column: span 2;">
                <span class="field-label">Batismo/Águas</span>
                <div class="field-value"></div>
            </div>

            <div class="field" style="grid-column: span 2;">
                <span class="field-label">Batismo/ Esp. Santo</span>
                <div class="field-value"></div>
            </div>

            <div class="field" style="grid-column: span 5;">
                <span class="field-label">Procedência</span>
                <div class="field-value"><?= h($m['procedencia']) ?></div>
            </div>

            <div class="field" style="grid-column: span 7;">
                <span class="field-label">Nome da Congregação</span>
                <div class="field-value"><?= h($m['congregacao']) ?></div>
            </div>

            <div class="field" style="grid-column: span 2;">
                <span class="field-label">Área</span>
                <div class="field-value"><?= h($m['area']) ?></div>
            </div>

            <div class="field" style="grid-column: span 3;">
                <span class="field-label">Núcleo</span>
                <div class="field-value"><?= h($m['nucleo']) ?></div>
            </div>
        </div>

        <div class="section-title mt-12">DECLARAÇÃO</div>

        <div class="declaracao-text">
            Declaro que estou ciente dos princípios bíblicos e doutrinários, projetos gerais, trabalhos e atividades
            desta Igreja, estando dispostos a cumpri-los, procurando cuidar da mesma, bem como, colaborar com seus
            projetos materiais, espirituais e financeiros. Solicito, respeitosamente meu ingresso a membresia desta
            Igreja.
        </div>

        <div class="data-local">
            Coari – Am ____ de __________________ de ______
        </div>

        <div class="footer-grid">
            <div>
                <div class="signature-name"><?= h($m['nome_completo']) ?></div>
                <div class="signature-line">Assinatura do Solicitante</div>
                <div class="signature-line" style="margin-top: 38px;">Assinatura do Pastor Presidente</div>
            </div>

            <div class="secretaria-box">
                <div class="secretaria-top">RECEBIDO EM: ____ / ____ / ____</div>
                <div class="secretaria-bottom">SECRETARIA GERAL</div>
            </div>
        </div>
    </div>
</div>
</body>
</html>