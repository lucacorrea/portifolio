<?php include 'includes/header.php'; ?>
<?php
if ($_SESSION['usuario_perfil'] !== 'ADMIN') {
    echo "<script>location.href='index.php';</script>";
    exit();
}
?>

<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>

<header style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1 style="font-size: 2.2rem; font-weight: 800; margin-bottom: 0.5rem;">Configurações e Auditoria</h1>
        <p style="color: var(--text-muted);">Controle de histórico e importação de dados.</p>
    </div>
    
    <div style="display: flex; gap: 0.5rem; background: rgba(0,0,0,0.2); padding: 5px; border-radius: 12px; border: 1px solid var(--border);">
        <button class="btn-premium active" onclick="switchTab('auditoria')" id="tab-auditoria" style="padding: 8px 15px; font-size: 0.8rem;">
            <i class="fas fa-history"></i> Auditoria
        </button>
        <button class="btn-premium" onclick="switchTab('importar')" id="tab-importar" style="padding: 8px 15px; font-size: 0.8rem; background:transparent;">
            <i class="fas fa-file-import"></i> Importar
        </button>
    </div>
</header>

<div id="section-auditoria" class="glass-card" style="padding: 0;">
    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <h2 style="font-size: 1.2rem;">Log de Ações</h2>
        <input type="text" id="filtro-auditoria" placeholder="Pesquisar no log..." style="background: rgba(0,0,0,0.2); border: 1px solid var(--border); border-radius: 8px; padding: 0.5rem 1rem; color: white; outline: none; width: 300px;">
    </div>
    <div style="padding: 1rem; overflow-x: auto;">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Usuário</th>
                    <th>Ação</th>
                    <th>Tabela</th>
                    <th>Detalhes</th>
                </tr>
            </thead>
            <tbody id="lista-auditoria-v2">
                <!-- Dinâmico -->
            </tbody>
        </table>
    </div>
</div>

<div id="section-importar" class="glass-card" style="display:none; max-width: 700px; margin: 0 auto; text-align: center; padding: 3rem;">
    <i class="fas fa-file-excel" style="font-size: 4rem; color: #10b981; margin-bottom: 1.5rem;"></i>
    <h2 style="font-weight: 800; margin-bottom: 1rem;">Importar Planilha</h2>
    <p style="color: var(--text-muted); margin-bottom: 2rem;">Selecione um arquivo Excel (.xlsx) para carregar processos em massa.</p>
    
    <div style="background: rgba(0,0,0,0.2); border: 2px dashed var(--border); padding: 2rem; border-radius: 15px; margin-bottom: 2rem;">
        <input type="file" id="arquivo-planilha" accept=".xlsx, .xls" style="color: var(--text-muted);">
    </div>

    <div class="form-group" style="text-align: left; margin-bottom: 2rem;">
        <label>Tipo de Processos nesta Planilha</label>
        <select id="tipo-processo-import" style="width: 100%;">
            <option value="CIÊNCIA">CIÊNCIA</option>
            <option value="CUMPRIMENTO">CUMPRIMENTO</option>
            <option value="RECURSO - CIÊNCIA">RECURSO - CIÊNCIA</option>
        </select>
    </div>

    <button id="btn-iniciar-import" class="btn-premium" style="width: 100%; justify-content: center; height: 50px;">
        <i class="fas fa-upload"></i> Iniciar Importação
    </button>
</div>

<script>
    let auditData = [];

    async function carregarAuditoria() {
        const resp = await fetch('../api.php?acao=listar_auditoria');
        auditData = await resp.json();
        renderizarAuditoria();
    }

    function renderizarAuditoria() {
        const tbody = document.getElementById('lista-auditoria-v2');
        const query = document.getElementById('filtro-auditoria').value.toLowerCase();
        
        const filtrados = auditData.filter(a => 
            a.usuario_nome.toLowerCase().includes(query) || a.acao.toLowerCase().includes(query)
        );

        tbody.innerHTML = '';
        filtrados.slice(0, 50).forEach(a => {
            const data = new Date(a.data_hora).toLocaleString('pt-BR');
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${data}</td>
                <td style="font-weight:600;">${a.usuario_nome}</td>
                <td><span style="background:rgba(255,255,255,0.1); padding:4px 10px; border-radius:50px; font-size:0.7rem;">${a.acao}</span></td>
                <td>${a.tabela}</td>
                <td><i class="fas fa-info-circle" style="cursor:pointer; color:var(--primary);"></i></td>
            `;
            tbody.appendChild(tr);
        });
    }

    window.switchTab = (tab) => {
        document.querySelectorAll('[id^="section-"]').forEach(s => s.style.display = 'none');
        document.querySelectorAll('[id^="tab-"]').forEach(b => b.style.background = 'transparent');
        
        document.getElementById('section-' + tab).style.display = (tab === 'auditoria' ? 'block' : 'block');
        if(tab === 'importar') {
            document.getElementById('section-auditoria').style.display = 'none';
            document.getElementById('section-importar').style.display = 'block';
        }
        document.getElementById('tab-' + tab).style.background = 'var(--primary)';
    };

    document.getElementById('btn-iniciar-import').onclick = async () => {
        const fileInput = document.getElementById('arquivo-planilha');
        if(!fileInput.files.length) return Swal.fire({ icon: 'warning', title: 'Selecione um arquivo!', background: '#1e293b', color: '#fff' });

        const btn = document.getElementById('btn-iniciar-import');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';

        // Lógica de leitura do Excel (conforme seu sistema original)
        const file = fileInput.files[0];
        const reader = new FileReader();
        reader.onload = async (e) => {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array', cellDates: true });
            const json = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]]);
            
            const tipo = document.getElementById('tipo-processo-import').value;
            json.forEach(item => item.tipo_processo = tipo);

            const resp = await fetch('../api.php?acao=importar_dados', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(json)
            });

            const res = await resp.json();
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-upload"></i> Iniciar Importação';
            
            if(res.status === 'sucesso') {
                Swal.fire({ icon: 'success', title: 'Sucesso!', text: `${res.inseridos} registros importados.`, background: '#1e293b', color: '#fff' });
                carregarAuditoria();
            }
        };
        reader.readAsArrayBuffer(file);
    };

    document.getElementById('filtro-auditoria').oninput = renderizarAuditoria;
    document.addEventListener('DOMContentLoaded', carregarAuditoria);
</script>

<?php include 'includes/footer.php'; ?>
