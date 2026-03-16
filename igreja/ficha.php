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
$logoIgreja = 'WhatsApp Image 2026-02-17 at 10.34.10.jpeg';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ficha de Integração à Membresia</title>
<style>
    * { box-sizing: border-box; }

    @page {
        size: A4 portrait;
        margin: 8mm;
    }

    body {
        margin: 0;
        background: #ececec;
        font-family: Arial, Helvetica, sans-serif;
        color: #222;
    }

    .page-wrap {
        width: 210mm;
        min-height: 297mm;
        margin: 0 auto;
        padding: 8px;
    }

    .no-print {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        gap: 10px;
    }

    .btn {
        display: inline-block;
        border: 1px solid #333;
        background: #fff;
        color: #111;
        padding: 8px 12px;
        text-decoration: none;
        font-size: 13px;
        border-radius: 6px;
        cursor: pointer;
    }

    .btn-dark {
        background: #111;
        color: #fff;
    }

    .sheet {
        width: 194mm;
        min-height: 279mm;
        background: #fff;
        border: 1px solid #b7b7b7;
        padding: 8px 10px 10px;
        margin: 0 auto;
        overflow: hidden;
    }

    .top-header {
        position: relative;
        text-align: center;
        padding: 2px 0 7px;
        border-bottom: 1px solid #8f8f8f;
        margin-bottom: 8px;
        min-height: 68px;
    }

    .top-logo {
        position: absolute;
        left: 0;
        top: 0;
        width: 74px;
        height: 66px;
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
        font-size: 23px;
        font-weight: 700;
        color: #5d5d5d;
        text-transform: uppercase;
        line-height: 1.05;
        letter-spacing: .2px;
    }

    .church-sub,
    .church-cnpj {
        color: #5f5f5f;
        font-weight: 700;
        line-height: 1.1;
    }

    .church-sub {
        margin-top: 5px;
        font-size: 12px;
    }

    .church-cnpj {
        margin-top: 3px;
        font-size: 13px;
    }

    .main-grid {
        display: grid;
        grid-template-columns: 108px 1fr;
        gap: 10px;
        align-items: start;
    }

    .photo-box {
        border: 1px solid #8f8f8f;
        height: 160px;
        text-align: center;
        padding: 8px 6px;
    }

    .photo-box .placeholder {
        margin-top: 32px;
        font-size: 15px;
        color: #666;
        font-weight: 700;
        line-height: 1.6;
    }

    .photo-box img {
        width: 92px;
        height: 122px;
        object-fit: cover;
        margin-top: 6px;
        border: 1px solid #999;
        background: #fafafa;
    }

    .main-content {
        padding-top: 1px;
    }

    .form-title {
        margin: 2px 0 6px;
        text-align: center;
        font-family: Georgia, "Times New Roman", serif;
        font-size: 28px;
        font-weight: 800;
        color: #5c5c5c;
        text-transform: uppercase;
        letter-spacing: .2px;
    }

    .choice-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin: 2px 0 6px;
        padding: 0 4px;
    }

    .choice-item {
        font-size: 14px;
        font-weight: 700;
        color: #5a5a5a;
        white-space: nowrap;
        text-align: center;
    }

    .section-title {
        text-align: center;
        font-family: Georgia, "Times New Roman", serif;
        font-size: 20px;
        font-weight: 800;
        color: #5b5b5b;
        text-transform: uppercase;
        text-decoration: underline;
        margin: 6px 0 6px;
        line-height: 1;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 5px;
    }

    .field {
        border: 1px solid #8f8f8f;
        min-height: 28px;
        padding: 1px 5px 3px;
        background: #fff;
    }

    .field-label {
        display: block;
        font-size: 8.5px;
        color: #5d5d5d;
        font-weight: 700;
        margin-bottom: 2px;
        line-height: 1;
    }

    .field-value {
        min-height: 12px;
        font-size: 11px;
        line-height: 1.12;
        color: #222;
        word-break: break-word;
    }

    .field-inline {
        font-size: 10px;
        line-height: 1.1;
    }

    .field-note {
        font-size: 8px;
        color: #5d5d5d;
        line-height: 1;
        margin-top: 1px;
    }

    .declaracao-text {
        text-align: center;
        max-width: 760px;
        margin: 4px auto 10px;
        font-size: 11.5px;
        line-height: 1.28;
        font-weight: 700;
        color: #4d4d4d;
    }

    .data-local {
        text-align: right;
        margin: 2px 18px 10px 0;
        font-size: 12px;
        font-weight: 700;
        color: #4d4d4d;
    }

    .footer-grid {
        display: grid;
        grid-template-columns: 1fr 235px;
        gap: 18px;
        align-items: end;
        margin-top: 2px;
    }

    .signature-name {
        min-height: 18px;
        text-align: center;
        margin-bottom: 2px;
        font-family: "Brush Script MT", cursive;
        font-size: 15px;
        color: #4257a8;
    }

    .signature-line {
        border-top: 1px solid #444;
        text-align: center;
        padding-top: 4px;
        font-size: 11px;
        font-weight: 700;
        color: #444;
        margin-top: 18px;
    }

    .secretaria-box {
        height: 108px;
        border: 1px solid #6f92be;
        background: linear-gradient(180deg, #8eb5df 0%, #7fa7d4 100%);
        color: #fff;
        text-align: center;
        padding: 12px 10px;
        font-weight: 700;
    }

    .secretaria-top {
        margin-top: 0;
        margin-bottom: 40px;
        font-size: 11px;
    }

    .secretaria-bottom {
        font-size: 11px;
    }

    .mt-8 { margin-top: 5px; }
    .mt-12 { margin-top: 7px; }

    @media print {
        body {
            background: #fff;
        }

        .page-wrap {
            width: auto;
            min-height: auto;
            margin: 0;
            padding: 0;
        }

        .no-print {
            display: none !important;
        }

        .sheet {
            width: 100%;
            min-height: auto;
            border: none;
            padding: 0;
            margin: 0;
        }
    }

    @media screen and (max-width: 900px) {
        .page-wrap {
            width: 100%;
        }

        .sheet {
            width: 100%;
            min-height: auto;
        }

        .church-title {
            font-size: 20px;
            margin-left: 76px;
        }

        .form-title {
            font-size: 24px;
        }

        .choice-row {
            grid-template-columns: 1fr;
            gap: 4px;
        }

        .footer-grid {
            grid-template-columns: 1fr;
        }
    }

    @media screen and (max-width: 700px) {
        .main-grid {
            grid-template-columns: 1fr;
        }

        .top-logo {
            position: static;
            margin: 0 auto 6px;
        }

        .church-title {
            margin-left: 0;
            font-size: 18px;
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
                        <span class="field-label">Pai:</span>
                        <div class="field-value"><?= h($m['pai']) ?></div>
                    </div>

                    <div class="field" style="grid-column: span 6;">
                        <span class="field-label">Mãe:</span>
                        <div class="field-value"><?= h($m['mae']) ?></div>
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
            projetos materiais, espirituais e financeiros. Solicito, respeitosamente meu ingresso a membresia desta Igreja.
        </div>

        <div class="data-local">
            Coari – Am ____ de __________________ de ______
        </div>

        <div class="footer-grid">
            <div>
                <div class="signature-name"><?= h($m['nome_completo']) ?></div>
                <div class="signature-line">Assinatura do Solicitante</div>
                <div class="signature-line" style="margin-top: 24px;">Assinatura do Pastor Presidente</div>
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