<?php
require_once 'database.php';
require_once 'functions.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    die('ID do membro não fornecido');
}

$stmt = $pdo->prepare("SELECT * FROM membros WHERE id = ?");
$stmt->execute([$id]);
$membro = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$membro) {
    die('Membro não encontrado');
}

$idade = calcularIdade($membro['data_nascimento']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha de Membro - <?php echo htmlspecialchars($membro['nome_completo']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 210mm;
            height: 297mm;
            background: white;
            margin: 0 auto 20px;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 3px solid #1a2e4a;
            padding-bottom: 15px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #1a2e4a 0%, #2d4a6f 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: bold;
        }

        .header-text h1 {
            font-size: 18px;
            color: #1a2e4a;
            margin-bottom: 3px;
        }

        .header-text p {
            font-size: 12px;
            color: #666;
        }

        .foto-section {
            text-align: center;
        }

        .foto-container {
            width: 100px;
            height: 130px;
            border: 3px solid #c9a84c;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f9f9f9;
            border-radius: 4px;
            overflow: hidden;
        }

        .foto-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .foto-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1a2e4a 0%, #2d4a6f 100%);
            color: white;
            font-size: 40px;
        }

        .foto-label {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
            font-weight: bold;
        }

        /* Título da Seção */
        .section-title {
            background: linear-gradient(135deg, #1a2e4a 0%, #2d4a6f 100%);
            color: white;
            padding: 10px 15px;
            margin-top: 20px;
            margin-bottom: 12px;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-left: 4px solid #c9a84c;
        }

        /* Linha de Separação */
        .divider {
            border-bottom: 2px solid #c9a84c;
            margin: 15px 0;
        }

        /* Grid de Campos */
        .field-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 12px;
        }

        .field-row.two-col {
            grid-template-columns: repeat(2, 1fr);
        }

        .field-row.full {
            grid-template-columns: 1fr;
        }

        .field {
            display: flex;
            flex-direction: column;
        }

        .field-label {
            font-size: 10px;
            font-weight: bold;
            color: #1a2e4a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }

        .field-value {
            font-size: 12px;
            color: #333;
            border-bottom: 1px solid #999;
            padding-bottom: 2px;
            min-height: 18px;
        }

        .field-value.empty {
            color: #ccc;
        }

        /* Declaração */
        .declaration {
            margin-top: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #c9a84c;
            font-size: 11px;
            line-height: 1.6;
            text-align: justify;
        }

        .declaration-title {
            font-weight: bold;
            color: #1a2e4a;
            margin-bottom: 8px;
            text-transform: uppercase;
            font-size: 12px;
        }

        /* Assinaturas */
        .signatures {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-top: 30px;
        }

        .signature-block {
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #333;
            margin-bottom: 5px;
            height: 50px;
        }

        .signature-label {
            font-size: 10px;
            font-weight: bold;
            color: #333;
        }

        /* Rodapé */
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 9px;
            color: #666;
            text-align: center;
        }

        .received-box {
            position: absolute;
            bottom: 30px;
            right: 30px;
            width: 150px;
            height: 80px;
            border: 2px solid #1a2e4a;
            background: linear-gradient(135deg, #1a2e4a 0%, #2d4a6f 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 11px;
            font-weight: bold;
        }

        .received-box-label {
            font-size: 9px;
            margin-top: 5px;
        }

        /* Impressão */
        @media print {
            body {
                background: white;
                padding: 0;
            }

            .container {
                max-width: 100%;
                height: auto;
                margin: 0;
                padding: 20px;
                box-shadow: none;
                page-break-after: always;
            }

            .no-print {
                display: none !important;
            }
        }

        /* Botões */
        .buttons {
            text-align: center;
            margin-bottom: 20px;
            gap: 10px;
            display: flex;
            justify-content: center;
        }

        .btn {
            padding: 10px 20px;
            background: #1a2e4a;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            background: #2d4a6f;
        }

        .btn-secondary {
            background: #c9a84c;
            color: #1a2e4a;
        }

        .btn-secondary:hover {
            background: #e0c9a0;
        }
    </style>
</head>
<body>
    <!-- Botões de Ação -->
    <div class="buttons no-print">
        <button class="btn" onclick="window.print()">
            <i class="fas fa-print"></i> Imprimir
        </button>
        <button class="btn btn-secondary" onclick="window.history.back()">
            <i class="fas fa-arrow-left"></i> Voltar
        </button>
    </div>

    <!-- Ficha de Membro -->
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo-section">
                <div class="logo">⛪</div>
                <div class="header-text">
                    <h1>IGREJA DE DEUS NASCER DE NOVO</h1>
                    <p>Avenida Joanico 195, Urucu - CEP: 69460-000</p>
                    <p>CNPJ: 26.938.216/0001-96</p>
                </div>
            </div>
            <div class="foto-section">
                <div class="foto-container">
                    <?php if (!empty($membro['foto_path'])): ?>
                        <img src="<?php echo htmlspecialchars($membro['foto_path']); ?>" alt="Foto">
                    <?php else: ?>
                        <div class="foto-placeholder">📷</div>
                    <?php endif; ?>
                </div>
                <div class="foto-label">Foto 3x4</div>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Título Principal -->
        <div style="text-align: center; margin-bottom: 15px;">
            <h2 style="font-size: 16px; color: #1a2e4a; margin-bottom: 3px;">FICHA DE MEMBRO</h2>
            <p style="font-size: 12px; color: #666;">Integração à Membrasia</p>
        </div>

        <!-- DADOS PESSOAIS -->
        <div class="section-title">Dados Pessoais</div>

        <div class="field-row full">
            <div class="field">
                <div class="field-label">Nome Completo</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['nome_completo']); ?></div>
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <div class="field-label">Data de Nascimento</div>
                <div class="field-value"><?php echo formatarData($membro['data_nascimento']); ?> (<?php echo $idade ? $idade . ' anos' : ''; ?>)</div>
            </div>
            <div class="field">
                <div class="field-label">Sexo</div>
                <div class="field-value"><?php echo $membro['sexo'] === 'M' ? 'Masculino' : ($membro['sexo'] === 'F' ? 'Feminino' : ''); ?></div>
            </div>
            <div class="field">
                <div class="field-label">Tipo Sanguíneo</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['tipo_sanguineo'] ?? ''); ?></div>
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <div class="field-label">Nacionalidade</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['nacionalidade'] ?? ''); ?></div>
            </div>
            <div class="field">
                <div class="field-label">Naturalidade</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['naturalidade'] ?? ''); ?></div>
            </div>
            <div class="field">
                <div class="field-label">Estado (UF)</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['estado_uf'] ?? ''); ?></div>
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <div class="field-label">Escolaridade</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['escolaridade'] ?? ''); ?></div>
            </div>
            <div class="field">
                <div class="field-label">Profissão</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['profissao'] ?? ''); ?></div>
            </div>
            <div class="field">
                <div class="field-label">Renda Familiar</div>
                <div class="field-value"></div>
            </div>
        </div>

        <!-- DOCUMENTOS -->
        <div class="section-title">Documentos</div>

        <div class="field-row">
            <div class="field">
                <div class="field-label">CPF</div>
                <div class="field-value"><?php echo formatarCPF($membro['cpf'] ?? ''); ?></div>
            </div>
            <div class="field">
                <div class="field-label">RG</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['rg'] ?? ''); ?></div>
            </div>
            <div class="field">
                <div class="field-label">Título de Eleitor</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['titulo_eleitor'] ?? ''); ?></div>
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <div class="field-label">CTP</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['ctp'] ?? ''); ?></div>
            </div>
            <div class="field">
                <div class="field-label">CDI</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['cdi'] ?? ''); ?></div>
            </div>
            <div class="field">
                <div class="field-label">Carteira de Trabalho</div>
                <div class="field-value"></div>
            </div>
        </div>

        <!-- FILIAÇÃO -->
        <div class="section-title">Filiação</div>

        <div class="field-row two-col">
            <div class="field">
                <div class="field-label">Pai</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['filiacao_pai'] ?? ''); ?></div>
            </div>
            <div class="field">
                <div class="field-label">Mãe</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['filiacao_mae'] ?? ''); ?></div>
            </div>
        </div>

        <!-- ESTADO CIVIL -->
        <div class="section-title">Estado Civil</div>

        <div class="field-row">
            <div class="field">
                <div class="field-label">Estado Civil</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['estado_civil'] ?? ''); ?></div>
            </div>
            <div class="field">
                <div class="field-label">Cônjuge</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['conjuge'] ?? ''); ?></div>
            </div>
            <div class="field">
                <div class="field-label">Filhos</div>
                <div class="field-value"><?php echo $membro['filhos'] ?? ''; ?></div>
            </div>
        </div>

        <!-- ENDEREÇO RESIDENCIAL -->
        <div class="section-title">Endereço Residencial</div>

        <div class="field-row full">
            <div class="field">
                <div class="field-label">Rua / Avenida</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['endereco_rua'] ?? ''); ?></div>
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <div class="field-label">Número</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['endereco_numero'] ?? ''); ?></div>
            </div>
            <div class="field">
                <div class="field-label">Bairro</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['endereco_bairro'] ?? ''); ?></div>
            </div>
            <div class="field">
                <div class="field-label">CEP</div>
                <div class="field-value"><?php echo formatarCEP($membro['endereco_cep'] ?? ''); ?></div>
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <div class="field-label">Cidade</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['endereco_cidade'] ?? ''); ?></div>
            </div>
            <div class="field">
                <div class="field-label">Estado (UF)</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['endereco_uf'] ?? ''); ?></div>
            </div>
            <div class="field">
                <div class="field-label">Telefone</div>
                <div class="field-value"><?php echo formatarTelefone($membro['telefone'] ?? ''); ?></div>
            </div>
        </div>

        <!-- DADOS ECLESIÁSTICOS -->
        <div class="section-title">Dados Eclesiásticos</div>

        <div class="field-row">
            <div class="field">
                <div class="field-label">Tipo de Integração</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['tipo_integracao'] ?? ''); ?></div>
            </div>
            <div class="field">
                <div class="field-label">Data de Integração</div>
                <div class="field-value"><?php echo formatarData($membro['data_integracao'] ?? ''); ?></div>
            </div>
            <div class="field">
                <div class="field-label">Procedência</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['procedencia'] ?? ''); ?></div>
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <div class="field-label">Batismo em Águas</div>
                <div class="field-value"><?php echo formatarData($membro['batismo_aguas'] ?? ''); ?></div>
            </div>
            <div class="field">
                <div class="field-label">Batismo no Espírito Santo</div>
                <div class="field-value"><?php echo formatarData($membro['batismo_espirito_santo'] ?? ''); ?></div>
            </div>
            <div class="field">
                <div class="field-label">Congregação</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['congregacao'] ?? ''); ?></div>
            </div>
        </div>

        <div class="field-row two-col">
            <div class="field">
                <div class="field-label">Área</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['area'] ?? ''); ?></div>
            </div>
            <div class="field">
                <div class="field-label">Núcleo</div>
                <div class="field-value"><?php echo htmlspecialchars($membro['nucleo'] ?? ''); ?></div>
            </div>
        </div>

        <!-- DECLARAÇÃO -->
        <div class="declaration">
            <div class="declaration-title">Declaração</div>
            <p>Declaro que estou ciente dos princípios bíblicos e doutrinários, projetos gerais, trabalhos e atividades desta Igreja, estando dispostos a cumpri-los, procurando cuidar da mesma, bem como, colaborar com seus projetos materiais, espirituais e financeiros. Solicito, respeitosamente meu ingresso à membrasia desta Igreja.</p>
        </div>

        <!-- Assinaturas -->
        <div class="signatures">
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-label">Assinatura do Solicitante</div>
            </div>
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-label">Assinatura do Pastor Presidente</div>
            </div>
        </div>

        <!-- Caixa de Recebimento -->
        <div class="received-box">
            RECEBIDO EM:<br>
            ___/___/_____<br>
            <div class="received-box-label">SECRETARIA GERAL</div>
        </div>

        <!-- Rodapé -->
        <div class="footer">
            <p>Documento gerado em <?php echo date('d/m/Y H:i:s'); ?></p>
            <p>Sistema de Gerenciamento de Membros - Igreja de Deus Nascer de Novo</p>
        </div>
    </div>

    <!-- Font Awesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>
