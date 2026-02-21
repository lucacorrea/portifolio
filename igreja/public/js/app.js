// ========================================
// SISTEMA DE MEMBROS - IGREJA DE DEUS NASCER DE NOVO
// JavaScript Principal
// ========================================

// Configurações globais
const API_BASE = '/api/membros.php';
const RELATORIO_BASE = '/api/relatorio.php';
const ITEMS_POR_PAGINA = 10;

// Estado da aplicação
let estadoApp = {
    paginaAtual: 1,
    membros: [],
    membroAtual: null,
    filtroAtivo: 'nome'
};

// ========================================
// INICIALIZAÇÃO
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    inicializarApp();
    configurarEventos();
});

function inicializarApp() {
    // Carregar página inicial
    navegarPara('dashboard');
}

function configurarEventos() {
    // Navegação
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const pagina = this.dataset.pagina;
            navegarPara(pagina);
            
            // Atualizar ativo
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Formulário de cadastro
    const formCadastro = document.getElementById('formCadastro');
    if (formCadastro) {
        formCadastro.addEventListener('submit', function(e) {
            e.preventDefault();
            salvarMembro();
        });
    }

    // Busca
    const inputBusca = document.getElementById('inputBusca');
    if (inputBusca) {
        inputBusca.addEventListener('keyup', function() {
            if (this.value.length >= 2) {
                buscarMembros(this.value);
            } else if (this.value.length === 0) {
                listarMembros();
            }
        });
    }

    // Filtro de busca
    const selectFiltro = document.getElementById('selectFiltro');
    if (selectFiltro) {
        selectFiltro.addEventListener('change', function() {
            estadoApp.filtroAtivo = this.value;
            const inputBusca = document.getElementById('inputBusca');
            if (inputBusca && inputBusca.value.length >= 2) {
                buscarMembros(inputBusca.value);
            }
        });
    }

    // Botões de ação
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-editar')) {
            const id = e.target.dataset.id;
            editarMembro(id);
        }
        if (e.target.classList.contains('btn-deletar')) {
            const id = e.target.dataset.id;
            if (confirm('Tem certeza que deseja deletar este membro?')) {
                deletarMembro(id);
            }
        }
        if (e.target.classList.contains('btn-visualizar')) {
            const id = e.target.dataset.id;
            visualizarMembro(id);
        }
        if (e.target.classList.contains('btn-relatorio')) {
            const id = e.target.dataset.id;
            gerarRelatoriMembro(id);
        }
    });

    // Fechar modal
    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.modal').classList.remove('active');
        });
    });

    // Fechar modal ao clicar fora
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });
}

// ========================================
// NAVEGAÇÃO
// ========================================

function navegarPara(pagina) {
    // Esconder todas as seções
    document.querySelectorAll('.pagina').forEach(p => {
        p.style.display = 'none';
    });

    // Mostrar seção solicitada
    const secao = document.getElementById('pagina-' + pagina);
    if (secao) {
        secao.style.display = 'block';

        // Carregar dados específicos
        if (pagina === 'membros') {
            listarMembros();
        } else if (pagina === 'dashboard') {
            carregarDashboard();
        }
    }
}

// ========================================
// MEMBROS - CRUD
// ========================================

