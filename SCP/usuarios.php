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
    <title>Usuários - SCP</title>
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
        <a href="cadastro.php" class="nav-link"><i class="fas fa-plus-circle"></i> Novo</a>
        <a href="prazos.php" class="nav-link"><i class="fas fa-clock"></i> Prazos</a>
        <a href="tipos.php" class="nav-link"><i class="fas fa-layer-group"></i> Tipos</a>
        <a href="relatorios.php" class="nav-link"><i class="fas fa-chart-line"></i> Relatórios</a>
        <?php if ($_SESSION['usuario_perfil'] === 'ADMIN'): ?>
        <a href="usuarios.php" class="nav-link active"><i class="fas fa-users"></i> Usuários</a>
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
            <h1>Gestão de Usuários</h1>
            <p>Controle de acesso para analisadores e administradores.</p>
        </div>
    </header>

    <section class="data-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2 style="font-size: 1.25rem;">Usuários do Sistema</h2>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <input type="text" id="filtro-usuarios" placeholder="Pesquisar usuários..." style="width: 250px; padding: 0.5rem 1rem; height: 38px;">
                <button class="btn btn-primary" onclick="abrirModalUsuario()" style="height: 38px;"><i class="fas fa-user-plus"></i> Novo Usuário</button>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%;">
                <thead>
                    <tr>
                        <th>NOME</th>
                        <th>LOGIN</th>
                        <th>PERFIL</th>
                        <th>AÇÕES</th>
                    </tr>
                </thead>
                <tbody id="lista-usuarios">
                    <!-- Dinâmico -->
                </tbody>
            </table>
        </div>
        <div id="paginacao-usuarios" class="pagination" style="margin-top: 1.5rem;"></div>
    </section>
</main>

