<?php
require_once '../config.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';
$response = ['success' => false, 'message' => 'Ação inválida'];

// Check if logged in for most actions
$isLoggedIn = isset($_SESSION['usuario_id']) && $_SESSION['usuario_id'] != -1;
if (!$isLoggedIn && $action !== 'login') {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada']);
    exit;
}

// Only Admin/Master allowed
if ($isLoggedIn && $_SESSION['usuario_nivel'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acesso restrito a administradores']);
    exit;
}

try {
    switch ($action) {
        case 'login':
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $authService = new \App\Services\AuthService();
            if ($authService->login($email, $password)) {
                if ($_SESSION['usuario_nivel'] === 'admin') {
                    $response = [
                        'success' => true, 
                        'user' => [
                            'nome' => $_SESSION['usuario_nome'],
                            'filial_id' => $_SESSION['filial_id']
                        ]
                    ];
                } else {
                    $authService->logout();
                    $response = ['success' => false, 'message' => 'Apenas administradores podem acessar este app'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Credenciais inválidas'];
            }
            break;

        case 'generate_code':
            $tipo = $_POST['tipo'] ?? 'geral';
            $filialAlvo = $_POST['filial_id'] ?? $_SESSION['filial_id'];
            $authService = new \App\Services\AuthorizationService();
            $code = $authService->generateCode($tipo, $filialAlvo, $_SESSION['usuario_id']);
            $response = ['success' => true, 'code' => $code];
            break;

        case 'generate_temp_login':
            $minutes = (int)($_POST['minutes'] ?? 60);
            $filialId = $_POST['filial_id'] ?? $_SESSION['filial_id'];
            $tempService = new \App\Services\TemporaryLoginService();
            $data = $tempService->generateLogin($_SESSION['usuario_id'], $filialId, $minutes);
            $response = ['success' => true, 'data' => $data];
            break;

        case 'get_filiais':
            $stmt = $pdo->query("SELECT id, nome FROM filiais ORDER BY principal DESC, nome ASC");
            $response = ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;
            
        case 'logout':
            $authService = new \App\Services\AuthService();
            $authService->logout();
            $response = ['success' => true];
            break;

        case 'get_webauthn_challenge':
            $webauthn = new \App\Services\WebAuthnService();
            $challenge = $webauthn->generateChallenge();
            $response = ['success' => true, 'challenge' => base64_encode($challenge)];
            break;

        case 'webauthn_register':
            $webauthn = new \App\Services\WebAuthnService();
            $parsedData = json_decode($_POST['result'] ?? '', true);
            if ($webauthn->verifyRegistration($_SESSION['usuario_id'], $_POST['clientDataJSON'], $_POST['attestationObject'], $parsedData)) {
                $response = ['success' => true, 'message' => 'Biometria vinculada com sucesso!'];
            } else {
                $response = ['success' => false, 'message' => 'Erro ao vincular biometria.'];
            }
            break;

        case 'webauthn_login':
            $webauthn = new \App\Services\WebAuthnService();
            $userId = $webauthn->verifyAuthentication(
                $_POST['credentialId'],
                $_POST['clientDataJSON'],
                $_POST['authenticatorData'],
                $_POST['signature']
            );
            
            if ($userId) {
                // Log in user
                $db = \App\Config\Database::getInstance()->getConnection();
                $user = $db->query("SELECT * FROM usuarios WHERE id = " . (int)$userId)->fetch();
                if ($user) {
                    $_SESSION['usuario_id'] = $user['id'];
                    $_SESSION['usuario_nome'] = $user['nome'];
                    $_SESSION['usuario_nivel'] = $user['nivel'];
                    $_SESSION['filial_id'] = $user['filial_id'];
                    $_SESSION['is_temporary'] = false;
                    $response = ['success' => true, 'message' => 'Login biométrico realizado!'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Falha na autenticação biométrica.'];
            }
            break;
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()];
}

echo json_encode($response);
