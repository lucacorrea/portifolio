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

        // Dados da unidade logada
        $currentBranchId = $_SESSION['filial_id'] ?? null;
        $currentBranch = null;
        if ($currentBranchId) {
            $currentBranch = $db->query("SELECT * FROM filiais WHERE id = " . (int)$currentBranchId)->fetch();
        }

        $this->render('settings', [
            'settings' => $settings,
            'sefaz' => $sefaz,
            'branches' => $branches,
            'currentBranch' => $currentBranch,
            'title' => 'Configurações de Redes & Fiscal',
            'pageTitle' => 'Painel de Gestão Centralizada'
        ]);
    }

    public function saveMatriz() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $model = new Setting();
            $db = \App\Config\Database::getInstance()->getConnection();
            $audit = new \App\Services\AuditLogService();

            // 1. Save Corporate Settings (Empresa Nome, CNPJ, etc.)
            foreach ($_POST as $key => $value) {
                if (in_array($key, ['empresa_nome', 'empresa_cnpj', 'empresa_fone', 'empresa_email', 'estoque_min_default'])) {
                    $model->set($key, $value);
                }
            }

            // 2. Save Sefaz Global Config
            $ambiente = $_POST['ambiente'] ?? 'homologacao';
            $senha = $_POST['certificado_senha'] ?? '';
            $csc_id = $_POST['csc_id_global'] ?? '';
            $csc_token = $_POST['csc_token_global'] ?? '';
            
            $dataSefaz = [
                'ambiente' => $ambiente,
                'certificado_senha' => $senha,
                'csc_id' => $csc_id,
                'csc' => $csc_token
            ];

            if (isset($_FILES['certificado_pfx']) && $_FILES['certificado_pfx']['error'] == 0) {
                $pfxContent = file_get_contents($_FILES['certificado_pfx']['tmp_name']);
                require_once dirname(__DIR__, 3) . '/nfce/vendor/autoload.php';
                
                try {
                    $certificate = \NFePHP\Common\Certificate::readPfx($pfxContent, $senha);
                } catch (\Exception $e) {
                    $this->redirect('configuracoes.php?msg=Erro: Certificado inválido ou senha incorreta: ' . urlencode($e->getMessage()));
                }

                $dir = dirname(__DIR__, 3) . "/storage/certificados/";
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $filename = "global_sefaz_" . time() . ".pfx";
                if (move_uploaded_file($_FILES['certificado_pfx']['tmp_name'], $dir . $filename)) {
                    $dataSefaz['certificado_path'] = $filename;
                }
            }

            $existing = $db->query("SELECT id FROM sefaz_config LIMIT 1")->fetch();
            if ($existing) {
                $sql = "UPDATE sefaz_config SET ambiente = ?, certificado_senha = ?, csc_id = ?, csc = ?";
                $params = [$dataSefaz['ambiente'], $dataSefaz['certificado_senha'], $dataSefaz['csc_id'], $dataSefaz['csc']];
                if (isset($dataSefaz['certificado_path'])) {
                    $sql .= ", certificado_path = ?";
                    $params[] = $dataSefaz['certificado_path'];
                }
                $sql .= " WHERE id = ?";
                $params[] = $existing['id'];
                $db->prepare($sql)->execute($params);
            } else {
                $db->prepare("INSERT INTO sefaz_config (certificado_path, certificado_senha, ambiente, csc_id, csc) VALUES (?, ?, ?, ?, ?)")
                   ->execute([$dataSefaz['certificado_path'] ?? null, $dataSefaz['certificado_senha'], $dataSefaz['ambiente'], $dataSefaz['csc_id'], $dataSefaz['csc']]);
            }

            $audit->record('Configurações da Matriz Atualizadas', 'configuracoes');
            $this->redirect('configuracoes.php?msg=Configurações da Matriz salvas com sucesso');
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
