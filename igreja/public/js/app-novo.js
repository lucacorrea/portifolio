/**
 * Sistema de Membros - JavaScript Moderno
 * Igreja de Deus Nascer de Novo
 */

// ================================================
// 1. CONFIGURAÇÃO GLOBAL
// ================================================

const APP = {
    apiUrl: 'api/membros.php',
    currentPage: 1,
    itemsPerPage: 10,
    
    init() {
        this.setupEventListeners();
        this.loadInitialData();
    },
    
    setupEventListeners() {
        // Fechar modal ao clicar fora
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.closeModal(modal.id);
                }
            });
        });
        
        // Busca em tempo real
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.buscarMembros(e.target.value);
            });
        }
    },
    
    loadInitialData() {
        this.carregarMembros();
        this.carregarEstatisticas();
    }
};

// ================================================
// 2. MODAIS
// ================================================

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
    }
}

// ================================================
// 3. MEMBROS
// ================================================

async function carregarMembros(pagina = 1) {
    try {
        const response = await fetch(`${APP.apiUrl}?acao=listar&pagina=${pagina}&limite=${APP.itemsPerPage}`);
        const data = await response.json();
        
        if (data.sucesso) {
            renderizarMembros(data.membros);
            renderizarPaginacao(data.total, pagina);
        } else {
            showAlert('Erro ao carregar membros', 'danger');
        }
    } catch (error) {
        console.error('Erro:', error);
        showAlert('Erro ao carregar membros', 'danger');
    }
}

