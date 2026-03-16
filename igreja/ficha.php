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
        margin: 7mm;
    }

    body {
        margin: 0;
        background: #e9e9e9;
        font-family: Arial, Helvetica, sans-serif;
        color: #222;
    }

    .page-wrap {
        width: 210mm;
        margin: 0 auto;
        padding: 8px 0;
    }

    .no-print {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 0 0 8px;
        gap: 10px;
    }

    .btn {
        display: inline-block;
        padding: 8px 14px;
        border: 1px solid #333;
        background: #fff;
        color: #111;
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
        width: 196mm;
        min-height: 282mm;
        background: #fff;
        border: 1px solid #b8b8b8;
        margin: 0 auto;
        padding: 8px 10px 10px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .top-header {
        position: relative;
        text-align: center;
        padding-bottom: 8px;
        border-bottom: 1px solid #888;
        margin-bottom: 8px;
        min-height: 72px;
    }

    .top-logo {
        position: absolute;
        left: 0;
        top: 0;
        width: 78px;
        height: 68px;
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
        font-size: 24px;
        font-weight: 700;
        color: #5e5e5e;
        text-transform: uppercase;
        line-height: 1.05;
        letter-spacing: .2px;
    }

    .church-sub {
        margin-top: 5px;
        font-size: 12px;
        color: #5f5f5f;
        font-weight: 700;
    }

    .church-cnpj {
        margin-top: 4px;
        font-size: 13px;
        color: #5f5f5f;
        font-weight: 700;
    }

    .intro-grid {
        display: grid;
        grid-template-columns: 110px 1fr;
        gap: 10px;
        align-items: start;
        margin-bottom: 6px;
    }

    .photo-box {
        border: 1px solid #8f8f8f;
        height: 165px;
        text-align: center;
        padding: 8px 6px;
    }

    .photo-box .placeholder {
        margin-top: 34px;
        font-size: 15px;
        line-height: 1.6;
        color: #666;
        font-weight: 700;
    }

    .photo-box img {
        width: 94px;
        height: 126px;
        object-fit: cover;
        border: 1px solid #9a9a9a;
        margin-top: 4px;
        background: #fafafa;
    }

    .form-title {
        margin: 2px 0 8px;
        text-align: center;
        font-family: Georgia, "Times New Roman", serif;
        font-size: 30px;
        font-weight: 800;
        color: #5c5c5c;
        text-transform: uppercase;
        line-height: 1;
    }

    .choice-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        margin-bottom: 8px;
    }

    .choice-item {
        font-size: 15px;
        font-weight: 700;
        color: #5a5a5a;
        text-align: center;
        white-space: nowrap;
    }

    .section-title {
        text-align: center;
        font-family: Georgia, "Times New Roman", serif;
        font-size: 22px;
        font-weight: 800;
        color: #5b5b5b;
        text-transform: uppercase;
        text-decoration: underline;
        margin: 6px 0 7px;
        line-height: 1;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 5px;
    }

    .field {
        border: 1px solid #8f8f8f;
        min-height: 31px;
        padding: 2px 6px 4px;
        background: #fff;
    }

    .field.medium {
        min-height: 36px;
    }

    .field.large {
        min-height: 52px;
    }

    .field-label {
        display: block;
        font-size: 8.8px;
        color: #5d5d5d;
        font-weight: 700;
        margin-bottom: 2px;
        line-height: 1.05;
    }

    .field-value {
        min-height: 12px;
        font-size: 11px;
        line-height: 1.14;
        color: #222;
        word-break: break-word;
    }

    .field-inline {
        font-size: 10px;
        line-height: 1.15;
    }

    .field-note {
        font-size: 7.7px;
        color: #5d5d5d;
        line-height: 1.05;
        margin-top: 1px;
    }

    .declaracao-text {
        text-align: center;
        max-width: 800px;
        margin: 6px auto 10px;
        font-size: 12px;
        line-height: 1.32;
        font-weight: 700;
        color: #4d4d4d;
    }

    .data-local {
        text-align: right;
        margin: 2px 18px 10px 0;
        font-size: 13px;
        font-weight: 700;
        color: #4d4d4d;
    }

    .footer-grid {
        display: grid;
        grid-template-columns: 1fr 255px;
        gap: 20px;
        align-items: end;
        margin-top: 6px;
    }

    .signature-name {
        min-height: 22px;
        text-align: center;
        margin-bottom: 4px;
        font-family: "Brush Script MT", cursive;
        font-size: 16px;
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
        height: 112px;
        border: 1px solid #6f92be;
        background: linear-gradient(180deg, #8fb5df 0%, #80a7d4 100%);
        color: #fff;
        text-align: center;
        padding: 14px 10px;
        font-weight: 700;
    }

    .secretaria-top {
        margin-bottom: 42px;
        font-size: 11px;
    }

    .secretaria-bottom {
        font-size: 11px;
    }

    @media print {
        body {
            background: #fff;
        }

        .page-wrap {
            width: auto;
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
            padding: 8px;
        }

        .sheet {
            width: 100%;
            min-height: auto;
        }

        .church-title {
            font-size: 20px;
            margin-left: 82px;
        }

        .form-title {
            font-size: 24px;
        }

        .choice-row {
            grid-template-columns: 1fr;
        }

        .footer-grid {
            grid-template-columns: 1fr;
        }
    }

    @media screen and (max-width: 700px) {
        .intro-grid {
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
        <div>
            <div class="top-header">
                <div class="top-logo">
                    <img src="<?= h($logoIgreja) ?>" alt="Logo da igreja">
                </div>

                <h1 class="church-title">IGREJA DE DEUS NASCER DE NOVO</h1>
                <div class="church-sub">Avenida Joanico 195 Urucu CEP: 69460-000</div>
                <div class="church-cnpj">CNPJ: 26.938.216/0001-96</div>
            </div>

            <div class="intro-grid">
                <div class="photo-box">
                    <?php if ($fotoSistema): ?>
                        <img src="<?= h($fotoSistema) ?>" alt="Foto 3x4">
                    <?php else: ?>
                        <div class="placeholder">Foto<br>3x4</div>
                    <?php endif; ?>
                </div>

                <div>
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
                            <span class="field-label">Cidade</span>
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

                        <div class="field medium" style="grid-column: span 6;">
                            <span class="field-label">Escolaridade</span>
                            <div class="field-inline">
                                ( <?= marcado($escolaridade, 'FUNDAMENTAL') ?> ) Fundamental
                                &nbsp;&nbsp;
                                ( <?= marcado($escolaridade, 'MEDIO') ?> ) Médio
                                &nbsp;&nbsp;
                                ( <?= marcado($escolaridade, 'SUPERIOR') ?> ) Superior
                            </div>
                        </div>

                        <div class="field medium" style="grid-column: span 6;">
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
                            <span class="field-label">Pai</span>
                            <div class="field-value"><?= h($m['pai']) ?></div>
                        </div>

                        <div class="field" style="grid-column: span 6;">
                            <span class="field-label">Mãe</span>
                            <div class="field-value"><?= h($m['mae']) ?></div>
                        </div>

                        <div class="field medium" style="grid-column: span 3;">
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

            <div class="section-title">ENDEREÇO RESIDÊNCIAL</div>

            <div class="form-grid">
                <div class="field medium" style="grid-column: span 8;">
                    <span class="field-label">Endereço (rua, número e bairro)</span>
                    <div class="field-value">
                        Rua: <?= h($m['rua']) ?>
                        <?php if (!empty($m['numero'])): ?> &nbsp;&nbsp; N°:<?= h($m['numero']) ?><?php endif; ?>
                        <?php if (!empty($m['bairro'])): ?> &nbsp;&nbsp; Bairro: <?= h($m['bairro']) ?><?php endif; ?>
                    </div>
                </div>

                <div class="field" style="grid-column: span 4;">
                    <span class="field-label">Telefone</span>
                    <div class="field-value"><?= h($m['telefone']) ?></div>
                </div>

                <div class="field" style="grid-column: span 3;">
                    <span class="field-label">CEP</span>
                    <div class="field-value"><?= h($m['cep']) ?></div>
                </div>

                <div class="field" style="grid-column: span 9;">
                    <span class="field-label">Cidade/UF</span>
                    <div class="field-value"><?= h(trim(($m['cidade'] ?? '') . ' ' . ($m['estado'] ?? ''))) ?></div>
                </div>
            </div>

            <div class="section-title">DADOS ECLESIÁSTICOS</div>

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
                    <span class="field-label">Batismo/Esp. Santo</span>
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

            <div class="section-title">DECLARAÇÃO</div>

            <div class="declaracao-text">
                Declaro que estou ciente dos princípios bíblicos e doutrinários, projetos gerais, trabalhos e atividades desta Igreja,
                estando dispostos a cumpri-los, procurando cuidar da mesma, bem como, colaborar com seus projetos materiais,
                espirituais e financeiros. Solicito, respeitosamente meu ingresso a membresia desta Igreja.
            </div>

            <div class="data-local">
                Coari – Am ____ de __________________ de ______
            </div>
        </div>

        <div class="footer-grid">
            <div>
                <div class="signature-name"><?= h($m['nome_completo']) ?></div>
                <div class="signature-line">Assinatura do Solicitante</div>
                <div class="signature-line" style="margin-top: 20px;">Assinatura do Pastor Presidente</div>
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