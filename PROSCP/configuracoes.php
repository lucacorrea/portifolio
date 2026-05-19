<?php
ob_start();
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'ADMIN') {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - SCP</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
    <style>
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 1rem;
        }
        .tab-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 600;
            color: #64748b;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }
        .tab-btn:hover {
            color: var(--primary);
        }
        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            border-radius: 0;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>

<header class="navbar">
    <div class="logo">
        <i class="fas fa-balance-scale"></i>
        <span>SCP PGM</span>
    </div>
    <nav class="nav-links">
        <a href="index.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
        <?php if ($_SESSION['usuario_perfil'] !== 'ACESSORES'): ?>
        <a href="cadastro.php" class="nav-link"><i class="fas fa-plus-circle"></i> Novo</a>
        <?php endif; ?>
        <a href="prazos.php" class="nav-link"><i class="fas fa-clock"></i> Prazos</a>
        <a href="tipos.php" class="nav-link"><i class="fas fa-layer-group"></i> Tipos</a>
        <a href="relatorios.php" class="nav-link"><i class="fas fa-chart-line"></i> Relatórios</a>
        <?php if ($_SESSION['usuario_perfil'] === 'ADMIN'): ?>
        <a href="usuarios.php" class="nav-link"><i class="fas fa-users"></i> Usuários</a>
        <a href="configuracoes.php" class="nav-link active"><i class="fas fa-cog"></i></a>
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
            <h1>Configurações e Auditoria</h1>
            <p>Log de alterações e segurança do sistema.</p>
        </div>
        <div class="user-profile" style="display: flex; align-items: center; gap: 1rem; background: var(--glass-bg); padding: 0.5rem 1rem; border-radius: 50px; border: 1px solid var(--glass-border);">
            <i class="fas fa-user-circle" style="font-size: 1.5rem; color: var(--primary);"></i>
            <span style="font-weight: 600;"><?php echo $_SESSION['usuario_nome']; ?></span>
        </div>
    </header>

    <div class="tabs" style="margin-top: 2rem;">
        <button class="tab-btn active" onclick="switchTab('auditoria')"><i class="fas fa-history"></i> Auditoria</button>
        <button class="tab-btn" onclick="switchTab('importar')"><i class="fas fa-file-import"></i> Importar Planilha</button>
    </div>

    <div id="tab-auditoria" class="tab-content active">
        <div class="data-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap;">
                <div>
                    <h2>Log de Auditoria</h2>
                    <p style="color: var(--text-muted);">Histórico das últimas 100 ações realizadas.</p>
                </div>
                <div style="position: relative; flex: 1; max-width: 300px;">
                    <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="text" id="filtro-auditoria" placeholder="Buscar por usuário, ação ou tabela..." style="padding-left: 2.5rem; font-size: 0.85rem;" oninput="filtrarAuditoria()">
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table style="width: 100%; font-size: 0.9rem;">
                    <thead>
                        <tr>
                            <th>DATA/HORA</th>
                            <th>USUÁRIO</th>
                            <th>AÇÃO</th>
                            <th>TABELA</th>
                            <th>ID REG.</th>
                            <th>MODIFICAÇÕES</th>
                        </tr>
                    </thead>
                    <tbody id="lista-auditoria">
                        <!-- Dinâmico -->
                    </tbody>
                </table>
            </div>
            <div id="paginacao-auditoria" class="pagination"></div>
        </div>
    </div>

    <div id="tab-importar" class="tab-content">
        <div class="data-section">
            <div style="max-width: 600px; margin: 0 auto; padding: 2rem 0;">
                <div style="text-align: center; margin-bottom: 2rem;">
                    <i class="fas fa-file-excel" style="font-size: 3rem; color: #16a34a; margin-bottom: 1rem;"></i>
                    <h2>Importar Dados da Planilha</h2>
                    <p style="color: var(--text-muted);">Suporta arquivos Excel (.xlsx, .xls) e CSV.</p>
                </div>

                <div style="background: #f8fafc; padding: 1.5rem; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 2rem;">
                    <h3 style="font-size: 1rem; margin-bottom: 0.5rem;"><i class="fas fa-info-circle"></i> Instruções:</h3>
                    <ol style="padding-left: 1.25rem; font-size: 0.9rem; line-height: 1.6; color: #475569;">
                        <li>Selecione seu arquivo <b>Excel (.xlsx)</b> ou <b>CSV</b>.</li>
                        <li>O sistema identifica as colunas pelo nome (ex: Nº do Processo, Data da Ciência).</li>
                        <li>Processos já cadastrados (mesmo número e data) serão pulados automaticamente.</li>
                    </ol>
                </div>

                <form id="form-importar" onsubmit="processarImportacao(event)">
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label for="tipo-processo-import" style="font-weight: 600; font-size: 0.95rem;">Que tipo de processos há nesta planilha?</label>
                        <select id="tipo-processo-import" required style="width: 100%; padding: 0.8rem; border: 1px solid var(--border); border-radius: 8px; background: white; margin-top: 0.5rem; font-size: 1rem;">
                            <option value="CIÊNCIA">CIÊNCIA</option>
                            <option value="CUMPRIMENTO">CUMPRIMENTO</option>
                            <option value="RECURSO - CIÊNCIA">RECURSO - CIÊNCIA</option>
                            <option value="RECURSO - CUMPRIMENTO">RECURSO - CUMPRIMENTO</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="arquivo-planilha" style="font-weight: 600; font-size: 0.95rem;">Arquivo da Planilha</label>
                        <input type="file" id="arquivo-planilha" accept=".csv, .xlsx, .xls" required style="padding: 1rem; border: 2px dashed var(--border); background: white; width: 100%; margin-top: 0.5rem; border-radius: 8px;">
                    </div>
                    <div id="resultado-importacao" style="margin-top: 1rem; display: none;"></div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem; margin-top: 1rem;" id="btn-importar-planilha">
                        <i class="fas fa-upload"></i> Iniciar Importação
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="modal-auditoria" class="modal-overlay" onclick="if(event.target === this) fecharModalAuditoria()">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2>Detalhes da Alteração</h2>
                <button onclick="fecharModalAuditoria()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
            </div>
            <div id="conteudo-auditoria" style="max-height: 450px; overflow-y: auto;">
                <!-- Diferenças serão inseridas aqui -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="fecharModalAuditoria()">Fechar</button>
            </div>
        </div>
    </div>
