<?php
namespace App\Controllers;

use App\Models\Setting;

class SettingController extends BaseController {
    public function index() {
        $model = new Setting();
        $settings = $model->getAll();

        $db = \App\Config\Database::getInstance()->getConnection();
        $sefaz = $db->query("SELECT * FROM sefaz_config LIMIT 1")->fetch();

        $filialModel = new \App\Models\Filial();
        $branches = $filialModel->getAllBranches();
        $nfceService = new \App\Services\NfceService();

        // Dados da unidade logada
        $currentBranchId = $_SESSION['filial_id'] ?? null;
        $currentBranch = null;
        $activeConfig = [];
        
        if ($currentBranchId) {
            $stmt = $db->prepare("SELECT * FROM filiais WHERE id = ?");
            $stmt->execute([$currentBranchId]);
            $currentBranch = $stmt->fetch();
            $activeConfig = $nfceService->getConfig($currentBranchId);
        }

        // Configuração da Matriz para usar como padrão em novas unidades
        $matrizBranch = $db->query("SELECT id FROM filiais WHERE principal = 1 LIMIT 1")->fetch();
        $matrizConfig = $matrizBranch ? $nfceService->getConfig($matrizBranch['id']) : $activeConfig;

        $this->render('settings', [
            'settings' => $settings,
            'sefaz' => $sefaz,
            'branches' => $branches,
            'currentBranch' => $currentBranch,
            'activeConfig' => $activeConfig,
            'matrizConfig' => $matrizConfig,
            'title' => 'Configurações de Redes & Fiscal',
            'pageTitle' => 'Painel de Gestão Centralizada'
        ]);
    }

    public function saveMatriz() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = \App\Config\Database::getInstance()->getConnection();
            $audit = new \App\Services\AuditLogService();
            $isMatriz = $_SESSION['is_matriz'] ?? false;
            $currentBranchId = $_SESSION['filial_id'] ?? null;

            // 1. Sefaz Config Data
            $ambiente = $_POST['ambiente'] ?? 'homologacao';
            $senha = $_POST['certificado_senha'] ?? '';
            $csc_id = $_POST['csc_id_global'] ?? '';
            $csc_token = $_POST['csc_token_global'] ?? '';
            
            $dataSefaz = [
                'ambiente' => ($isMatriz ? $ambiente : ($ambiente === 'producao' ? 1 : 2)),
                'certificado_senha' => $senha,
                'csc_id' => $csc_id,
                'csc' => $csc_token
            ];
            
            // Map keys for Branch table vs Global table
            if (!$isMatriz) {
                $dataSefaz['csc_token'] = $csc_token;
                unset($dataSefaz['csc']);
            }

            if (isset($_FILES['certificado_pfx']) && $_FILES['certificado_pfx']['error'] == 0) {
                $pfxContent = file_get_contents($_FILES['certificado_pfx']['tmp_name']);
                require_once dirname(__DIR__, 3) . '/nfce/vendor/autoload.php';
                
                try {
                    \NFePHP\Common\Certificate::readPfx($pfxContent, $senha);
                } catch (\Exception $e) {
                    $this->redirect('configuracoes.php?msg=Erro: Certificado inválido ou senha incorreta: ' . urlencode($e->getMessage()));
                }

                $dir = dirname(__DIR__, 3) . "/storage/certificados/";
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $prefix = $isMatriz ? "global_sefaz_" : "cert_filial_" . $currentBranchId . "_";
                $filename = $prefix . time() . ".pfx";
                if (move_uploaded_file($_FILES['certificado_pfx']['tmp_name'], $dir . $filename)) {
                    $dataSefaz[$isMatriz ? 'certificado_path' : 'certificado_pfx'] = $filename;
                }
            }

            if ($isMatriz) {
                // Save to Global Config (only certificate + environment - CSC fields are per-filial)
                $existing = $db->query("SELECT id FROM sefaz_config LIMIT 1")->fetch();
                if ($existing) {
                    $sql = "UPDATE sefaz_config SET ambiente = ?, certificado_senha = ?";
                    $params = [$dataSefaz['ambiente'], $dataSefaz['certificado_senha']];
                    if (isset($dataSefaz['certificado_path'])) {
                        $sql .= ", certificado_path = ?";
                        $params[] = $dataSefaz['certificado_path'];
                    }
                    $sql .= " WHERE id = ?";
                    $params[] = $existing['id'];
                    $db->prepare($sql)->execute($params);
                } else {
                    $db->prepare("INSERT INTO sefaz_config (certificado_path, certificado_senha, ambiente) VALUES (?, ?, ?)")
                       ->execute([$dataSefaz['certificado_path'] ?? null, $dataSefaz['certificado_senha'], $dataSefaz['ambiente']]);
                }
                
                // Save CSC to Matriz filial (if provided)
                if (!empty($csc_id) || !empty($csc_token)) {
                    $matrizBranch = $db->query("SELECT id FROM filiais WHERE principal = 1 LIMIT 1")->fetch();
                    if ($matrizBranch) {
                        $db->prepare("UPDATE filiais SET csc_id = ?, csc_token = ? WHERE id = ?")
                           ->execute([$csc_id, $csc_token, $matrizBranch['id']]);
                    }
                }
                
                $audit->record('Configurações Globais de Certificado Atualizadas', 'configuracoes');
            } else {
                // Save to Branch Config
                $sql = "UPDATE filiais SET ambiente = ?, certificado_senha = ?, csc_id = ?, csc_token = ?";
                $params = [(string)$dataSefaz['ambiente'], $dataSefaz['certificado_senha'], $dataSefaz['csc_id'], $dataSefaz['csc_token']];
                if (isset($dataSefaz['certificado_pfx'])) {
                    $sql .= ", certificado_pfx = ?";
                    $params[] = $dataSefaz['certificado_pfx'];
                }
                $sql .= " WHERE id = ?";
                $params[] = $currentBranchId;
                $db->prepare($sql)->execute($params);
                $audit->record("Configurações de Certificado da Filial $currentBranchId Atualizadas", 'configuracoes');
            }

            $this->redirect('configuracoes.php?msg=Configurações salvas com sucesso');
        }
    }

    public function saveFilial() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $id = $data['id'] ?? null;
            $model = new \App\Models\Filial();

            if (isset($_FILES['certificado']) && $_FILES['certificado']['error'] == 0) {
                $senha = $data['certificado_senha_filial'] ?? '';
                $pfxContent = file_get_contents($_FILES['certificado']['tmp_name']);
                require_once dirname(__DIR__, 3) . '/nfce/vendor/autoload.php';
                try {
                    \NFePHP\Common\Certificate::readPfx($pfxContent, $senha);
                } catch (\Exception $e) {
                    $this->redirect('configuracoes.php?msg=Erro no certificado da filial: ' . urlencode($e->getMessage()));
                }
                $dir = dirname(__DIR__, 3) . "/storage/certificados/";
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $filename = "cert_filial_" . ($id ?: time()) . "_" . uniqid() . ".pfx";
                if (move_uploaded_file($_FILES['certificado']['tmp_name'], $dir . $filename)) {
                    $data['certificado_pfx'] = $filename;
                    $data['certificado_senha'] = $senha;
                }
            }
            
            if (isset($data['certificado_senha_filial'])) unset($data['certificado_senha_filial']);

            $model->save($data);
            $this->redirect('configuracoes.php?msg=Unidade salva com sucesso#unidades');
        }
    }
}