function listarMembros(pagina = 1) {
    mostrarCarregando('tabelaMembros');

    fetch(`${API_BASE}?acao=listar&pagina=${pagina}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'sucesso') {
                renderizarTabelaMembros(data.dados.membros);
                renderizarPaginacao(data.dados.paginaAtual, data.dados.paginas);
                estadoApp.paginaAtual = pagina;
            } else {
                mostrarAlerta('erro', data.mensagem);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarAlerta('erro', 'Erro ao carregar membros');
        });
}

function renderizarTabelaMembros(membros) {
    const tbody = document.querySelector('#tabelaMembros tbody');
    
    if (!tbody) return;

    if (membros.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">Nenhum membro cadastrado</td></tr>';
        return;
    }

    tbody.innerHTML = membros.map(membro => `
        <tr>
            <td>
                <div style="display: flex; align-items: center; gap: 10px;">
                    ${membro.foto_path ? 
                        `<img src="${membro.foto_path}" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">` :
                        `<div class="avatar" style="background: ${gerarCorAvatar(membro.nome_completo)}">${gerarIniciaisAvatar(membro.nome_completo)}</div>`
                    }
                    <span>${membro.nome_completo}</span>
                </div>
            </td>
            <td>${formatarCPF(membro.cpf || '')}</td>
            <td>${formatarTelefone(membro.telefone || '')}</td>
            <td><span class="badge badge-${membro.tipo_integracao ? membro.tipo_integracao.toLowerCase() : 'primary'}">${membro.tipo_integracao || 'N/A'}</span></td>
            <td>${formatarData(membro.data_integracao || '')}</td>
            <td>
                <div style="display: flex; gap: 5px;">
                    <button class="btn btn-sm btn-primary btn-visualizar" data-id="${membro.id}" title="Visualizar">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-primary btn-editar" data-id="${membro.id}" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger btn-deletar" data-id="${membro.id}" title="Deletar">
                        <i class="fas fa-trash"></i>
                    </button>
                    <button class="btn btn-sm btn-secondary btn-relatorio" data-id="${membro.id}" title="Relatório PDF">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                    <button class="btn btn-sm btn-outline" onclick="imprimirFicha(${membro.id})" title="Imprimir Ficha">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function renderizarPaginacao(paginaAtual, totalPaginas) {
    const container = document.querySelector('.pagination');
    
    if (!container) return;

    let html = '';

    if (paginaAtual > 1) {
        html += `<a href="#" onclick="listarMembros(${paginaAtual - 1}); return false;">← Anterior</a>`;
    }

    for (let i = 1; i <= totalPaginas; i++) {
        if (i === paginaAtual) {
            html += `<span class="active">${i}</span>`;
        } else {
            html += `<a href="#" onclick="listarMembros(${i}); return false;">${i}</a>`;
        }
    }

    if (paginaAtual < totalPaginas) {
        html += `<a href="#" onclick="listarMembros(${paginaAtual + 1}); return false;">Próxima →</a>`;
    }

    container.innerHTML = html;
}

function salvarMembro() {
    const form = document.getElementById('formCadastro');
    const formData = new FormData(form);
    const id = form.dataset.membroId;

    const acao = id ? 'atualizar' : 'criar';
    if (id) formData.append('id', id);

    mostrarCarregando('formCadastro');

    fetch(`${API_BASE}?acao=${acao}`, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'sucesso') {
                mostrarAlerta('sucesso', `Membro ${acao === 'criar' ? 'cadastrado' : 'atualizado'} com sucesso!`);
                form.reset();
                delete form.dataset.membroId;
                document.getElementById('modalCadastro').classList.remove('active');
                listarMembros();
            } else {
                mostrarAlerta('erro', data.mensagem);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarAlerta('erro', 'Erro ao salvar membro');
        });
}

function editarMembro(id) {
    fetch(`${API_BASE}?acao=obter&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'sucesso') {
                const membro = data.dados;
                const form = document.getElementById('formCadastro');
                
                // Preencher formulário
                form.dataset.membroId = id;
                document.getElementById('nome_completo').value = membro.nome_completo;
                document.getElementById('data_nascimento').value = inverterData(membro.data_nascimento);
                document.getElementById('nacionalidade').value = membro.nacionalidade || '';
                document.getElementById('naturalidade').value = membro.naturalidade || '';
                document.getElementById('estado_uf').value = membro.estado_uf || '';
                document.getElementById('sexo').value = membro.sexo || '';
                document.getElementById('tipo_sanguineo').value = membro.tipo_sanguineo || '';
                document.getElementById('escolaridade').value = membro.escolaridade || '';
                document.getElementById('profissao').value = membro.profissao || '';
                document.getElementById('rg').value = membro.rg || '';
                document.getElementById('cpf').value = membro.cpf || '';
                document.getElementById('titulo_eleitor').value = membro.titulo_eleitor || '';
                document.getElementById('ctp').value = membro.ctp || '';
                document.getElementById('cdi').value = membro.cdi || '';
                document.getElementById('filiacao_pai').value = membro.filiacao_pai || '';
                document.getElementById('filiacao_mae').value = membro.filiacao_mae || '';
                document.getElementById('estado_civil').value = membro.estado_civil || '';
                document.getElementById('conjuge').value = membro.conjuge || '';
                document.getElementById('filhos').value = membro.filhos || 0;
                document.getElementById('endereco_rua').value = membro.endereco_rua || '';
                document.getElementById('endereco_numero').value = membro.endereco_numero || '';
                document.getElementById('endereco_bairro').value = membro.endereco_bairro || '';
                document.getElementById('endereco_cep').value = membro.endereco_cep || '';
                document.getElementById('endereco_cidade').value = membro.endereco_cidade || '';
                document.getElementById('endereco_uf').value = membro.endereco_uf || '';
                document.getElementById('telefone').value = membro.telefone || '';
                document.getElementById('tipo_integracao').value = membro.tipo_integracao || '';
                document.getElementById('data_integracao').value = inverterData(membro.data_integracao);
                document.getElementById('batismo_aguas').value = membro.batismo_aguas || '';
                document.getElementById('batismo_espirito_santo').value = membro.batismo_espirito_santo || '';
                document.getElementById('procedencia').value = membro.procedencia || '';
                document.getElementById('congregacao').value = membro.congregacao || '';
                document.getElementById('area').value = membro.area || '';
                document.getElementById('nucleo').value = membro.nucleo || '';

                // Abrir modal
                document.getElementById('modalCadastro').classList.add('active');
                document.querySelector('.modal-title').textContent = 'Editar Membro';
            } else {
                mostrarAlerta('erro', data.mensagem);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarAlerta('erro', 'Erro ao carregar dados do membro');
        });
}

function visualizarMembro(id) {
    fetch(`${API_BASE}?acao=obter&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'sucesso') {
                const membro = data.dados;
                renderizarVisualizacaoMembro(membro);
                document.getElementById('modalVisualizacao').classList.add('active');
            } else {
                mostrarAlerta('erro', data.mensagem);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarAlerta('erro', 'Erro ao carregar dados do membro');
        });
}

