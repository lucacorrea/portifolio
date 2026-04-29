<?php include 'includes/header.php'; ?>
<?php
if ($_SESSION['usuario_perfil'] !== 'ADMIN') {
    echo "<script>location.href='index.php';</script>";
    exit();
}
?>

<header style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-end;">
    <div>
        <h1 style="font-size: 2.2rem; font-weight: 800; margin-bottom: 0.5rem;">Gestão de Usuários</h1>
        <p style="color: var(--text-muted);">Controle de acesso e perfis da equipe.</p>
    </div>
    <button class="btn-premium" onclick="abrirModalUsuario()">
        <i class="fas fa-user-plus"></i> Novo Usuário
    </button>
</header>

<div class="glass-card" style="padding: 0;">
    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <h2 style="font-size: 1.2rem;">Usuários Ativos</h2>
        <div style="display: flex; gap: 1rem;">
            <input type="text" id="filtro-usuarios" placeholder="Buscar por nome ou login..." style="background: rgba(0,0,0,0.2); border: 1px solid var(--border); border-radius: 8px; padding: 0.5rem 1rem; color: white; outline: none; width: 300px;">
        </div>
    </div>
    <div style="padding: 1rem; overflow-x: auto;">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Login</th>
                    <th>Perfil</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="lista-usuarios-v2">
                <!-- Dinâmico -->
            </tbody>
        </table>
    </div>
    <div id="paginacao-usuarios" style="padding: 1rem; display: flex; justify-content: center; gap: 5px;"></div>
</div>

<!-- Modal Usuário -->
<div id="modal-usuario" style="display:none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:2000; align-items:center; justify-content:center; backdrop-filter: blur(5px);">
    <div class="glass-card" style="width: 100%; max-width: 500px; padding: 2rem;">
        <h2 id="modal-title" style="margin-bottom: 1.5rem; font-weight: 800;">Novo Usuário</h2>
        <form id="form-usuario-v2">
            <input type="hidden" id="user-id">
            <div style="display:flex; flex-direction:column; gap: 1.5rem;">
                <div class="form-group">
                    <label>Nome Completo</label>
                    <input type="text" id="user-nome" required style="width:100%">
                </div>
                <div class="form-group">
                    <label>Login</label>
                    <input type="text" id="user-login" required style="width:100%">
                </div>
                <div class="form-group">
                    <label>Senha</label>
                    <input type="password" id="user-senha" style="width:100%">
                    <small id="senha-aviso" style="display:none; color:var(--text-muted); font-size:0.7rem; margin-top:5px;">Deixe em branco para manter a atual.</small>
                </div>
                <div class="form-group">
                    <label>Perfil</label>
                    <select id="user-perfil" style="width:100%">
                        <option value="ANALISADOR">ANALISADOR</option>
                        <option value="ACESSORES">ACESSORES</option>
                        <option value="ADMIN">ADMINISTRADOR</option>
                    </select>
                </div>
            </div>
            <div style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" class="btn-premium" style="background: var(--border);" onclick="fecharModal()">Cancelar</button>
                <button type="submit" class="btn-premium">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
    let usuariosData = [];
    let paginaAtual = 1;
    const itensPorPagina = 10;

    async function carregarUsuarios() {
        const resp = await fetch('../api.php?acao=listar_usuarios');
        usuariosData = await resp.json();
        renderizarTabela();
    }

    function renderizarTabela() {
        const tbody = document.getElementById('lista-usuarios-v2');
        const query = document.getElementById('filtro-usuarios').value.toUpperCase();
        
        let filtrados = usuariosData.filter(u => 
            u.nome.toUpperCase().includes(query) || u.login.toUpperCase().includes(query)
        );

        const totalPaginas = Math.ceil(filtrados.length / itensPorPagina);
        const inicio = (paginaAtual - 1) * itensPorPagina;
        const paginados = filtrados.slice(inicio, inicio + itensPorPagina);

        tbody.innerHTML = '';
        paginados.forEach(u => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="font-weight: 600;">${u.nome}</td>
                <td>${u.login}</td>
                <td><span style="background: rgba(255,255,255,0.1); padding: 4px 10px; border-radius: 50px; font-size: 0.75rem;">${u.perfil}</span></td>
                <td>
                    <div class="dropdown">
                        <button class="btn-dots" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                        <div class="dropdown-menu">
                            <button onclick="abrirModalUsuario(${u.id})"><i class="fas fa-edit"></i> Editar Dados</button>
                            <button onclick="excluirUsuario(${u.id})" style="color: #f87171;"><i class="fas fa-user-times"></i> Remover Usuário</button>
                        </div>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });

        renderizarPaginacao(totalPaginas);
    }

    function renderizarPaginacao(total) {
        const container = document.getElementById('paginacao-usuarios');
        container.innerHTML = '';
        if(total <= 1) return;

        for(let i=1; i<=total; i++) {
            const btn = document.createElement('button');
            btn.innerText = i;
            btn.className = (i === paginaAtual) ? 'btn-premium' : 'btn-premium';
            if(i !== paginaAtual) btn.style.background = 'var(--border)';
            btn.style.padding = '5px 12px';
            btn.onclick = () => { paginaAtual = i; renderizarTabela(); };
            container.appendChild(btn);
        }
    }

    window.abrirModalUsuario = (id = null) => {
        const modal = document.getElementById('modal-usuario');
        const form = document.getElementById('form-usuario-v2');
        form.reset();
        document.getElementById('user-id').value = '';
        document.getElementById('senha-aviso').style.display = 'none';
        document.getElementById('user-senha').required = true;
        document.getElementById('modal-title').innerText = 'Novo Usuário';

        if(id) {
            const u = usuariosData.find(x => x.id == id);
            document.getElementById('user-id').value = u.id;
            document.getElementById('user-nome').value = u.nome;
            document.getElementById('user-login').value = u.login;
            document.getElementById('user-perfil').value = u.perfil;
            document.getElementById('senha-aviso').style.display = 'block';
            document.getElementById('user-senha').required = false;
            document.getElementById('modal-title').innerText = 'Editar Usuário';
        }
        modal.style.display = 'flex';
    };

    window.fecharModal = () => {
        document.getElementById('modal-usuario').style.display = 'none';
    };

    window.excluirUsuario = async (id) => {
        const result = await Swal.fire({
            title: 'Tem certeza?',
            text: "Esta ação não pode ser desfeita!",
            icon: 'warning',
            showCancelButton: true,
            background: '#1e293b',
            color: '#fff',
            confirmButtonColor: '#f87171',
            cancelButtonColor: '#334155',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        });

        if (result.isConfirmed) {
            await fetch(`../api.php?acao=excluir_usuario&id=${id}`, { method: 'DELETE' });
            carregarUsuarios();
        }
    };

    document.getElementById('form-usuario-v2').onsubmit = async (e) => {
        e.preventDefault();
        const data = {
            id: document.getElementById('user-id').value,
            nome: document.getElementById('user-nome').value,
            login: document.getElementById('user-login').value,
            senha: document.getElementById('user-senha').value,
            perfil: document.getElementById('user-perfil').value
        };

        const resp = await fetch('../api.php?acao=salvar_usuario', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        if(resp.ok) {
            fecharModal();
            carregarUsuarios();
            Swal.fire({ icon: 'success', title: 'Sucesso!', background: '#1e293b', color: '#fff' });
        }
    };

    document.getElementById('filtro-usuarios').oninput = () => {
        paginaAtual = 1;
        renderizarTabela();
    };

    document.addEventListener('DOMContentLoaded', carregarUsuarios);
</script>

<?php include 'includes/footer.php'; ?>
