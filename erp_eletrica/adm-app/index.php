<?php
require_once '../config.php';
$isLoggedIn = isset($_SESSION['usuario_id']) && $_SESSION['usuario_id'] != -1 && $_SESSION['usuario_nivel'] === 'admin';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ERP Adm Mobile</title>
    <link rel="manifest" href="manifest.json">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body>
    <div class="app-container">
        <?php if (!$isLoggedIn): ?>
            <!-- Login Screen -->
            <div id="login-screen">
                <div class="header">
                    <i class="fas fa-shield-halved fa-4x mb-3" style="color: var(--accent)"></i>
                    <h1>ADM SHIELD</h1>
                    <p>Controle de Acessos e Autorizações</p>
                </div>
                <div class="premium-card">
                    <form id="login-form">
                        <div class="mb-3">
                            <label class="form-label small text-uppercase fw-bold opacity-75">Email Administrativo</label>
                            <input type="email" id="email" class="form-control" placeholder="seu-email@adm.com" required autocomplete="username">
                        </div>
                        <div class="mb-4">
                            <label class="form-label small text-uppercase fw-bold opacity-75">Senha Mestra</label>
                            <input type="password" id="password" class="form-control" placeholder="••••••••" required autocomplete="current-password">
                        </div>
                        <button type="submit" class="btn btn-premium mb-3">Entrar <i class="fas fa-chevron-right ms-2 mt-1"></i></button>
                        
                        <div id="biometric-login" style="display: none;">
                            <button type="button" class="btn face-id-btn w-100" onclick="tryBiometricLogin()">
                                <i class="fas fa-fingerprint me-2"></i> Desbloquear com Biometria
                            </button>
                        </div>
                    </form>
                    <div id="login-error" class="text-danger small mt-2 text-center" style="display:none;"></div>
                </div>
            </div>
        <?php else: ?>
            <!-- Dashboard Screen -->
            <div id="dashboard-screen">
                <div class="header d-flex align-items-center justify-content-between text-start pb-2">
                    <div>
                        <p class="mb-0 small text-uppercase fw-bold" style="letter-spacing: 1px">Bem-vindo, Adm</p>
                        <h1 class="mt-0 fw-bold"><?= $_SESSION['usuario_nome'] ?></h1>
                    </div>
                    <i class="fas fa-user-circle fa-2x opacity-50"></i>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs d-flex" id="admTabs">
                    <li class="nav-item flex-fill">
                        <a class="nav-link active" id="tab-codes" data-bs-toggle="tab" href="#codes-section">Códigos</a>
                    </li>
                    <li class="nav-item flex-fill">
                        <a class="nav-link" id="tab-logins" data-bs-toggle="tab" href="#logins-section">Logins</a>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Tab Codes -->
                    <div class="tab-pane fade show active" id="codes-section">
                        <div class="premium-card">
                            <h5 class="fw-bold mb-4"><i class="fas fa-key me-2 text-info"></i> Gerar Autorização</h5>
                            <div class="mb-3">
                                <label class="small text-uppercase opacity-75">Tipo de Operação</label>
                                <select id="code-type" class="form-select mt-1">
                                    <option value="geral">Qualquer</option>
                                    <option value="sangria">Sangria</option>
                                    <option value="suprimento">Suprimento</option>
                                    <option value="desconto">Desconto</option>
                                    <option value="cancelamento">Cancelamento</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="small text-uppercase opacity-75">Unidade Destino</label>
                                <select id="code-filial" class="form-select mt-1">
                                    <!-- Populated by JS -->
                                </select>
                            </div>
                            <button onclick="generateCode()" class="btn btn-premium">Gerar Código Unico</button>
                            
                            <div id="code-result" class="generated-area" style="display: none;">
                                <p class="small text-uppercase opacity-50 mb-0">Código Gerado</p>
                                <div class="code-display" id="display-code">------</div>
                                <div class="d-flex gap-2 justify-content-center mt-2">
                                    <button class="btn btn-sm btn-outline-info flex-fill" onclick="copyToClipboard('display-code')"><i class="fas fa-copy me-1"></i> COPIAR</button>
                                    <button class="btn btn-sm btn-success flex-fill" onclick="shareToWhatsApp('code')"><i class="fab fa-whatsapp me-1"></i> ENVIAR</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Logins -->
                    <div class="tab-pane fade" id="logins-section">
                        <div class="premium-card">
                            <h5 class="fw-bold mb-4"><i class="fas fa-user-shield me-2 text-info"></i> Login Temporário</h5>
                            <p class="small text-secondary mb-4">Cria um login "Master" que expira automaticamente.</p>
                            
                            <div class="mb-3">
                                <label class="small text-uppercase opacity-75">Tempo de Acesso</label>
                                <select id="temp-time" class="form-select mt-1">
                                    <option value="30">30 Minutos</option>
                                    <option value="60" selected>1 Hora</option>
                                    <option value="240">4 Horas</option>
                                    <option value="480">8 Horas</option>
                                    <option value="720">12 Horas</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="small text-uppercase opacity-75">Unidade (Filial)</label>
                                <select id="temp-filial" class="form-select mt-1">
                                    <!-- Populated by JS -->
                                </select>
                            </div>
                            <button onclick="generateTempLogin()" class="btn btn-premium" style="background-color: #6366f1; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3)">Criar Acesso Especial</button>

                            <div id="temp-result" class="generated-area mt-4 text-start" style="display: none;">
                                <div class="alert alert-info py-2" style="font-size: 0.75rem">
                                    <i class="fas fa-info-circle me-1"></i> Este acesso libera tudo do sistema.
                                </div>
                                <div class="mb-2">
                                    <label class="small text-uppercase opacity-50">Usuário:</label>
                                    <div class="fw-bold" id="display-user">-</div>
                                </div>
                                <div class="mb-2">
                                    <label class="small text-uppercase opacity-50">Senha:</label>
                                    <div class="fw-bold" id="display-pass">-</div>
                                </div>
                                <div class="mb-0">
                                    <label class="small text-uppercase opacity-50">Válido até:</label>
                                    <div class="small fw-bold text-info" id="display-time">-</div>
                                </div>
                                <div class="d-flex gap-2 mt-3">
                                    <button class="btn btn-sm btn-outline-info flex-fill" onclick="copyLogin()"><i class="fas fa-copy me-1"></i> COPIAR TUDO</button>
                                    <button class="btn btn-sm btn-success flex-fill" onclick="shareToWhatsApp('login')"><i class="fab fa-whatsapp me-1"></i> ENVIAR WHATSAPP</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center">
                    <button class="logout-btn" onclick="logout()">
                        <i class="fas fa-sign-out-alt me-1"></i> SAIR DA CONTA ADM
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>