function renderizarVisualizacaoMembro(membro) {
    const container = document.getElementById('conteudoVisualizacao');
    
    if (!container) return;

    const idade = calcularIdade(membro.data_nascimento);
    
    container.innerHTML = `
        <div style="text-align: center; margin-bottom: 20px;">
            ${membro.foto_path ? 
                `<img src="${membro.foto_path}" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--secondary);">` :
                `<div class="avatar avatar-lg" style="background: ${gerarCorAvatar(membro.nome_completo)}; margin: 0 auto;">${gerarIniciaisAvatar(membro.nome_completo)}</div>`
            }
            <h3 style="margin-top: 15px;">${membro.nome_completo}</h3>
            <p style="color: var(--text-secondary);">${membro.profissao || 'Profissão não informada'}</p>
        </div>

        <div class="form-section">
            <div class="form-section-title">Dados Pessoais</div>
            <div class="form-row">
                <div>
                    <strong>CPF:</strong><br>
                    ${formatarCPF(membro.cpf || '')}
                </div>
                <div>
                    <strong>Data de Nascimento:</strong><br>
                    ${formatarData(membro.data_nascimento || '')} ${idade ? `(${idade} anos)` : ''}
                </div>
                <div>
                    <strong>Sexo:</strong><br>
                    ${membro.sexo === 'M' ? 'Masculino' : membro.sexo === 'F' ? 'Feminino' : 'Não informado'}
                </div>
                <div>
                    <strong>Estado Civil:</strong><br>
                    ${membro.estado_civil || 'Não informado'}
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-title">Endereço</div>
            <div>
                <strong>${membro.endereco_rua || ''}, ${membro.endereco_numero || ''}</strong><br>
                ${membro.endereco_bairro || ''} - ${membro.endereco_cidade || ''}, ${membro.endereco_uf || ''}<br>
                CEP: ${formatarCEP(membro.endereco_cep || '')}<br>
                Telefone: ${formatarTelefone(membro.telefone || '')}
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-title">Dados Eclesiásticos</div>
            <div class="form-row">
                <div>
                    <strong>Tipo de Integração:</strong><br>
                    <span class="badge badge-${membro.tipo_integracao ? membro.tipo_integracao.toLowerCase() : 'primary'}">${membro.tipo_integracao || 'N/A'}</span>
                </div>
                <div>
                    <strong>Data de Integração:</strong><br>
                    ${formatarData(membro.data_integracao || '')}
                </div>
                <div>
                    <strong>Congregação:</strong><br>
                    ${membro.congregacao || 'Não informado'}
                </div>
            </div>
        </div>
    `;
}

function deletarMembro(id) {
    mostrarCarregando('tabelaMembros');

    fetch(`${API_BASE}?acao=deletar`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `id=${id}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'sucesso') {
                mostrarAlerta('sucesso', 'Membro deletado com sucesso!');
                listarMembros();
            } else {
                mostrarAlerta('erro', data.mensagem);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarAlerta('erro', 'Erro ao deletar membro');
        });
}

