<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

// Verifica se quer gerar PDF
$is_print = isset($_GET['print']) && $_GET['print'] == 1;

if ($is_print) {
    // Buscar todas as aquisições e seus ofícios
    $stmt_aq = $pdo->query("
        SELECT
            a.*,
            o.id AS oficio_original_id,
            o.numero AS oficio_num,
            o.justificativa,
            o.arquivo_oficio,
            o.criado_em as oficio_criado_em,
            s.nome AS secretaria,
            s.responsavel AS sec_responsavel,
            f.nome AS fornecedor,
            f.cnpj AS fornecedor_cnpj
        FROM aquisicoes a
        JOIN oficios o ON a.oficio_id = o.id
        JOIN secretarias s ON o.secretaria_id = s.id
        JOIN fornecedores f ON a.fornecedor_id = f.id
        WHERE o.status = 'APROVADO'
        ORDER BY a.criado_em ASC, a.id ASC
    ");
    $aquisicoes = $stmt_aq->fetchAll();

    function h($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
    function money_br($value) { return 'R$ ' . number_format((float) $value, 2, ',', '.'); }

    // Prepara dados para o JS
    $records_js = [];
    foreach ($aquisicoes as $aq) {
        $stmt_anexos = $pdo->prepare("SELECT caminho FROM oficio_anexos WHERE oficio_id = ? AND tipo = 'OFICIO' ORDER BY id ASC");
        $stmt_anexos->execute([$aq['oficio_original_id']]);
        $anexos = $stmt_anexos->fetchAll(PDO::FETCH_COLUMN);

        if (empty($anexos) && !empty($aq['arquivo_oficio'])) {
            $anexos[] = $aq['arquivo_oficio'];
        }

        $records_js[] = [
            'id' => $aq['id'],
            'numero' => $aq['numero_aq'],
            'anexos' => $anexos
        ];
    }
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <title>Gerando PDF do Lote...</title>
        <link rel="stylesheet" href="assets/css/style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js"></script>
        <style>
            body { background: #f8f9fa; font-family: Arial, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
            .loading-container { text-align: center; background: #fff; padding: 3rem; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); max-width: 500px; width: 100%; }
            .spinner { border: 4px solid rgba(0,0,0,0.1); width: 60px; height: 60px; border-radius: 50%; border-left-color: #206bc4; animation: spin 1s linear infinite; margin: 0 auto 1.5rem auto; }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            h2 { margin: 0 0 1rem 0; color: #333; }
            p { color: #666; margin-bottom: 2rem; }
            #progress-bar-container { background: #eef2f7; border-radius: 8px; height: 12px; overflow: hidden; margin-bottom: 10px; }
            #progress-bar { background: #206bc4; height: 100%; width: 0%; transition: width 0.3s; }
            #status-text { font-weight: bold; color: #206bc4; }

            /* Estilos de impressão (Fora da tela) */
            #render-container { position: absolute; left: -9999px; top: 0; width: 793px; background: #fff; }
            .aq-folha { padding: 38px; box-sizing: border-box; background: #fff; color: #000; font-family: Arial, sans-serif; }
            .ordem-header { display: flex; align-items: center; border-bottom: 2px solid #000; padding-bottom: 15px; margin-bottom: 20px; }
            .ordem-logo img { width: 140px; height: auto; display: block; }
            .ordem-center { flex-grow: 1; text-align: center; padding: 0 10px; }
            .ordem-center h1 { font-size: 18px; margin: 0; font-weight: bold; }
            .ordem-center h2 { font-size: 14px; margin: 5px 0 0; }
            .ordem-right { text-align: right; }
            .ordem-right-box { border: 2px solid #000; padding: 5px 15px; display: inline-block; text-align: center; }
            .ordem-info-table, .ordem-items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }
            .ordem-info-table td, .ordem-items-table th, .ordem-items-table td { border: 1px solid #000; padding: 8px; word-wrap: break-word; }
            .ordem-info-label { font-weight: bold; background: #f0f0f0; }
            .ordem-items-table th { background: #f0f0f0; font-weight: bold; text-align: center; }
            
            /* Previne quebra de linha feia cortando o texto ao meio */
            .ordem-items-table tr { page-break-inside: avoid; }
            td { page-break-inside: avoid; }
            .assinaturas { display: flex; justify-content: space-between; margin-top: 50px; text-align: center; page-break-inside: avoid; }
            .assinaturas > div { width: 45%; border-top: 1px solid #000; padding-top: 5px; font-size: 12px; font-weight: bold; }
        </style>
    </head>
    <body>

        <div class="loading-container" id="loading-box">
            <div class="spinner"></div>
            <h2>Montando Lote de Aquisições</h2>
            <p>Por favor, não feche esta página. O sistema está mesclando o HTML das aquisições com os PDFs dos Ofícios anexados.</p>
            <div id="progress-bar-container">
                <div id="progress-bar"></div>
            </div>
            <div id="status-text">Iniciando...</div>
        </div>

        <div class="loading-container" id="done-box" style="display: none;">
            <i class="fas fa-check-circle" style="font-size: 60px; color: #28a745; margin-bottom: 1.5rem;"></i>
            <h2>Lote Gerado com Sucesso!</h2>
            <p>O download do PDF completo começará em instantes. Caso contrário, clique no botão abaixo.</p>
            <a href="#" id="download-btn" class="btn btn-primary">Baixar Arquivo PDF</a>
            <br><br>
            <a href="imprimir_lote.php" class="btn btn-outline" style="text-decoration: none;">Voltar</a>
        </div>

        <!-- HTML oculto para renderização das Aquisições -->
        <div id="render-container">
            <?php foreach ($aquisicoes as $aq): ?>
                <?php
                    $stmt_items_aq = $pdo->prepare("SELECT * FROM itens_aquisicao WHERE aquisicao_id = ? ORDER BY id ASC");
                    $stmt_items_aq->execute([$aq['id']]);
                    $items_aq = $stmt_items_aq->fetchAll();
                ?>
                <div id="aq-html-<?= $aq['id'] ?>" class="aq-folha">
                    <div class="ordem-header">
                        <div class="ordem-logo">
                            <img src="assets/img/prefeitura.jpg" alt="Logo">
                        </div>
                        <div class="ordem-center">
                            <h1>PREFEITURA MUNICIPAL DE COARI</h1>
                            <h2>Ordem de Aquisição e Suprimentos</h2>
                            <div style="font-size: 10px; margin-top: 5px;">COARI - AM | CNPJ: 04.262.432/0001-21</div>
                        </div>
                        <div class="ordem-right">
                            <div style="font-size: 10px; font-weight: bold; margin-bottom: 5px;">Via Administrativa</div>
                            <div class="ordem-right-box">
                                <span style="font-size: 10px;">Ordem Nº</span><br>
                                <span style="font-size: 18px; font-weight: bold;"><?= h(str_replace('AQ-', '', $aq['numero_aq'])) ?></span>
                            </div>
                            <div style="font-size: 10px; margin-top: 5px;">DATA: <?= date('d/m/Y', strtotime($aq['criado_em'])) ?></div>
                        </div>
                    </div>

                    <table class="ordem-info-table">
                        <tr>
                            <td class="ordem-info-label" style="width: 15%;">Fornecedor:</td>
                            <td style="font-weight: bold;"><?= h(strtoupper($aq['fornecedor'])) ?></td>
                            <td class="ordem-info-label" style="width: 25%;">Local e Data:</td>
                            <td>COARI-AM, <?= date('d/m/Y', strtotime($aq['criado_em'])) ?></td>
                        </tr>
                        <tr>
                            <td class="ordem-info-label">Para:</td>
                            <td style="font-weight: bold;"><?= h(strtoupper($aq['secretaria'])) ?></td>
                            <td class="ordem-info-label">Ref. Ofício:</td>
                            <td style="font-weight: bold;"><?= h($aq['oficio_num']) ?></td>
                        </tr>
                    </table>

                    <h3 style="font-size: 14px; text-transform: uppercase;">Autorização de Fornecimento - AF</h3>

                    <table class="ordem-items-table">
                        <thead>
                            <tr>
                                <th style="width: 30px;">Item</th>
                                <th style="width: 40px;">Unid.</th>
                                <th style="width: 50px;">Qtd</th>
                                <th>Especificação</th>
                                <th style="width: 90px;">Val. Unit</th>
                                <th style="width: 90px;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items_aq)): ?>
                                <tr><td colspan="6" style="text-align: center;">Nenhum item.</td></tr>
                            <?php else: ?>
                                <?php $i = 1; $valorTotalAquisicao = 0; ?>
                                <?php foreach ($items_aq as $item): ?>
                                    <?php
                                    $qtd = (float) $item['quantidade'];
                                    $v_un = (float) $item['valor_unitario'];
                                    $v_tot = $qtd * $v_un;
                                    $valorTotalAquisicao += $v_tot;
                                    ?>
                                    <tr>
                                        <td style="text-align: center;"><?= str_pad($i++, 2, '0', STR_PAD_LEFT) ?></td>
                                        <td style="text-align: center;">UN</td>
                                        <td style="text-align: center;"><?= number_format($qtd, 0, ',', '.') ?></td>
                                        <td><?= h(strtoupper($item['produto'])) ?></td>
                                        <td style="text-align: center;"><?= money_br($v_un) ?></td>
                                        <td style="text-align: center; font-weight: bold;"><?= money_br($v_tot) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr style="background: #f0f0f0;">
                                    <td colspan="5" style="text-align: right; font-weight: bold;">TOTAL R$</td>
                                    <td style="text-align: right; font-weight: bold;"><?= money_br($valorTotalAquisicao) ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div class="assinaturas">
                        <div>
                            RECEBEDOR<br>
                            <span style="font-weight: normal; font-size: 10px;">Autorização de Recebimento</span>
                        </div>
                        <div>
                            CONFIRMAÇÃO DE RECEBIMENTO<br>
                            <span style="font-weight: normal; font-size: 10px;">Assinatura e Carimbo</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <script>
            const records = <?= json_encode($records_js) ?>;
            const statusText = document.getElementById('status-text');
            const progressBar = document.getElementById('progress-bar');
            const loadingBox = document.getElementById('loading-box');
            const doneBox = document.getElementById('done-box');
            const downloadBtn = document.getElementById('download-btn');

            async function gerarPdfLote() {
                const { PDFDocument } = window.PDFLib;
                
                try {
                    // Create main document
                    const doc = await PDFDocument.create();
                    
                    for (let i = 0; i < records.length; i++) {
                        const rec = records[i];
                        
                        // Atualiza status
                        const pct = Math.round(((i) / records.length) * 100);
                        progressBar.style.width = pct + '%';
                        statusText.innerText = `Processando Aquisição ${i+1} de ${records.length} (${rec.numero})...`;

                        // 1. GERAR HTML DA AQUISIÇÃO PARA PDF
                        const elem = document.getElementById('aq-html-' + rec.id);
                        const opt = {
                            margin: 0, 
                            filename: 'temp.pdf',
                            pagebreak: { mode: 'css', avoid: ['tr'] },
                            image: { type: 'jpeg', quality: 1 },
                            html2canvas: { scale: 2, logging: false, useCORS: true },
                            jsPDF: { unit: 'px', format: [793, 1122], orientation: 'portrait', hotfixes: ["px_scaling"] }
                        };
                        
                        const worker = html2pdf().set(opt).from(elem);
                        const aqPdfBlob = await worker.outputPdf('blob');
                        const aqPdfBuffer = await aqPdfBlob.arrayBuffer();

                        // Mescla pagina da Aquisição
                        const aqDocLoaded = await PDFDocument.load(aqPdfBuffer);
                        const copiedAqPages = await doc.copyPages(aqDocLoaded, aqDocLoaded.getPageIndices());
                        copiedAqPages.forEach(p => doc.addPage(p));

                        // 2. BUSCAR E MESCLAR OS ANEXOS (OFÍCIOS REAIS)
                        let hasAnyAnexo = false;
                        if (rec.anexos && rec.anexos.length > 0) {
                            for (let j = 0; j < rec.anexos.length; j++) {
                                let caminhoAnexo = rec.anexos[j];
                                
                                try {
                                    const resp = await fetch(caminhoAnexo);
                                    if(!resp.ok) continue;
                                    const buffer = await resp.arrayBuffer();
                                    
                                    const ext = caminhoAnexo.split('.').pop().toLowerCase();
                                    
                                    if(ext === 'pdf') {
                                        const anexoDoc = await PDFDocument.load(buffer, { ignoreEncryption: true });
                                        const copiedAnexoPages = await doc.copyPages(anexoDoc, anexoDoc.getPageIndices());
                                        copiedAnexoPages.forEach(p => doc.addPage(p));
                                        hasAnyAnexo = true;
                                    } else if(['jpg','jpeg','png'].includes(ext)) {
                                        let image;
                                        if (ext === 'png') {
                                            image = await doc.embedPng(buffer);
                                        } else {
                                            image = await doc.embedJpg(buffer);
                                        }
                                        
                                        const page = doc.addPage();
                                        const { width, height } = page.getSize();
                                        
                                        // Dimensiona a imagem para caber na A4 (com margem)
                                        const margin = 30; // approx 1cm
                                        const imgDims = image.scaleToFit(width - (margin*2), height - (margin*2));
                                        
                                        page.drawImage(image, {
                                            x: (width - imgDims.width) / 2,
                                            y: (height - imgDims.height) / 2,
                                            width: imgDims.width,
                                            height: imgDims.height,
                                        });
                                        hasAnyAnexo = true;
                                    }
                                } catch(e) {
                                    console.log("Erro ao anexar arquivo", caminhoAnexo, e);
                                    // Ignora arquivo faltante e segue
                                }
                            }
                        }
                    }

                    // Fim do Processo
                    progressBar.style.width = '100%';
                    statusText.innerText = "Finalizado! Preparando download...";

                    const pdfBytes = await doc.save();
                    const finalBlob = new Blob([pdfBytes], { type: 'application/pdf' });
                    const finalUrl = URL.createObjectURL(finalBlob);

                    loadingBox.style.display = 'none';
                    doneBox.style.display = 'block';

                    downloadBtn.href = finalUrl;
                    downloadBtn.download = `Lote_Aquisicoes_Oficios_${new Date().getTime()}.pdf`;
                    downloadBtn.click(); // Autoclick
                    
                } catch(error) {
                    console.error("Erro fatal na geracao:", error);
                    statusText.innerText = "Houve um erro ao gerar o PDF. Verifique o console.";
                    progressBar.style.backgroundColor = 'red';
                }
            }

            // Inicia assim que a página carregar
            window.addEventListener('load', () => { // Ensure fonts/images are ready
                setTimeout(gerarPdfLote, 500);
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// VIEW NORMAL PARA CLICAR "IMPRIMIR LOTE"
$page_title = "Impressão em Lote";
include 'views/layout/header.php';
?>

<div class="card">
    <div class="card-body" style="text-align: center; padding: 4rem 2rem;">
        <div style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem;">
            <i class="fas fa-file-pdf"></i>
        </div>
        <h2 style="margin-bottom: 1rem; color: var(--text-dark);">Gerar Documento Unificado (PDF)</h2>
        <p style="color: var(--text-muted); max-width: 600px; margin: 0 auto 2rem auto; font-size: 1.1rem; line-height: 1.6;">
            Esta ferramenta gera um único arquivo PDF contendo <strong>todas as Aquisições (Via Administrativa)</strong> 
            e logo em seguida intercala com <strong>os arquivos originais em anexo (PDF/Fotos)</strong> dos respectivos Ofícios.
            <br><br>
            A montagem será nativa na seguinte ordem:
            <br>
            <strong>Aquisição 1 (Sistema) &rarr; Arquivo Anexo do Ofício 1 &rarr; Aquisição 2 (Sistema) &rarr; Arquivo Anexo 2 ...</strong>
        </p>

        <a href="imprimir_lote.php?print=1" target="_blank" class="btn btn-primary" style="padding: 1rem 2.5rem; font-size: 1.15rem; border-radius: 50px; font-weight: 800;">
            <i class="fas fa-magic" style="margin-right: 8px;"></i> Processar e Baixar Lote Completo
        </a>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>
