<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCP 2.0 - Cadastro de Processo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="v2/assets/css/style.css">
    <style>
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
        label { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); }
        input, select, textarea { background: rgba(0, 0, 0, 0.2); border: 1px solid var(--border); border-radius: 10px; padding: 0.8rem; color: white; outline: none; transition: border-color 0.3s; }
        input:focus, select:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 10px var(--primary-glow); }
        .section-title { font-size: 1.1rem; font-weight: 700; margin-top: 2rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border); color: var(--primary); }
        
        /* Sistema de Abas (Steps) */
        .step-indicator { display: flex; gap: 1rem; margin-bottom: 2rem; background: rgba(0,0,0,0.2); padding: 10px; border-radius: 12px; border: 1px solid var(--border); }
        .step-item { flex: 1; text-align: center; padding: 10px; cursor: pointer; border-radius: 8px; transition: 0.3s; color: var(--text-muted); font-weight: 600; }
        .step-item.active { background: var(--primary); color: white; box-shadow: 0 0 15px var(--primary-glow); }
        .form-step { display: none; animation: fadeIn 0.3s; }
        .form-step.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Timeline */
        .timeline { display: flex; justify-content: space-between; position: relative; margin: 2rem 0; padding: 0 20px; }
        .timeline::before { content: ''; position: absolute; top: 15px; left: 0; width: 100%; height: 2px; background: var(--border); z-index: 1; }
        .timeline-item { position: relative; z-index: 2; text-align: center; width: 33%; }
        .timeline-icon { width: 32px; height: 32px; border-radius: 50%; background: var(--glass-bg); border: 2px solid var(--border); display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; color: var(--text-muted); transition: 0.3s; }
        .timeline-item.done .timeline-icon { background: var(--status-protocolado); border-color: var(--status-protocolado); color: white; box-shadow: 0 0 10px rgba(34,197,94,0.5); }
        .timeline-title { font-size: 0.8rem; font-weight: 700; color: var(--text-muted); }
        .timeline-item.done .timeline-title { color: white; }
    </style>
