<?php
ob_start();
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] === 'ACESSORES') {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCP - Cadastro de Processo</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<header class="navbar">
    <div class="logo">
        <i class="fas fa-balance-scale"></i>
        <span>SCP PGM</span>
    </div>
    <nav class="nav-links">
        <a href="index.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
        <a href="cadastro.php" class="nav-link active"><i class="fas fa-plus-circle"></i> Novo</a>
        <a href="prazos.php" class="nav-link"><i class="fas fa-clock"></i> Prazos</a>
        <a href="tipos.php" class="nav-link"><i class="fas fa-layer-group"></i> Tipos</a>
        <a href="relatorios.php" class="nav-link"><i class="fas fa-chart-line"></i> Relatórios</a>
        <?php if ($_SESSION['usuario_perfil'] === 'ADMIN'): ?>
        <a href="usuarios.php" class="nav-link"><i class="fas fa-users"></i> Usuários</a>
        <a href="configuracoes.php" class="nav-link"><i class="fas fa-cog"></i></a>
        <?php endif; ?>
    </nav>
    <div style="display: flex; align-items: center; gap: 1rem;">
        <div id="nome-analisador" style="font-weight: 600; font-size: 0.9rem; color: var(--text-main);">
            <i class="fas fa-user-circle" style="color: var(--primary); margin-right: 5px;"></i>
            <?php echo $_SESSION['usuario_nome']; ?>
        </div>
        <a href="api.php?acao=logout" class="btn-quick" style="color: #f87171; border:none;" title="Sair">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</header>

<main class="main-content">
    <header class="header">
        <div class="title-group">
            <h1>Cadastro de Processo</h1>
            <p>Preencha os dados abaixo para iniciar o controle.</p>
        </div>
        <a href="index.php" class="btn btn-secondary" style="background: white; border: 1px solid var(--border);"><i class="fas fa-arrow-left"></i> Voltar</a>
    </header>

    <form id="form-processo" class="data-section">
        <input type="hidden" id="processo-id" value="">
        
        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step-item active" id="indicator-1" onclick="irParaEtapa(1)">
                <div class="step-number">1</div>
                <span>Identificação</span>
            </div>
            <div class="step-item" id="indicator-2" onclick="irParaEtapa(2)">
                <div class="step-number">2</div>
                <span>Conclusão</span>
            </div>
        </div>

        <!-- Step 1: Identificação -->
        <div class="form-step form-step-active" id="step-1">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
            <!-- Dados Básicos -->
            <div class="form-group">
                <label for="numero_processo">Nº do Processo</label>
                <input type="text" id="numero_processo" placeholder="0000000-00.0000.8.04.0000" required>
            </div>

            <div class="form-group">
                <label for="tipo_processo">Tipo de Processo</label>
                <select id="tipo_processo" required>
                    <option value="">Selecione...</option>
                    <option value="CIÊNCIA">CIÊNCIA</option>
                    <option value="CUMPRIMENTO">CUMPRIMENTO</option>
                    <option value="RECURSO - CIÊNCIA">RECURSO - CIÊNCIA</option>
                    <option value="RECURSO - CUMPRIMENTO">RECURSO - CUMPRIMENTO</option>
                </select>
            </div>

            <div class="form-group">
                <label for="tipo_ato">Tipo de Ato</label>
                <select id="tipo_ato" required>
                    <option value="">Selecione...</option>
                    <option value="DECISÃO">DECISÃO</option>
                    <option value="DESPACHO">DESPACHO</option>
                    <option value="JUNTADA DE ANÁLISE DE DECURSO DE PRAZO">JUNTADA DE ANÁLISE DE DECURSO DE PRAZO</option>
                    <option value="JUNTADA DE ATO ORDINATÓRIO">JUNTADA DE ATO ORDINATÓRIO</option>
                    <option value="EXPEDIÇÃO DE OFÍCIO">EXPEDIÇÃO DE OFÍCIO</option>
                    <option value="CERTIDÃO">CERTIDÃO</option>
                    <option value="SENTENÇA">SENTENÇA</option>
                    <option value="SEQUESTRO DE VALOR">SEQUESTRO DE VALOR</option>
                    <option value="JUNTADA DE CUMPRIMENTO DE DILIGÊNCIA">JUNTADA DE CUMPRIMENTO DE DILIGÊNCIA</option>
                    <option value="JUNTADA DE PETIÇÃO DE MANIFESTAÇÃO DA PARTE">JUNTADA DE PETIÇÃO DE MANIFESTAÇÃO DA PARTE</option>
                    <option value="CIÊNCIA">CIÊNCIA</option>
                </select>
            </div>

            <div class="form-group">
                <label for="natureza_prazo">Natureza do Prazo</label>
                <select id="natureza_prazo" required>
                    <option value="">Selecione...</option>
                    <option value="MANIFESTAÇÃO">MANIFESTAÇÃO</option>
                    <option value="PAGAMENTO">PAGAMENTO</option>
                    <option value="RECURSO">RECURSO</option>
                    <option value="IMPUGNAÇÃO">IMPUGNAÇÃO</option>
                    <option value="REMETIDOS OS AUTOS">REMETIDOS OS AUTOS</option>
                    <option value="CUMPRIMENTO DA DECISÃO">CUMPRIMENTO DA DECISÃO</option>
                    <option value="CONTESTAÇÃO">CONTESTAÇÃO</option>
                    <option value="APELAÇÃO">APELAÇÃO</option>
                    <option value="FINALIZADO">FINALIZADO</option>
                    <option value="AUDIÊNCIA">AUDIÊNCIA</option>
                    <option value="ANÁLISE">ANÁLISE</option>
                    <option value="CIÊNCIA">CIÊNCIA</option>
                </select>
            </div>

            <div class="form-group">
                <label for="revelia">Revelia / Desnec. Audiência</label>
                <select id="revelia">
                    <option value="NÃO" style="color: red;">NÃO</option>
                    <option value="SIM" style="color: green;">SIM</option>
                </select>
            </div>

            <!-- Datas e Contagem -->
            <div class="form-group">
                <label for="data_envio_intimacao">Data de Envio da Intimação</label>
                <input type="date" id="data_envio_intimacao" required>
            </div>

            <div class="form-group">
                <label for="data_ciencia">Data da Ciência</label>
                <input type="date" id="data_ciencia" required>
            </div>

            <div class="form-group">
                <label for="tipo_contagem">Tipo de Contagem (Cálculo Automático)</label>
                <select id="tipo_contagem" required>
                    <option value="ÚTEIS">DIAS ÚTEIS</option>
                    <option value="CORRIDOS">DIAS CORRIDOS</option>
                    <option value="REDESIGNADA">REDESIGNADA</option>
                </select>
            </div>

                <div class="form-group">
                    <label for="final_prazo">Data Final do Prazo</label>
                    <input type="date" id="final_prazo" required>
                </div>

                <div class="form-group">
                    <label for="quantidade_dias">Qtd. Dias (Calculado)</label>
                    <input type="number" id="quantidade_dias" readonly style="background: #f1f5f9; cursor: not-allowed; font-weight: 700; color: var(--primary);">
                </div>
            </div>
            
            <div class="form-navigation">
                <span></span> <!-- Spacer -->
                <button type="button" class="btn btn-primary" onclick="proximaEtapa()">Próximo <i class="fas fa-arrow-right"></i></button>
            </div>
        </div>

        <!-- Step 2: Conclusão -->
        <div class="form-step" id="step-2">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                <div class="form-group">
                    <label for="prazo_critico">Prazo Crítico?</label>
                    <select id="prazo_critico">
                        <option value="NÃO">NÃO</option>
                        <option value="SIM">SIM</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="tipo_manifestacao">Tipo de Manifestação</label>
                    <input type="text" id="tipo_manifestacao" placeholder="Ex: Manifestar a respeito do valor">
                </div>

                <!-- Campos Reais (usados para salvar no BD) -->
                <input type="hidden" id="data_protocolo" value="">
                <input type="hidden" id="data_analise" value="">
                <input type="hidden" id="analisador" value="<?php echo $_SESSION['usuario_nome']; ?>">
                <input type="hidden" id="protocolista" value="">

                <!-- Visualização Dinâmica do Protocolo -->
                <div id="container-protocolo" style="display: none; grid-column: span 2; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; background: rgba(34, 197, 94, 0.05); padding: 1.5rem; border-radius: var(--radius); border: 1px dashed rgba(34, 197, 94, 0.3);">
                    <div style="grid-column: span 2; margin-bottom: -0.5rem;">
                        <h3 style="color: var(--status-protocolado); font-size: 0.95rem; border-bottom: 2px solid var(--status-protocolado); display: inline-block; padding-bottom: 4px;"><i class="fas fa-check-circle"></i> Dados do Protocolo</h3>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Data do Protocolo</label>
                        <input type="date" id="data_protocolo_visivel" readonly style="background: white; cursor: not-allowed; font-weight: 700; color: var(--text-main);">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Responsável pelo Protocolo</label>
                        <input type="text" id="protocolista_visivel" readonly style="background: white; cursor: not-allowed; font-weight: 700; color: var(--text-main);">
                    </div>
                </div>

                <!-- Visualização Dinâmica da Análise -->
                <div id="container-analise" style="display: none; grid-column: span 2; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; background: rgba(59, 130, 246, 0.05); padding: 1.5rem; border-radius: var(--radius); border: 1px dashed rgba(59, 130, 246, 0.3);">
                    <div style="grid-column: span 2; margin-bottom: -0.5rem;">
                        <h3 style="color: var(--status-analisado); font-size: 0.95rem; border-bottom: 2px solid var(--status-analisado); display: inline-block; padding-bottom: 4px;"><i class="fas fa-eye"></i> Dados da Análise</h3>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Data da Análise</label>
                        <input type="date" id="data_analise_visivel" readonly style="background: white; cursor: not-allowed; font-weight: 700; color: var(--text-main);">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Responsável pela Análise</label>
                        <input type="text" id="analisador_visivel" readonly style="background: white; cursor: not-allowed; font-weight: 700; color: var(--text-main);">
                    </div>
                </div>

                <input type="hidden" id="peticionador" value="">
                <input type="hidden" id="data_peticionamento" value="">

                <!-- Visualização Dinâmica do Peticionamento -->
                <div id="container-peticionamento" style="display: none; grid-column: span 2; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; background: rgba(139, 92, 246, 0.05); padding: 1.5rem; border-radius: var(--radius); border: 1px dashed rgba(139, 92, 246, 0.3);">
                    <div style="grid-column: span 2; margin-bottom: -0.5rem;">
                        <h3 style="color: #8b5cf6; font-size: 0.95rem; border-bottom: 2px solid #8b5cf6; display: inline-block; padding-bottom: 4px;"><i class="fas fa-file-upload"></i> Dados do Peticionamento</h3>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Data de Peticionamento</label>
                        <input type="date" id="data_peticionamento_visivel" readonly style="background: white; cursor: not-allowed; font-weight: 700; color: var(--text-main);">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Responsável por Peticionar</label>
                        <input type="text" id="peticionador_visivel" readonly style="background: white; cursor: not-allowed; font-weight: 700; color: var(--text-main);">
                    </div>
                </div>

                <div class="form-group" style="grid-column: span 2;">
                    <label for="observacoes">Observações</label>
                    <textarea id="observacoes" rows="4" placeholder="Adicione notas ou detalhes importantes sobre este processo..."></textarea>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status">
                        <option value="PENDENTE" style="color: var(--status-pendente);">PENDENTE</option>
                        <option value="PROTOCOLADO" style="color: var(--status-protocolado);">PROTOCOLADO</option>
                        <option value="ANALISADO" style="color: var(--status-analisado);">ANALISADO</option>
                    </select>
                </div>
            </div>

            <div class="form-navigation">
                <button type="button" class="btn btn-secondary" onclick="etapaAnterior()"><i class="fas fa-arrow-left"></i> Anterior</button>
                <button type="submit" class="btn btn-primary" id="btn-salvar">Salvar Processo <i class="fas fa-check"></i></button>
            </div>
        </div>
    </form>
</main>

<script src="assets/js/script.js?v=61"></script>
</body>
</html>