function buscarMembros(termo) {
    mostrarCarregando('tabelaMembros');

    fetch(`${API_BASE}?acao=buscar&termo=${encodeURIComponent(termo)}&filtro=${estadoApp.filtroAtivo}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'sucesso') {
                renderizarTabelaMembros(data.dados);
                document.querySelector('.pagination').innerHTML = '';
            } else {
                mostrarAlerta('erro', data.mensagem);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarAlerta('erro', 'Erro ao buscar membros');
        });
}

// ========================================
// DASHBOARD
// ========================================

function carregarDashboard() {
    fetch(`${API_BASE}?acao=estatisticas`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'sucesso') {
                renderizarDashboard(data.dados);
            } else {
                mostrarAlerta('erro', data.mensagem);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarAlerta('erro', 'Erro ao carregar estatísticas');
        });
}

function renderizarDashboard(dados) {
    // Atualizar cards de estatísticas
    document.getElementById('totalMembros').textContent = dados.total;

    // Gráfico de tipo de integração
    if (dados.porTipo.length > 0) {
        criarGraficoTipo(dados.porTipo);
    }

    // Gráfico de sexo
    if (dados.porSexo.length > 0) {
        criarGraficoSexo(dados.porSexo);
    }

    // Gráfico de estado civil
    if (dados.porEstadoCivil.length > 0) {
        criarGraficoEstadoCivil(dados.porEstadoCivil);
    }

    // Gráfico de faixa etária
    if (dados.porFaixaEtaria.length > 0) {
        criarGraficoFaixaEtaria(dados.porFaixaEtaria);
    }
}

function criarGraficoTipo(dados) {
    const ctx = document.getElementById('graficoTipo');
    if (!ctx) return;

    const labels = dados.map(d => d.tipo_integracao);
    const values = dados.map(d => d.quantidade);
    const cores = ['#1a2e4a', '#c9a84c', '#2d7d6f', '#f57c00'];

    const chart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: cores,
                borderColor: 'white',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

function criarGraficoSexo(dados) {
    const ctx = document.getElementById('graficoSexo');
    if (!ctx) return;

    const labels = dados.map(d => d.sexo === 'M' ? 'Masculino' : 'Feminino');
    const values = dados.map(d => d.quantidade);

    const chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Quantidade',
                data: values,
                backgroundColor: ['#1976d2', '#d32f2f'],
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

function criarGraficoEstadoCivil(dados) {
    const ctx = document.getElementById('graficoEstadoCivil');
    if (!ctx) return;

    const labels = dados.map(d => d.estado_civil);
    const values = dados.map(d => d.quantidade);

    const chart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: ['#2d7d6f', '#1a2e4a', '#c9a84c', '#f57c00'],
                borderColor: 'white',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

function criarGraficoFaixaEtaria(dados) {
    const ctx = document.getElementById('graficoFaixaEtaria');
    if (!ctx) return;

    const labels = dados.map(d => d.faixa_etaria);
    const values = dados.map(d => d.quantidade);

    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Membros',
                data: values,
                borderColor: '#1a2e4a',
                backgroundColor: 'rgba(26, 46, 74, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#c9a84c',
                pointBorderColor: '#1a2e4a',
                pointBorderWidth: 2,
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

// ========================================
// RELATÓRIOS
// ========================================

function gerarRelatoriMembro(id) {
    window.open(`${RELATORIO_BASE}?acao=membro&id=${id}`, '_blank');
}

function gerarRelatorioTodos() {
    window.open(`${RELATORIO_BASE}?acao=todos`, '_blank');
}

function gerarRelatorioEstatisticas() {
    window.open(`${RELATORIO_BASE}?acao=estatisticas`, '_blank');
}

// ========================================
// UTILITÁRIOS
// ========================================

function formatarCPF(cpf) {
    if (!cpf) return '';
    cpf = cpf.replace(/\D/g, '');
    return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
}

function formatarTelefone(telefone) {
    if (!telefone) return '';
    telefone = telefone.replace(/\D/g, '');
    
    if (telefone.length === 10) {
        return telefone.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    } else if (telefone.length === 11) {
        return telefone.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    }
    
    return telefone;
}

function formatarCEP(cep) {
    if (!cep) return '';
    cep = cep.replace(/\D/g, '');
    return cep.replace(/(\d{5})(\d{3})/, '$1-$2');
}

function formatarData(data) {
    if (!data) return '';
    const [ano, mes, dia] = data.split('-');
    return `${dia}/${mes}/${ano}`;
}

function inverterData(data) {
    if (!data) return '';
    const [dia, mes, ano] = data.split('/');
    return `${ano}-${mes}-${dia}`;
}

function calcularIdade(dataNascimento) {
    if (!dataNascimento) return null;
    
    const hoje = new Date();
    const nascimento = new Date(dataNascimento);
    let idade = hoje.getFullYear() - nascimento.getFullYear();
    const mes = hoje.getMonth() - nascimento.getMonth();
    
    if (mes < 0 || (mes === 0 && hoje.getDate() < nascimento.getDate())) {
        idade--;
    }
    
    return idade;
}

function gerarIniciaisAvatar(nome) {
    const partes = nome.trim().split(' ');
    let iniciais = '';
    
    if (partes.length >= 2) {
        iniciais = (partes[0][0] + partes[partes.length - 1][0]).toUpperCase();
    } else {
        iniciais = nome.substring(0, 2).toUpperCase();
    }
    
    return iniciais;
}

function gerarCorAvatar(nome) {
    const cores = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E2'];
    let hash = 0;
    
    for (let i = 0; i < nome.length; i++) {
        hash = nome.charCodeAt(i) + ((hash << 5) - hash);
    }
    
    const indice = Math.abs(hash) % cores.length;
    return cores[indice];
}

function mostrarAlerta(tipo, mensagem) {
    const container = document.getElementById('alertas');
    
    if (!container) return;

    const alert = document.createElement('div');
    alert.className = `alert alert-${tipo}`;
    alert.innerHTML = `
        <i class="fas fa-${tipo === 'sucesso' ? 'check-circle' : tipo === 'erro' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${mensagem}</span>
    `;

    container.appendChild(alert);

    setTimeout(() => {
        alert.remove();
    }, 5000);
}

function mostrarCarregando(elementoId) {
    const elemento = document.getElementById(elementoId);
    if (elemento) {
        elemento.innerHTML = '<div class="text-center"><div class="loading"></div></div>';
    }
}

// Função para abrir modal de novo cadastro
function abrirModalCadastro() {
    const form = document.getElementById('formCadastro');
    form.reset();
    delete form.dataset.membroId;
    document.querySelector('.modal-title').textContent = 'Novo Membro';
    document.getElementById('modalCadastro').classList.add('active');
}

// Funcao para imprimir ficha de membro
function imprimirFicha(id) {
    window.open('ficha-impressao.php?id=' + id, '_blank');
}
