<?php
namespace App\Services;

use App\Repositories\UserRepository;

class AuthService extends BaseService {
    public function __construct() {
        parent::__construct(new UserRepository());
    }

    public function login(string $email, string $password, int $filialId = 0) {
        $user = $this->repository->findByEmail($email);
        $tempLoginService = new \App\Services\TemporaryLoginService();
        $tempUser = null;
        
        $success = false;
        $userId = null;
        $motivo = '';

        if ($user) {
            // Check branch assignment for non-master admins
            if ($user['nivel'] !== 'master' && $filialId > 0 && $user['filial_id'] != $filialId) {
                $motivo = 'Unidade de acesso incorreta';
            } elseif (password_verify($password, $user['senha'])) {
                $userId = $user['id'];
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_nome'] = $user['nome'];
                $_SESSION['usuario_nivel'] = $user['nivel'];
                $_SESSION['filial_id'] = $user['filial_id'];
                $_SESSION['is_temporary'] = false;
                
                // Check if user is in Matriz
                $filialModel = new \App\Models\Filial();
                $stmt = $filialModel->query("SELECT principal FROM filiais WHERE id = ?", [$user['filial_id']]);
                $filial = $stmt->fetch();
                $_SESSION['is_matriz'] = ($filial && $filial['principal'] == 1);
                
                $this->repository->updateLastLogin($user['id']);
                $this->logAction('login', 'usuarios', $user['id']);
                $success = true;
            } else {
                $userId = $user['id'];
                $motivo = 'Senha incorreta';
            }
        } elseif ($tempUser = $tempLoginService->validate($email, $password)) {
            // Temporary login successful - must match branch if provided
            if ($filialId > 0 && $tempUser['filial_id'] != $filialId) {
                $motivo = 'Login temporário restrito a outra unidade';
            } else {
                $_SESSION['usuario_id'] = -1; // Pseudo-ID for temp users
                $_SESSION['temp_login_id'] = $tempUser['id'];
                $_SESSION['usuario_nome'] = $tempUser['usuario_aleatorio'];
                $_SESSION['usuario_nivel'] = 'admin'; // "Full" access as requested
                $_SESSION['filial_id'] = $tempUser['filial_id'];
                $_SESSION['is_temporary'] = true;
                $_SESSION['is_matriz'] = true; // Temp logins often need wide access
                
                $success = true;
                $motivo = 'Login temporário';
            }
        } else {
            $motivo = 'Usuário não encontrado';
        }
        
        $this->recordAccessAttempt($email, $userId, $success, $motivo);
        return $success;
    }

    private function recordAccessAttempt($email, $userId, $success, $motivo) {
        $db = \App\Config\Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO logs_acesso (usuario_id, email_tentativa, ip_address, user_agent, sucesso, motivo) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId, 
            $email, 
            $_SERVER['REMOTE_ADDR'] ?? 'unknown', 
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 
            $success ? 1 : 0, 
            $motivo
        ]);
    }

    public static function check($niveis_permitidos = []) {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login.php');
            exit;
        }
        
        if (!empty($niveis_permitidos) && !in_array($_SESSION['usuario_nivel'], $niveis_permitidos)) {
            header('Location: index.php?msg=Acesso negado');
            exit;
        }
    }

    public static function hasPermission($modulo, $acao) {
        if (!isset($_SESSION['usuario_id'])) return false;
        if (($_SESSION['usuario_nivel'] ?? '') === 'admin') return true;

        $nivel = $_SESSION['usuario_nivel'];
        
        // Hardcoded restrictions for 'vendedor' level
        if ($nivel === 'vendedor') {
            // Vendedor: ONLY Pre-Sales (full), Inventory (full) and Sales search (visualizar)
            if ($modulo === 'pre_vendas') return true;
            if ($modulo === 'caixa') return true;
            if ($modulo === 'estoque') return true;
            if ($modulo === 'vendas' && $acao === 'visualizar') return true;
            return false;
        }

        if ($nivel === 'gerente') {
            // Gerente: FULL Sales, Cashier, Costs and Intelligence
            if ($modulo === 'vendas') return true;
            if ($modulo === 'caixa') return true;
            if ($modulo === 'custos') return true;
            if ($modulo === 'inteligencia') return true;
            if ($modulo === 'estoque') return true;
            return false;
        }

        $db = \App\Config\Database::getInstance()->getConnection();
        
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM permissao_nivel pn
                JOIN permissoes p ON pn.permissao_id = p.id
                WHERE pn.nivel = ? AND p.modulo = ? AND p.acao = ?
            ");
            $stmt->execute([$nivel, $modulo, $acao]);
            return $stmt->fetchColumn() > 0;
        } catch (\PDOException $e) {
            // Fallback robusto se as tabelas de permissão (007_rbac_schema) não existirem
            // Admins sempre têm acesso. Gerentes e Vendedores têm acessos baseados no hardcoding acima.
            return $nivel === 'admin';
        }
    }

    public static function checkPermission($modulo, $acao) {
        if (!self::hasPermission($modulo, $acao)) {
            header('Location: index.php?error=Acesso negado');
            exit;
        }
    }

    public function logout() {
        if (isset($_SESSION['is_temporary']) && $_SESSION['is_temporary'] && isset($_SESSION['temp_login_id'])) {
            $tempService = new \App\Services\TemporaryLoginService();
            $tempService->repository->invalidate($_SESSION['temp_login_id']);
        }

        if (isset($_SESSION['usuario_id']) && $_SESSION['usuario_id'] > 0) {
            $this->logAction('logout', 'usuarios', $_SESSION['usuario_id']);
        }
        
        session_unset();
        session_destroy();
    }
}