</main>

<script async>
    let auditData = [];
    let filteredData = [];
    let currentPage = 1;
    const itemsPerPage = 10;

    function switchTab(tab) {
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        
        document.getElementById('tab-' + tab).classList.add('active');
        event.currentTarget.classList.add('active');
    }

    async function processarImportacao(e) {
        e.preventDefault();
        const fileInput = document.getElementById('arquivo-planilha');
        const btn = document.getElementById('btn-importar-planilha');
        const resDiv = document.getElementById('resultado-importacao');

        if (!fileInput.files.length) return;

        const file = fileInput.files[0];
        const isExcel = file.name.endsWith('.xlsx') || file.name.endsWith('.xls');

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Lendo arquivo...';
        resDiv.style.display = 'none';

        const tipoProcessoSelecionado = document.getElementById('tipo-processo-import').value;

        try {
            const data = await lerArquivo(file);
            data.forEach(item => item.tipo_processo = tipoProcessoSelecionado);
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando para o sistema...';

            const resp = await fetch('api.php?acao=importar_dados', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await resp.json();

            if (result.status === 'sucesso') {
                resDiv.style.display = 'block';
                resDiv.innerHTML = `
                    <div style="background: #dcfce7; color: #15803d; padding: 1rem; border-radius: 8px; border: 1px solid #bbf7d0; font-size: 0.9rem;">
                        <p><b>Sucesso!</b> ${result.inseridos} registros importados.</p>
                        <p style="font-size: 0.8rem;">Pulados (duplicados): ${result.pula}</p>
                    </div>
                `;
                carregarAuditoria();
            } else {
                throw new Error(result.message || 'Erro ao importar');
            }
        } catch (err) {
            resDiv.style.display = 'block';
            resDiv.innerHTML = `<div style="background: #fee2e2; color: #b91c1c; padding: 1rem; border-radius: 8px; border: 1px solid #fecaca; font-size: 0.9rem;">Erro: ${err.message}</div>`;
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-upload"></i> Iniciar Importação';
        }
    }

    function lerArquivo(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                try {
                    const data = new Uint8Array(e.target.result);
                    // cellDates: true converte datas do Excel diretamente para objetos Date do JS
                    const workbook = XLSX.read(data, { type: 'array', cellDates: true });
                    let allJson = [];
                    workbook.SheetNames.forEach(sheetName => {
                        const worksheet = workbook.Sheets[sheetName];
                        const json = XLSX.utils.sheet_to_json(worksheet, { defval: "" });
                        allJson = allJson.concat(json);
                    });

                    const finalData = allJson.map(row => {
                        const mapped = {};
                        Object.keys(row).forEach(key => {
                            const k = key.trim().toUpperCase();
                            const val = row[key];
                            
                            if (k.includes('Nº DO PROCESSO') || k.includes('N° DO PROCESSO') || (k.includes('N') && k.includes('PROCESSO'))) {
                                mapped.numero = val;
                            } else if (k === 'PROCESSO') {
                                if (!mapped.numero) mapped.numero = val;
                                else if (!mapped.tipo_ato) mapped.tipo_ato = val;
                            }
                            
                            if (k.includes('TIPO DE ATO')) mapped.tipo_ato = val;
                            if (k.includes('NATUREZA')) mapped.natureza = val;
                            if (k.includes('MANIFESTAÇÃO') && k.includes('TIPO')) mapped.tipo_manifestacao = val;
                            if (k.includes('REVEL')) mapped.revelia = val;
                            if (k.includes('ENVIO') || k.includes('INTIMAÇÃO')) mapped.data_envio = formatarDataParaISO(val);
                            if (k.includes('CIÊNCIA')) mapped.data_ciencia = formatarDataParaISO(val);
                            if (k.includes('CONTAGEM')) mapped.tipo_contagem = val;
                            if (k.includes('FINAL DO PRAZO') || k.includes('FINAL')) mapped.final_prazo = formatarDataParaISO(val);
                            if (k.includes('CRÍTICO')) mapped.prazo_critico = val;
                            if (k.includes('ANALISADOR')) mapped.analisador = val;
                            if (k.includes('STATUS')) mapped.status = val;
                            if (k.includes('PROTOCOLO')) mapped.data_protocolo = formatarDataParaISO(val);
                            if (k.includes('OBSERVAÇÕES') || k.includes('OBSERVACOES')) mapped.observacoes = val;
                        });
                        return mapped;
                    });
                    resolve(finalData);
                } catch (err) {
                    reject(err);
                }
            };
            reader.onerror = reject;
            reader.readAsArrayBuffer(file);
        });
    }

    function formatarDataParaISO(val) {
        if (!val) return '';
        
        // Se já for um objeto Date (graças ao cellDates: true)
        if (val instanceof Date && !isNaN(val)) {
            return val.toISOString().split('T')[0];
        }

        let namePart = '';
        let datePart = String(val);

        if (datePart.includes(' - ')) {
            const split = datePart.split(' - ');
            datePart = split[0].trim();
            namePart = ' - ' + split[1].trim();
        }
        
        // Se for string no formato DD/MM/YYYY
        if (datePart.includes('/')) {
            const parts = datePart.trim().split('/');
            if (parts.length === 3) {
                const ano = parts[2].length === 2 ? '20' + parts[2] : parts[2];
                return `${ano}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}${namePart}`;
            }
        }

        // Se for string no formato YYYY-MM-DD
        if (/^\d{4}-\d{2}-\d{2}/.test(datePart)) {
            return datePart.split(' ')[0] + namePart;
        }
        
        return val;
    }

    async function carregarAuditoria() {
        const resp = await fetch('api.php?acao=listar_auditoria');
        auditData = await resp.json();
        filteredData = [...auditData];
        renderizarAuditoria();
    }

    function filtrarAuditoria() {
        const query = document.getElementById('filtro-auditoria').value.toLowerCase();
        filteredData = auditData.filter(a => 
            a.usuario_nome.toLowerCase().includes(query) ||
            a.acao.toLowerCase().includes(query) ||
            a.tabela.toLowerCase().includes(query) ||
            a.registro_id.toString().includes(query)
        );
        currentPage = 1;
        renderizarAuditoria();
    }

    function renderizarAuditoria() {
        const tbody = document.getElementById('lista-auditoria');
        tbody.innerHTML = '';
        
        const start = (currentPage - 1) * itemsPerPage;
        const end = start + itemsPerPage;
        const paginatedItems = filteredData.slice(start, end);

        if (paginatedItems.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 2rem; color: var(--text-muted);">Nenhum registro encontrado.</td></tr>';
            document.getElementById('paginacao-auditoria').innerHTML = '';
            return;
        }

        paginatedItems.forEach(a => {
            const tr = document.createElement('tr');
            const data = new Date(a.data_hora).toLocaleString('pt-BR');
            
            let btnHtml = '-';
            if (a.acao !== 'DELETE') {
                btnHtml = `<button class="btn-quick" onclick="abrirModalAuditoria(${a.id})" title="Ver Detalhes"><i class="fas fa-eye"></i></button>`;
            } else {
                btnHtml = `<button class="btn-quick" onclick="abrirModalAuditoria(${a.id})" title="Ver Registro Apagado" style="color:#ef4444;"><i class="fas fa-history"></i></button>`;
            }

            tr.innerHTML = `
                <td>${data}</td>
                <td style="font-weight:600;">${a.usuario_nome}</td>
                <td><span class="badge" style="background:${a.acao === 'DELETE' ? '#fee2e2' : a.acao === 'INSERT' ? '#dcfce7' : '#dbeafe'}; color:${a.acao === 'DELETE' ? '#b91c1c' : a.acao === 'INSERT' ? '#15803d' : '#1e40af'}; font-size:0.75rem;">${a.acao}</span></td>
                <td>${a.tabela}</td>
                <td>${a.registro_id}</td>
                <td>${btnHtml}</td>
            `;
            tbody.appendChild(tr);
        });

        renderizarPaginacao();
    }

    function renderizarPaginacao() {
        const totalPages = Math.ceil(filteredData.length / itemsPerPage);
        const container = document.getElementById('paginacao-auditoria');
        container.innerHTML = '';

        if (totalPages <= 1) return;

        // Botão Anterior
        const btnPrev = document.createElement('button');
        btnPrev.className = 'page-btn';
        btnPrev.innerHTML = '<i class="fas fa-chevron-left"></i>';
        btnPrev.disabled = currentPage === 1;
        btnPrev.onclick = () => { currentPage--; renderizarAuditoria(); };
        container.appendChild(btnPrev);

        let botoes = [];
        if (totalPages <= 7) {
            for (let i = 1; i <= totalPages; i++) botoes.push(i);
        } else {
            if (currentPage <= 4) botoes = [1, 2, 3, 4, 5, '...', totalPages];
            else if (currentPage >= totalPages - 3) botoes = [1, '...', totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
            else botoes = [1, '...', currentPage - 1, currentPage, currentPage + 1, '...', totalPages];
        }

        botoes.forEach(item => {
            if (item === '...') {
                const span = document.createElement('span');
                span.textContent = '...';
                span.style.padding = '0 0.5rem';
                span.style.color = 'var(--text-muted)';
                span.style.fontWeight = 'bold';
                container.appendChild(span);
            } else {
                const btnPage = document.createElement('button');
                btnPage.className = `page-btn ${item === currentPage ? 'active' : ''}`;
                btnPage.textContent = item;
                btnPage.onclick = () => { currentPage = item; renderizarAuditoria(); };
                container.appendChild(btnPage);
            }
        });

        // Botão Próximo
        const btnNext = document.createElement('button');
        btnNext.className = 'page-btn';
        btnNext.innerHTML = '<i class="fas fa-chevron-right"></i>';
        btnNext.disabled = currentPage === totalPages;
        btnNext.onclick = () => { currentPage++; renderizarAuditoria(); };
        container.appendChild(btnNext);
    }

    function abrirModalAuditoria(id) {
        const item = auditData.find(a => a.id === id);
        if (!item) return;

        const conteiner = document.getElementById('conteudo-auditoria');
        conteiner.innerHTML = '';

        const ant = JSON.parse(item.dados_anteriores || '{}');
        const novos = JSON.parse(item.dados_novos || '{}');
        
        const allKeys = new Set([...Object.keys(ant), ...Object.keys(novos)]);
        let html = '<div class="diff-list">';

        if (item.acao === 'DELETE') {
            html += '<p style="margin-bottom:1rem; color:#ef4444; font-weight:600;">Atenção: Este registro foi permanentemente removido.</p>';
        }

        allKeys.forEach(key => {
            // Ignorar chaves técnicas se desejar, ou formatar nomes amigáveis
            if (key === 'id' || key === 'data_criacao') return;

            const valAnt = ant[key] || '-';
            const valNovo = novos[key] || '-';

            if (item.acao === 'UPDATE' && valAnt == valNovo) return; // Só mostra o que mudou no UPDATE

            html += `
                <div class="diff-item">
                    <span class="diff-label">${key.replace('_', ' ')}</span>
                    <div class="diff-change">
                        ${item.acao !== 'INSERT' ? `<span class="diff-old">${valAnt}</span> <i class="fas fa-arrow-right" style="color:var(--text-muted); font-size:0.8rem;"></i>` : ''}
                        <span class="diff-new">${valNovo}</span>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        conteiner.innerHTML = html;
        document.getElementById('modal-auditoria').classList.add('active');
    }

    function fecharModalAuditoria() {
        document.getElementById('modal-auditoria').classList.remove('active');
    }

    document.addEventListener('DOMContentLoaded', carregarAuditoria);
</script>
<script>
    window.userPerfil = '<?php echo $_SESSION['usuario_perfil'] ?? 'ANALISADOR'; ?>';
</script>
<script src="assets/js/script.js?v=65"></script>
</body>
</html>