function renderizarMembros(membros) {
    const container = document.getElementById('membrosContainer');
    if (!container) return;
    
    if (membros.length === 0) {
        container.innerHTML = '<p class="text-center text-muted p-4">Nenhum membro cadastrado</p>';
        return;
    }
    
    container.innerHTML = membros.map(membro => `
        <div class="card">
            <div class="card-header">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #1e3a5f 0%, #d4af37 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 1.5rem;">
                        ${membro.nome_completo.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <h4 style="margin: 0;">${membro.nome_completo}</h4>
                        <p style="margin: 0.25rem 0; color: #7f8c8d;">
                            <span class="badge badge-primary">${membro.tipo_integracao || 'Não definido'}</span>
                        </p>
                    </div>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-sm" onclick="visualizarMembro(${membro.id})" title="Visualizar">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm" onclick="editarMembro(${membro.id})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm" onclick="imprimirFicha(${membro.id})" title="Imprimir">
                        <i class="fas fa-print"></i>
                    </button>
                    <button class="btn btn-sm" onclick="deletarMembro(${membro.id})" title="Deletar">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <p style="margin: 0; font-size: 0.85rem; color: #7f8c8d;">CPF</p>
                        <p style="margin: 0; font-weight: 600;">${membro.cpf || 'Não informado'}</p>
                    </div>
                    <div>
                        <p style="margin: 0; font-size: 0.85rem; color: #7f8c8d;">Telefone</p>
                        <p style="margin: 0; font-weight: 600;">${membro.telefone || 'Não informado'}</p>
                    </div>
                    <div>
                        <p style="margin: 0; font-size: 0.85rem; color: #7f8c8d;">Cadastro</p>
                        <p style="margin: 0; font-weight: 600;">${formatarData(membro.data_cadastro)}</p>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

async function buscarMembros(termo) {
    if (!termo) {
        carregarMembros();
        return;
    }
    
    try {
        const response = await fetch(`${APP.apiUrl}?acao=buscar&termo=${encodeURIComponent(termo)}`);
        const data = await response.json();
        
        if (data.sucesso) {
            renderizarMembros(data.membros);
        }
    } catch (error) {
        console.error('Erro:', error);
    }
}

async function visualizarMembro(id) {
    try {
        const response = await fetch(`${APP.apiUrl}?acao=obter&id=${id}`);
        const data = await response.json();
        
        if (data.sucesso) {
            const membro = data.membro;
            const modal = document.getElementById('modalVisualizar');
            
            if (modal) {
                modal.innerHTML = `
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title">${membro.nome_completo}</h2>
                            <button class="modal-close" onclick="closeModal('modalVisualizar')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                                <div>
                                    <h5 style="color: #1e3a5f; margin-bottom: 1rem;">Dados Pessoais</h5>
                                    <p><strong>Nome:</strong> ${membro.nome_completo}</p>
                                    <p><strong>CPF:</strong> ${membro.cpf || 'Não informado'}</p>
                                    <p><strong>Data Nascimento:</strong> ${formatarData(membro.data_nascimento)}</p>
                                    <p><strong>Sexo:</strong> ${membro.sexo === 'M' ? 'Masculino' : 'Feminino'}</p>
                                </div>
                                <div>
                                    <h5 style="color: #1e3a5f; margin-bottom: 1rem;">Contato</h5>
                                    <p><strong>Telefone:</strong> ${membro.telefone || 'Não informado'}</p>
                                    <p><strong>Cidade:</strong> ${membro.endereco_cidade || 'Não informado'}</p>
                                    <p><strong>Bairro:</strong> ${membro.endereco_bairro || 'Não informado'}</p>
                                </div>
                                <div>
                                    <h5 style="color: #1e3a5f; margin-bottom: 1rem;">Dados Eclesiásticos</h5>
                                    <p><strong>Tipo:</strong> <span class="badge badge-primary">${membro.tipo_integracao || 'Não definido'}</span></p>
                                    <p><strong>Data:</strong> ${formatarData(membro.data_integracao)}</p>
                                    <p><strong>Congregação:</strong> ${membro.congregacao || 'Não informado'}</p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline" onclick="closeModal('modalVisualizar')">Fechar</button>
                            <button class="btn btn-primary" onclick="editarMembro(${membro.id})">Editar</button>
                        </div>
                    </div>
                `;
                openModal('modalVisualizar');
            }
        }
    } catch (error) {
        console.error('Erro:', error);
        showAlert('Erro ao carregar dados do membro', 'danger');
    }
}

async function deletarMembro(id) {
    if (!confirm('Tem certeza que deseja deletar este membro?')) {
        return;
    }
    
    try {
        const response = await fetch(`${APP.apiUrl}?acao=deletar&id=${id}`, {
            method: 'DELETE'
        });
        const data = await response.json();
        
        if (data.sucesso) {
            showAlert('Membro deletado com sucesso', 'success');
            carregarMembros();
        } else {
            showAlert('Erro ao deletar membro', 'danger');
        }
    } catch (error) {
        console.error('Erro:', error);
        showAlert('Erro ao deletar membro', 'danger');
    }
}

function imprimirFicha(id) {
    window.open(`ficha-impressao.php?id=${id}`, '_blank');
}

function editarMembro(id) {
    window.location.href = `editar-membro.php?id=${id}`;
}

// ================================================
// 4. FORMULÁRIOS
// ================================================

async function salvarMembro() {
    const form = document.getElementById('formCadastro');
    if (!form) return;
    
    const formData = new FormData(form);
    
    try {
        const response = await fetch(`${APP.apiUrl}?acao=criar`, {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.sucesso) {
            showAlert('Membro cadastrado com sucesso!', 'success');
            closeModal('modalCadastro');
            form.reset();
            carregarMembros();
        } else {
            showAlert('Erro: ' + data.mensagem, 'danger');
        }
    } catch (error) {
        console.error('Erro:', error);
        showAlert('Erro ao salvar membro', 'danger');
    }
}

// ================================================
// 5. ESTATÍSTICAS
// ================================================

async function carregarEstatisticas() {
    try {
        const response = await fetch(`${APP.apiUrl}?acao=estatisticas`);
        const data = await response.json();
        
        if (data.sucesso) {
            renderizarEstatisticas(data);
        }
    } catch (error) {
        console.error('Erro:', error);
    }
}

function renderizarEstatisticas(data) {
    // Implementar renderização de estatísticas
    console.log('Estatísticas:', data);
}

// ================================================
// 6. PAGINAÇÃO
// ================================================

function renderizarPaginacao(total, paginaAtual) {
    const totalPaginas = Math.ceil(total / APP.itemsPerPage);
    const container = document.getElementById('paginacao');
    
    if (!container || totalPaginas <= 1) return;
    
    let html = '<div style="display: flex; gap: 0.5rem; justify-content: center; margin-top: 2rem;">';
    
    // Botão anterior
    if (paginaAtual > 1) {
        html += `<button class="btn btn-outline" onclick="carregarMembros(${paginaAtual - 1})">
            <i class="fas fa-chevron-left"></i> Anterior
        </button>`;
    }
    
    // Números de página
    for (let i = 1; i <= totalPaginas; i++) {
        if (i === paginaAtual) {
            html += `<button class="btn btn-primary" disabled>${i}</button>`;
        } else {
            html += `<button class="btn btn-outline" onclick="carregarMembros(${i})">${i}</button>`;
        }
    }
    
    // Botão próximo
    if (paginaAtual < totalPaginas) {
        html += `<button class="btn btn-outline" onclick="carregarMembros(${paginaAtual + 1})">
            Próximo <i class="fas fa-chevron-right"></i>
        </button>`;
    }
    
    html += '</div>';
    container.innerHTML = html;
}

// ================================================
// 7. ALERTAS
// ================================================

function showAlert(mensagem, tipo = 'info') {
    const alertContainer = document.getElementById('alertContainer');
    if (!alertContainer) {
        const container = document.createElement('div');
        container.id = 'alertContainer';
        container.style.position = 'fixed';
        container.style.top = '20px';
        container.style.right = '20px';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${tipo}`;
    alert.innerHTML = `
        <i class="fas fa-check-circle"></i>
        <span>${mensagem}</span>
    `;
    
    document.getElementById('alertContainer').appendChild(alert);
    
    setTimeout(() => {
        alert.remove();
    }, 3000);
}

// ================================================
// 8. UTILITÁRIOS
// ================================================

function formatarData(data) {
    if (!data) return 'Não informado';
    
    const date = new Date(data);
    return date.toLocaleDateString('pt-BR');
}

function formatarCPF(cpf) {
    if (!cpf) return '';
    return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
}

function formatarTelefone(tel) {
    if (!tel) return '';
    return tel.replace(/(\d{2})(\d{4,5})(\d{4})/, '($1) $2-$3');
}

// ================================================
// 9. INICIALIZAÇÃO
// ================================================

document.addEventListener('DOMContentLoaded', () => {
    APP.init();
});

// ================================================
// FIM DO SCRIPT
// ================================================
