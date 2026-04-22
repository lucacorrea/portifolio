// Service Worker Registration
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js');
}

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
        checkBiometricSupport();
        
        // Automation: Try to trigger Conditional UI (Passkeys)
        if (localStorage.getItem('webauthn_registered') === 'true') {
            tryBiometricLogin(true); // Auto-attempt with conditional mediation
        }
    }
    
    if (document.getElementById('dashboard-screen')) {
        loadFiliais();
        updateBiometricsUI();
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
        location.reload();
    } else {
        errorDiv.textContent = res.message;
        errorDiv.style.display = 'block';
    }
}

// --- Native Biometrics (WebAuthn) ---
let cachedWebAuthnChallenge = null;

async function preFetchWebAuthnChallenge() {
    try {
        const res = await apiCall('get_webauthn_challenge');
        if (res.success) {
            cachedWebAuthnChallenge = res.challenge || (res.data && res.data.challenge);
        }
    } catch (e) {
        console.warn('Falha pré-carregamento:', e);
    }
}

async function checkBiometricSupport() {
    const btnBio = document.getElementById('biometric-login');
    if (!btnBio) return;

    if (window.PublicKeyCredential && 
        await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()) {
        
        if (localStorage.getItem('webauthn_registered') === 'true') {
            btnBio.style.display = 'block';
            preFetchWebAuthnChallenge(); 
        }
    }
}

async function registerBiometrics() {
    if (!window.isSecureContext) {
        alert('A biometria exige conexão segura (HTTPS). Certifique-se de que o site está usando SSL.');
        return;
    }

    try {
        const resChallenge = await apiCall('get_webauthn_challenge');
        if (!resChallenge.success) throw new Error(resChallenge.message || 'Não foi possível obter desafio do servidor.');

        const challengeBase64 = resChallenge.challenge || (resChallenge.data && resChallenge.data.challenge);
        if (!challengeBase64) throw new Error('Desafio não encontrado na resposta do servidor.');
        
        const challenge = base64ToBinary(challengeBase64);
        const userId = Uint8Array.from(window.crypto.getRandomValues(new Uint8Array(16)));

        const publicKeyCredentialCreationOptions = {
            challenge: challenge,
            rp: { name: "ERP Elétrica ADM", id: window.location.hostname },
            user: {
                id: userId,
                name: "Administrador",
                displayName: "Administrador Geral"
            },
            pubKeyCredParams: [
                { alg: -7, type: "public-key" }, // ES256
                { alg: -257, type: "public-key" } // RS256
            ],
            authenticatorSelection: { 
                authenticatorAttachment: "platform", 
                userVerification: "required" 
            },
            timeout: 60000
        };

        const credential = await navigator.credentials.create({ publicKey: publicKeyCredentialCreationOptions });
        
        // Use modern getPublicKey if available (Chrome 11x+, Android, iOS 16+)
        let publicKeyPem = "";
        if (typeof credential.response.getPublicKey === 'function') {
            const spkiBuffer = credential.response.getPublicKey();
            publicKeyPem = spkiToPem(spkiBuffer);
        } else {
            throw new Error('Seu navegador não suporta a exportação segura da chave pública biométrica necessária.');
        }
        
        const res = await apiCall('webauthn_register', {
            attestationObject: binaryToBase64(credential.response.attestationObject),
            clientDataJSON: new TextDecoder().decode(credential.response.clientDataJSON),
            result: JSON.stringify({
                id: binaryToBase64(new Uint8Array(credential.rawId)),
                publicKey: publicKeyPem,
                deviceName: (navigator.userAgent.match(/\(([^)]+)\)/) || [null, 'Celular'])[1]
            })
        });

        if (res.success) {
            localStorage.setItem('webauthn_registered', 'true');
            alert('Biometria registrada com sucesso!');
            updateBiometricsUI();
        } else {
            alert(res.message);
        }
    } catch (e) {
        console.error('Registration error:', e);
        if (e.name === 'NotAllowedError') {
            alert('Permissão negada ou biometria cancelada.');
        } else {
            alert('Erro ao configurar biometria: ' + e.message);
        }
    }
}

