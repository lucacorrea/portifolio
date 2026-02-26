<?php
namespace App\Services;

use App\Repositories\UserRepository;

class AuthService extends BaseService {
    public function __construct() {
        parent::__construct(new UserRepository());
    }

    public function login(string $email, string $password) {
        $user = $this->repository->findByEmail($email);
        $success = false;
        $userId = null;
        $motivo = '';

        if ($user) {
            if (password_verify($password, $user['senha'])) {
                $userId = $user['id'];
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_nome'] = $user['nome'];
                $_SESSION['usuario_nivel'] = $user['nivel'];
                $_SESSION['filial_id'] = $user['filial_id'];
                
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
        if (($_SESSION['usuario_nivel'] ?? '') === 'master' || ($_SESSION['usuario_nivel'] ?? '') === 'admin') return true;

        $nivel = $_SESSION['usuario_nivel'];
        
        // Hardcoded restrictions for 'vendedor' level
        if ($nivel === 'vendedor') {
            // Vendedor can ONLY visualize Sales and Inventory
            if (in_array($modulo, ['vendas', 'estoque'])) {
                return $acao === 'visualizar';
            }
            // Everything else is blocked (including pre_vendas as requested)
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
            // Fallback if table doesn't exist (migrations didn't run)
            if ($e->getCode() == '42S02') {
                return in_array($nivel, ['admin', 'master', 'gerente']);
            }
            throw $e;
        }
    }

    public static function checkPermission($modulo, $acao) {
        if (!self::hasPermission($modulo, $acao)) {
            header('Location: index.php?error=Acesso negado');
            exit;
        }
    }

    public function logout() {
        if (isset($_SESSION['usuario_id'])) {
            $this->logAction('logout', 'usuarios', $_SESSION['usuario_id']);
        }
        session_unset();
        session_destroy();
    }
}
