<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold text-secondary"><i class="fas fa-file-import me-2"></i>Importar NF-e via XML</h5>
            </div>
            <div class="card-body">
                <div id="upload_section">
                    <div class="border-2 border-dashed rounded-4 p-5 text-center bg-light">
                        <i class="fas fa-cloud-upload-alt fs-1 text-muted mb-3"></i>
                        <h4>Arraste o XML da nota ou clique para selecionar</h4>
                        <p class="text-muted">Apenas arquivos .xml são permitidos</p>
                        <input type="file" id="xml_input" class="d-none" accept=".xml" onchange="handleFileUpload(this)">
                        <button class="btn btn-primary btn-lg px-5 mt-3" onclick="document.getElementById('xml_input').click()">
                            Selecionar Arquivo
                        </button>
                    </div>
                </div>

                <div id="preview_section" class="d-none mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">Produtos Identificados no XML</h6>
                        <button class="btn btn-success" onclick="confirmarImportacao()">
                            <i class="fas fa-check-circle me-1"></i> Confirmar Importação
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle" id="xml_table">
                            <thead class="bg-light">
                                <tr>
                                    <th>Cód. Fornecedor</th>
                                    <th>Nome do Produto</th>
                                    <th>NCM</th>
                                    <th class="text-center">Qtd</th>
                                    <th class="text-center">Un</th>
                                    <th class="text-end">V. Unitário</th>
                                    <th class="text-end">V. Total</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let produtosImportar = [];

async function handleFileUpload(input) {
    if (!input.files.length) return;
    
    const formData = new FormData();
    formData.append('xml_file', input.files[0]);

    const res = await fetch('importar_xml.php?action=upload', {
        method: 'POST',
        body: formData
    });

    const result = await res.json();
    if (result.success) {
        produtosImportar = result.produtos;
        renderPreview();
    } else {
        alert('Erro: ' + result.error);
    }
}

function renderPreview() {
    const tbody = document.querySelector('#xml_table tbody');
    tbody.innerHTML = '';
    
    produtosImportar.forEach(p => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${p.codigo}</td>
            <td>${p.nome}</td>
            <td>${p.ncm}</td>
            <td class="text-center">${p.qCom}</td>
            <td class="text-center">${p.uCom}</td>
            <td class="text-end">R$ ${p.vUnCom.toFixed(2).replace('.', ',')}</td>
            <td class="text-end">R$ ${p.vProd.toFixed(2).replace('.', ',')}</td>
        `;
        tbody.appendChild(row);
    });

    document.getElementById('upload_section').classList.add('d-none');
    document.getElementById('preview_section').classList.remove('d-none');
}

async function confirmarImportacao() {
    if (!confirm('Deseja realmente importar estes ' + produtosImportar.length + ' produtos para o estoque?')) return;

    const res = await fetch('importar_xml.php?action=confirmar', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({items: produtosImportar})
    });

    const result = await res.json();
    if (result.success) {
        alert('Importação concluída com sucesso!');
        location.href = 'estoque.php';
    } else {
        alert('Erro ao importar: ' + result.error);
    }
}
</script>
