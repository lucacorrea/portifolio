// Service Worker Registration
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js');
}

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
        checkSavedCredentials();
    }
    
    if (document.getElementById('dashboard-screen')) {
        loadFiliais();
    }
});

async function apiCall(action, data = {}) {
    const formData = new FormData();
    formData.append('action', action);
    for (const key in data) {
        formData.append(key, data[key]);
    }

    try {
        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });
        return await response.json();
    } catch (e) {
        console.error('API Error:', e);
        return { success: false, message: 'Erro de conexão' };
    }
}

async function handleLogin(e) {
    e.preventDefault();
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const errorDiv = document.getElementById('login-error');

    errorDiv.style.display = 'none';

    const res = await apiCall('login', { email, password });
    if (res.success) {
        // Option to save for Biometrics
        if (confirm('Deseja ativar o desbloqueio por Biometria/FaceID para acessos futuros?')) {
            localStorage.setItem('adm_saved_email', email);
            localStorage.setItem('adm_saved_pass', btoa(password)); // Obfuscated for demo, ideally would use WebAuthn credential
            localStorage.setItem('biometrics_enabled', 'true');
        }
        location.reload();
    } else {
        errorDiv.textContent = res.message;
        errorDiv.style.display = 'block';
    }
}

function checkSavedCredentials() {
    if (localStorage.getItem('biometrics_enabled') === 'true') {
        document.getElementById('biometric-login').style.display = 'block';
    }
}

async function tryBiometricLogin() {
    // In a real WebAuthn implementation, we would call navigator.credentials.get()
    // Here we simulate the OS biometric prompt
    try {
        // This is a placeholder for actual Biometric Auth logic
        alert('Autenticando via Biometria...');
        
        const email = localStorage.getItem('adm_saved_email');
        const password = atob(localStorage.getItem('adm_saved_pass'));

        if (email && password) {
            const res = await apiCall('login', { email, password });
            if (res.success) {
                location.reload();
            } else {
                alert('Erro na biometria: Credenciais salvas inválidas.');
            }
        }
    } catch (e) {
        console.error('Biometric Error:', e);
    }
}

async function loadFiliais() {
    const res = await apiCall('get_filiais');
    if (res.success) {
        const selects = ['code-filial', 'temp-filial'];
        selects.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.innerHTML = res.data.map(f => `<option value="${f.id}">${f.nome}</option>`).join('');
        });
    }
}

async function generateCode() {
    const tipo = document.getElementById('code-type').value;
    const filialId = document.getElementById('code-filial').value;
    
    const res = await apiCall('generate_code', { tipo, filial_id: filialId });
    if (res.success) {
        document.getElementById('display-code').textContent = res.code;
        document.getElementById('code-result').style.display = 'block';
    } else {
        alert(res.message);
    }
}

async function generateTempLogin() {
    const minutes = document.getElementById('temp-time').value;
    const filialId = document.getElementById('temp-filial').value;
    
    const res = await apiCall('generate_temp_login', { minutes, filial_id: filialId });
    if (res.success) {
        document.getElementById('display-user').textContent = res.data.username;
        document.getElementById('display-pass').textContent = res.data.password;
        document.getElementById('display-time').textContent = new Date(res.data.validade).toLocaleString();
        document.getElementById('temp-result').style.display = 'block';
    } else {
        alert(res.message);
    }
}

function copyToClipboard(id) {
    const text = document.getElementById(id).textContent;
    navigator.clipboard.writeText(text);
    alert('Copiado!');
}

function copyLogin() {
    const user = document.getElementById('display-user').textContent;
    const pass = document.getElementById('display-pass').textContent;
    const time = document.getElementById('display-time').textContent;
    const text = `Acesso Temporário ERP\nUsuário: ${user}\nSenha: ${pass}\nVálido até: ${time}`;
    navigator.clipboard.writeText(text);
    alert('Informações de login copiadas!');
}

function logout() {
    apiCall('logout').then(() => {
        location.reload();
    });
}