</head>
<body>

    <nav class="premium-nav">
        <div class="logo-group" style="cursor:pointer" onclick="location.href='v2/index.php'">
            <i class="fas fa-microchip"></i>
            <span>SCP 2.0</span>
        </div>
        <div style="display: flex; gap: 2rem; align-items: center;">
            <button class="btn-premium" style="background: var(--border);" onclick="location.href='v2/index.php'">
                <i class="fas fa-arrow-left"></i> Voltar ao Painel
            </button>
        </div>
    </nav>

    <main style="padding: 2rem; max-width: 1000px; margin: 0 auto;">
        
        <header style="margin-bottom: 2rem;">
            <h1 style="font-size: 2rem; font-weight: 800;">Registro de Processo</h1>
            <p style="color: var(--text-muted);">Padrão SEEU - Preenchimento obrigatório da máscara CNJ.</p>
        </header>

        <div class="glass-card">
            
            <div class="step-indicator">
                <div class="step-item active" id="tab-1" onclick="switchStep(1)">1. Identificação</div>
                <div class="step-item" id="tab-2" onclick="switchStep(2)">2. Controle e Prazos</div>
                <div class="step-item" id="tab-3" onclick="switchStep(3)">3. Conclusão</div>
            </div>

            <form id="form-cadastro-v2">
                <input type="hidden" id="id" name="id">
                
                <!-- PASSO 1: IDENTIFICAÇÃO -->
                <div id="step-1" class="form-step active">
                    <div class="section-title"><i class="fas fa-info-circle"></i> Informações Básicas</div>
                    <div class="form-grid">
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Número do Processo (Padrão CNJ)</label>
                            <input type="text" id="numero" name="numero" placeholder="0000000-00.0000.8.04.0000" required maxlength="25">
                            <small style="color: var(--status-protocolado); display: none;" id="cnj-valido"><i class="fas fa-check"></i> Formato válido</small>
                        </div>
                        <div class="form-group">
                            <label>Tipo de Processo</label>
                            <select id="tipo_processo" name="tipo_processo" required>
                                <option value="CIÊNCIA">CIÊNCIA</option>
                                <option value="CUMPRIMENTO">CUMPRIMENTO</option>
                                <option value="RECURSO - CIÊNCIA">RECURSO - CIÊNCIA</option>
                                <option value="RECURSO - CUMPRIMENTO">RECURSO - CUMPRIMENTO</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Natureza do Prazo</label>
                            <select id="natureza_prazo" name="natureza_prazo" required>
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
                                <option value="PERSONALIZADO" style="color: var(--primary);">PERSONALIZADO</option>
                            </select>
                            <input type="text" id="natureza_prazo_personalizado" name="natureza_prazo_personalizado" placeholder="Digite a natureza..." style="display: none; margin-top: 5px;">
                        </div>
                        <div class="form-group">
                            <label>Tipo de Ato</label>
                            <select id="tipo_ato" name="tipo_ato" required>
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
                                <option value="PERSONALIZADO" style="color: var(--primary);">PERSONALIZADO</option>
                            </select>
                            <input type="text" id="tipo_ato_personalizado" name="tipo_ato_personalizado" placeholder="Digite o tipo de ato..." style="display: none; margin-top: 5px;">
                        </div>
                        <div class="form-group">
                            <label>Revelia / Desnec. Audiência</label>
                            <select id="revelia" name="revelia">
                                <option value="NÃO" style="color: #f87171;">NÃO</option>
                                <option value="SIM" style="color: #4ade80;">SIM</option>
                            </select>
                        </div>
                    </div>
                    <div style="text-align: right; margin-top: 2rem;">
                        <button type="button" class="btn-premium" onclick="switchStep(2)">Avançar <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>

                <!-- PASSO 2: CONTROLE E PRAZOS (CALCULADORA) -->
                <div id="step-2" class="form-step">
                    <div class="section-title"><i class="fas fa-calendar-alt"></i> Prazos e Datas</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Data de Envio da Intimação</label>
                            <input type="date" id="data_envio_intimacao" name="data_envio_intimacao" required>
                        </div>
                        <div class="form-group">
                            <label>Data da Ciência</label>
                            <input type="date" id="data_ciencia" name="data_ciencia" required onchange="calcularPrazoFinal()">
                        </div>
                        <div class="form-group">
                            <label>Tipo de Contagem (Cálculo Automático)</label>
                            <select id="tipo_contagem" name="tipo_contagem" required onchange="calcularPrazoFinal()">
                                <option value="ÚTEIS">DIAS ÚTEIS</option>
                                <option value="CORRIDOS">DIAS CORRIDOS</option>
                                <option value="REDESIGNADA">REDESIGNADA</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Qtd. Dias</label>
                            <input type="number" id="quantidade_dias" name="quantidade_dias" value="15" oninput="calcularPrazoFinal()">
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Data Final do Prazo</label>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input type="date" id="final_prazo" name="final_prazo" required style="flex:1;">
                                <button type="button" class="btn-premium" style="background: var(--primary);" onclick="calcularPrazoFinal()" title="Recalcular">
                                    <i class="fas fa-calculator"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-top: 2rem;">
                        <button type="button" class="btn-premium" style="background: var(--border);" onclick="switchStep(1)"><i class="fas fa-arrow-left"></i> Voltar</button>
                        <button type="button" class="btn-premium" onclick="switchStep(3)">Avançar <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>

                <!-- PASSO 3: CONCLUSÃO -->
                <div id="step-3" class="form-step">
                    <div class="section-title"><i class="fas fa-flag-checkered"></i> Conclusão e Atribuição</div>
                    
                    <div class="timeline" id="timeline-processo" style="display: none;">
                        <div class="timeline-item done" id="tl-ciencia">
                            <div class="timeline-icon"><i class="fas fa-file-signature"></i></div>
                            <div class="timeline-title">Ciência</div>
                        </div>
                        <div class="timeline-item" id="tl-analise">
                            <div class="timeline-icon"><i class="fas fa-search"></i></div>
                            <div class="timeline-title">Em Análise</div>
                        </div>
                        <div class="timeline-item" id="tl-protocolo">
                            <div class="timeline-icon"><i class="fas fa-check-double"></i></div>
                            <div class="timeline-title">Protocolado</div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Prazo Crítico?</label>
                            <select id="prazo_critico" name="prazo_critico">
                                <option value="NÃO">NÃO</option>
                                <option value="SIM" style="color: #f87171;">SIM (Alta Prioridade)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tipo de Manifestação</label>
                            <input type="text" id="tipo_manifestacao" name="tipo_manifestacao" placeholder="Ex: Manifestar a respeito do valor">
                        </div>
                        <div class="form-group">
                            <label>Analisador Responsável</label>
                            <input type="text" id="analisador" name="analisador" value="<?php echo $_SESSION['usuario_nome']; ?>" readonly style="background: rgba(255,255,255,0.05);">
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select id="status" name="status" onchange="updateTimeline()">
                                <option value="PENDENTE" style="color: #ef4444; font-weight: bold;">PENDENTE</option>
                                <option value="SENDO AVALIADO" style="color: #eab308; font-weight: bold;">SENDO AVALIADO</option>
                                <option value="EM ELABORAÇÃO" style="color: #f97316; font-weight: bold;">EM ELABORAÇÃO</option>
                                <option value="PROTOCOLADO" style="color: #22c55e; font-weight: bold;">PROTOCOLADO</option>
                                <option value="ANALISADO" style="color: #3b82f6; font-weight: bold;">ANALISADO</option>
                                <option value="PROCESSO FINALIZADO" style="color: #94a3b8; font-weight: bold;">PROCESSO FINALIZADO</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 1.5rem;">
                        <label>Observações</label>
                        <textarea id="observacoes" name="observacoes" rows="4" placeholder="Adicione notas ou detalhes importantes..."></textarea>
                    </div>

                    <div style="margin-top: 2.5rem; display: flex; justify-content: space-between;">
                        <button type="button" class="btn-premium" style="background: var(--border);" onclick="switchStep(2)"><i class="fas fa-arrow-left"></i> Voltar</button>
                        <button type="submit" class="btn-premium">
                            <i class="fas fa-save"></i> Gravar Processo
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Lógica de Abas
        function switchStep(step) {
            document.querySelectorAll('.form-step').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.step-item').forEach(el => el.classList.remove('active'));
            document.getElementById('step-' + step).classList.add('active');
            document.getElementById('tab-' + step).classList.add('active');
        }

        // Máscara CNJ (0000000-00.0000.8.04.0000)
        document.getElementById('numero').addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            if(v.length > 20) v = v.substring(0, 20);
            
            if(v.length > 16) {
                v = v.replace(/^(\d{7})(\d{2})(\d{4})(\d{1})(\d{2})(\d{4})$/, "$1-$2.$3.$4.$5.$6");
            } else if(v.length > 13) {
                v = v.replace(/^(\d{7})(\d{2})(\d{4})(\d{1})/, "$1-$2.$3.$4");
            } else if(v.length > 9) {
                v = v.replace(/^(\d{7})(\d{2})(\d{1,4})/, "$1-$2.$3");
            } else if(v.length > 7) {
                v = v.replace(/^(\d{7})(\d{1,2})/, "$1-$2");
            }
            e.target.value = v;

            const cnjValido = document.getElementById('cnj-valido');
            if(v.length === 25) cnjValido.style.display = 'block';
            else cnjValido.style.display = 'none';
        });

        // Calculadora Inteligente de Prazos (Padrão SEEU - Pula Finais de Semana)
        window.calcularPrazoFinal = function() {
            const dataCienciaStr = document.getElementById('data_ciencia').value;
            let dias = parseInt(document.getElementById('quantidade_dias').value) || 0;
            const tipo = document.getElementById('tipo_contagem').value;

            if(!dataCienciaStr || dias === 0) return;

            let data = new Date(dataCienciaStr + 'T12:00:00'); // Evita timezone issues
            let contagem = 0;

            if (tipo === 'ÚTEIS') {
                while(contagem < dias) {
                    data.setDate(data.getDate() + 1);
                    const diaSemana = data.getDay();
                    if(diaSemana !== 0 && diaSemana !== 6) { // 0 = Domingo, 6 = Sábado
                        contagem++;
                    }
                }
            } else { // CORRIDOS ou REDESIGNADA
                data.setDate(data.getDate() + dias);
            }

            document.getElementById('final_prazo').value = data.toISOString().split('T')[0];
        };

        // Personalizados
        const checkCustom = (sId, iId) => {
            const s = document.getElementById(sId);
            const i = document.getElementById(iId);
            if(s && i) {
                s.addEventListener('change', () => {
                    i.style.display = s.value === 'PERSONALIZADO' ? 'block' : 'none';
                });
            }
        };
        checkCustom('tipo_ato', 'tipo_ato_personalizado');
        checkCustom('natureza_prazo', 'natureza_prazo_personalizado');

        // Timeline Dinâmica
        window.updateTimeline = function() {
            const status = document.getElementById('status').value;
            const timeline = document.getElementById('timeline-processo');
            timeline.style.display = 'flex';
            
            document.getElementById('tl-analise').classList.remove('done');
            document.getElementById('tl-protocolo').classList.remove('done');

            if (['SENDO AVALIADO', 'EM ELABORAÇÃO', 'ANALISADO'].includes(status)) {
                document.getElementById('tl-analise').classList.add('done');
            }
            if (['PROTOCOLADO', 'PROCESSO FINALIZADO'].includes(status)) {
                document.getElementById('tl-analise').classList.add('done');
                document.getElementById('tl-protocolo').classList.add('done');
            }
        };

        // Carregar dados se for edição
        const urlParams = new URLSearchParams(window.location.search);
        const idEdicao = urlParams.get('id');
        if (idEdicao) {
            fetch(`api.php?acao=obter&id=${idEdicao}`)
            .then(r => r.json())
            .then(dados => {
                if(dados.status !== 'erro') {
                    for (const [key, value] of Object.entries(dados)) {
                        const el = document.getElementById(key);
                        if(el) el.value = value;
                    }
                    document.getElementById('cnj-valido').style.display = 'block';
                    updateTimeline();
                }
            });
        }

        // Submissão do Formulário
        document.getElementById('form-cadastro-v2').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());
            data.acao = 'salvar';

            // Ajusta personalizados
            if(data.tipo_ato === 'PERSONALIZADO') data.tipo_ato = data.tipo_ato_personalizado;
            if(data.natureza_prazo === 'PERSONALIZADO') data.natureza_prazo = data.natureza_prazo_personalizado;

            try {
                const response = await fetch('api.php?acao=salvar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if(result.status === 'sucesso') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Processo Gravado!',
                        text: 'Salvo com sucesso no padrão SEEU/SCP 2.0.',
                        background: '#1e293b',
                        color: '#fff'
                    }).then(() => {
                        location.href = 'v2/index.php';
                    });
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Erro', text: error.message, background: '#1e293b', color: '#fff' });
            }
        });
    </script>
</body>
</html>