<div class="modal-overlay" id="modal-usuario" onclick="if(event.target === this) fecharModal()">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-title">Novo Usuário</h2>
            <button onclick="fecharModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:var(--text-muted);">&times;</button>
        </div>
        <form id="form-usuario">
            <input type="hidden" id="user-id">
            <div class="form-group">
                <label>Nome Completo</label>
                <input type="text" id="user-nome" required placeholder="Ex: João Silva">
            </div>
            <div class="form-group">
                <label>Login de Acesso</label>
                <input type="text" id="user-login" required placeholder="Ex: joao.silva">
            </div>
            <div class="form-group">
                <label>Senha</label>
                <div class="password-wrapper">
                    <input type="password" id="user-senha" required placeholder="******">
                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility('user-senha', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <small id="senha-aviso" style="display:none; color:var(--text-muted);">Deixe em branco para não alterar a senha atual.</small>
            </div>
            <div class="form-group">
                <label>Perfil de Acesso</label>
                <select id="user-perfil">
                    <option value="ANALISADOR">ANALISADOR</option>
                    <option value="ADMIN">ADMINISTRADOR</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="fecharModal()" style="background:#e2e8f0; color:#475569;">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar Usuário</button>
            </div>
        </form>
    </div>
</div>

<script>
    let usuariosData = [];
    let paginaAtual = 1;
    const itensPorPagina = 10;

    async function carregarUsuarios() {
        const resp = await fetch('api.php?acao=listar_usuarios');
        usuariosData = await resp.json();
        renderizarTabelaUsuarios();
    }

    function renderizarTabelaUsuarios() {
        const tbody = document.getElementById('lista-usuarios');
        if (!tbody) return;
        tbody.innerHTML = '';
        
        const inputBusca = document.getElementById('filtro-usuarios');
        let filtrados = usuariosData;

        if (inputBusca && inputBusca.value) {
            const query = inputBusca.value.toUpperCase();
            filtrados = filtrados.filter(u => 
                u.nome.toUpperCase().includes(query) || 
                u.login.toUpperCase().includes(query)
            );
        }

        const totalPaginas = Math.ceil(filtrados.length / itensPorPagina);
        const inicio = (paginaAtual - 1) * itensPorPagina;
        const fim = inicio + itensPorPagina;
        const paginados = filtrados.slice(inicio, fim);

        if (paginados.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 2rem; color: var(--text-muted);">Nenhum usuário encontrado.</td></tr>';
            renderizarPaginacaoUsuarios(0);
            return;
        }

        paginados.forEach(u => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${u.nome}</td>
                <td>${u.login}</td>
                <td><span class="badge" style="background:#e2e8f0; color:#475569;">${u.perfil}</span></td>
                <td>
                    <div style="display:flex; gap:10px;">
                        <button class="btn-acao" onclick="abrirModalUsuario(${u.id})" title="Editar" style="color:var(--primary); border:none; background:none; cursor:pointer;"><i class="fas fa-edit"></i></button>
                        <button class="btn-acao" onclick="excluirUsuario(${u.id})" title="Excluir" style="color:#ef4444; border:none; background:none; cursor:pointer;"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });

        renderizarPaginacaoUsuarios(totalPaginas);
    }

    function renderizarPaginacaoUsuarios(totalPaginas) {
        const pagContainer = document.getElementById('paginacao-usuarios');
        if (!pagContainer) return;
        pagContainer.innerHTML = '';
        if (totalPaginas <= 1) return;

        const btnAnt = document.createElement('button');
        btnAnt.innerHTML = '<i class="fas fa-chevron-left"></i>';
        btnAnt.disabled = paginaAtual === 1;
        btnAnt.onclick = () => { paginaAtual--; renderizarTabelaUsuarios(); };
        pagContainer.appendChild(btnAnt);

        for (let i = 1; i <= totalPaginas; i++) {
            const btn = document.createElement('button');
            btn.textContent = i;
            if (i === paginaAtual) btn.classList.add('active');
            btn.onclick = () => { paginaAtual = i; renderizarTabelaUsuarios(); };
            pagContainer.appendChild(btn);
        }

        const btnProx = document.createElement('button');
        btnProx.innerHTML = '<i class="fas fa-chevron-right"></i>';
        btnProx.disabled = paginaAtual === totalPaginas;
        btnProx.onclick = () => { paginaAtual++; renderizarTabelaUsuarios(); };
        pagContainer.appendChild(btnProx);
    }

    window.togglePasswordVisibility = (inputId, btn) => {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    };

    window.excluirUsuario = async (id) => {
        if (id === <?php echo $_SESSION['usuario_id']; ?>) return alert('Você não pode excluir o seu próprio usuário!');
        if (!confirm('Deseja excluir este usuário?')) return;
        await fetch(`api.php?acao=excluir_usuario&id=${id}`, { method: 'DELETE' });
        carregarUsuarios();
    };

    window.abrirModalUsuario = (id = null) => {
        const modal = document.getElementById('modal-usuario');
        const form = document.getElementById('form-usuario');
        const title = document.getElementById('modal-title');
        const aviso = document.getElementById('senha-aviso');
        const inputSenha = document.getElementById('user-senha');
        
        form.reset();
        document.getElementById('user-id').value = '';
        aviso.style.display = 'none';
        inputSenha.required = true;

        if (id) {
            const u = usuariosData.find(x => x.id == id);
            if (u) {
                document.getElementById('user-id').value = u.id;
                document.getElementById('user-nome').value = u.nome;
                document.getElementById('user-login').value = u.login;
                document.getElementById('user-perfil').value = u.perfil;
                document.getElementById('user-senha').value = u.senha_plana || '';
                title.textContent = 'Editar Usuário';
                aviso.style.display = 'block';
                inputSenha.required = false; // Não é obrigatória na edição
            }
        } else {
            title.textContent = 'Novo Usuário';
        }

        modal.classList.add('active');
    };

    window.fecharModal = () => {
        document.getElementById('modal-usuario').classList.remove('active');
    };
    document.getElementById('form-usuario').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('user-id').value;
        const nome = document.getElementById('user-nome').value;
        const login = document.getElementById('user-login').value;
        const senha = document.getElementById('user-senha').value;
        const perfil = document.getElementById('user-perfil').value;

        const resp = await fetch('api.php?acao=salvar_usuario', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, nome, login, senha, perfil })
        });
        
        if (resp.ok) {
            fecharModal();
            carregarUsuarios();
        } else {
            const errorData = await resp.json();
            alert('Erro: ' + (errorData.message || 'Erro ao salvar usuário.'));
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        carregarUsuarios();

        const inputBusca = document.getElementById('filtro-usuarios');
        if (inputBusca) {
            inputBusca.addEventListener('input', () => {
                paginaAtual = 1;
                renderizarTabelaUsuarios();
            });
        }
    });
</script>
<script src="assets/js/script.js"></script>
</body>
</html>