async function tryBiometricLogin(isAuto = false) {
    try {
        // Use cached challenge to preserve user gesture
        let challengeBase64 = cachedWebAuthnChallenge;
        
        if (!challengeBase64) {
            if (isAuto) return; // Don't block auto-flow
            
            // If not cached, fetch it now (might lose gesture on some devices)
            const resChallenge = await apiCall('get_webauthn_challenge');
            challengeBase64 = resChallenge.challenge || (resChallenge.data && resChallenge.data.challenge);
        }

        if (!challengeBase64) return;
        const challenge = base64ToBinary(challengeBase64);

        const publicKeyCredentialRequestOptions = {
            challenge: challenge,
            allowCredentials: [], 
            userVerification: "required"
        };

        if (isAuto) {
            publicKeyCredentialRequestOptions.mediation = 'conditional';
        }

        const assertion = await navigator.credentials.get({ publicKey: publicKeyCredentialRequestOptions });
        if (!assertion) return;

        // Clear cache for next attempt
        cachedWebAuthnChallenge = null;
        preFetchWebAuthnChallenge(); // Re-fetch for next time

        const res = await apiCall('webauthn_login', {
            credentialId: binaryToBase64(new Uint8Array(assertion.rawId)),
            clientDataJSON: new TextDecoder().decode(assertion.response.clientDataJSON),
            authenticatorData: binaryToBase64(new Uint8Array(assertion.response.authenticatorData)),
            signature: binaryToBase64(new Uint8Array(assertion.response.signature))
        });

        if (res.success) {
            location.reload();
        } else {
            console.error('Login biometric error:', res.message);
            if (!isAuto) alert(res.message);
        }
    } catch (e) {
        if (isAuto) {
            console.log('Passkey automation not ready:', e.message);
            return;
        }
        console.warn('Falha na Biometria:', e);
        // If it was a NotAllowedError, it's likely a timeout or cancelled
        if (e.name !== 'NotAllowedError') {
            alert('Erro: ' + e.message);
        }
    }
}

function updateBiometricsUI() {
    const status = document.getElementById('biometrics-status');
    const btn = document.getElementById('btn-register-bio');
    if (!status) return;

    if (localStorage.getItem('webauthn_registered') === 'true') {
        status.className = 'alert alert-success py-2 extra-small mb-3';
        status.innerHTML = '<i class="fas fa-check-circle me-1"></i> Biometria Ativa neste dispositivo.';
        btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i> Atualizar Biometria';
    }
}

// --- Helpers ---

function base64ToBinary(base64) {
    // Robust decoding: remove whitespace and handle non-standard Base64URL characters if any
    const cleanBase64 = base64.replace(/\s/g, '').replace(/-/g, '+').replace(/_/g, '/');
    try {
        return Uint8Array.from(atob(cleanBase64), c => c.charCodeAt(0));
    } catch (e) {
        console.error('atob error for string:', cleanBase64);
        throw e;
    }
}

function binaryToBase64(binary) {
    return btoa(String.fromCharCode(...new Uint8Array(binary)));
}

/**
 * Converts SPKI ArrayBuffer to PEM string.
 */
function spkiToPem(buffer) {
    const base64 = binaryToBase64(buffer);
    const matches = base64.match(/.{1,64}/g);
    return "-----BEGIN PUBLIC KEY-----\n" + matches.join("\n") + "\n-----END PUBLIC KEY-----";
}

// --- Features ---

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

function shareToWhatsApp(type) {
    let message = "";
    if (type === 'code') {
        const code = document.getElementById('display-code').textContent;
        const tipo = document.getElementById('code-type').options[document.getElementById('code-type').selectedIndex].text;
        message = `*Código de Autorização ERP*\nOperação: ${tipo}\nCódigo: *${code}*`;
    } else {
        const user = document.getElementById('display-user').textContent;
        const pass = document.getElementById('display-pass').textContent;
        const time = document.getElementById('display-time').textContent;
        message = `*Acesso Temporário ERP*\nUsuário: ${user}\nSenha: ${pass}\nVálido até: ${time}`;
    }
    
    const url = `https://wa.me/?text=${encodeURIComponent(message)}`;
    window.open(url, '_blank');
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
