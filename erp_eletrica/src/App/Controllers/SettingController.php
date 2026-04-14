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
            
            // Collect Identity fields for Matriz sync
            $identityData = [
                'nome' => $_POST['empresa_nome'] ?? '',
                'cnpj' => $_POST['empresa_cnpj'] ?? '',
                'telefone' => $_POST['empresa_fone'] ?? '',
                'email' => $_POST['empresa_email'] ?? ''
            ];

            $dataSefaz = [
                'ambiente' => ($ambiente === 'producao' ? 1 : 2),
                'certificado_senha' => $senha,
                'csc_id' => $csc_id,
                'csc' => $csc_token,
                'cnpj' => preg_replace('/\D/', '', $identityData['cnpj']),
                'razao_social' => $identityData['nome']
            ];

            if (isset($_FILES['certificado_pfx']) && $_FILES['certificado_pfx']['error'] == 0) {
                $pfxContent = file_get_contents($_FILES['certificado_pfx']['tmp_name']);
                require_once dirname(__DIR__, 3) . '/nfce/vendor/autoload.php';
                
                try {
                    \NFePHP\Common\Certificate::readPfx($pfxContent, $senha);
                } catch (\Exception $e) {
                    setFlash('danger', 'Erro no Certificado: ' . $e->getMessage());
                    $this->redirect('configuracoes.php');
                }

                $dir = dirname(__DIR__, 3) . "/storage/certificados/";
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $prefix = $isMatriz ? "global_sefaz_" : "cert_filial_" . $currentBranchId . "_";
                $filename = $prefix . time() . ".pfx";
                if (move_uploaded_file($_FILES['certificado_pfx']['tmp_name'], $dir . $filename)) {
                    $dataSefaz[$isMatriz ? 'certificado_path' : 'certificado_pfx'] = $filename;
                }
            }

            try {
                if ($isMatriz) {
                    // Save to Global Config
                    $existing = $db->query("SELECT id FROM sefaz_config LIMIT 1")->fetch();
                    if ($existing) {
                        $sql = "UPDATE sefaz_config SET ambiente = ?, certificado_senha = ?, csc_id = ?, csc = ?, cnpj = ?, razao_social = ?";
                        $params = [$dataSefaz['ambiente'], $dataSefaz['certificado_senha'], $dataSefaz['csc_id'], $dataSefaz['csc'], $dataSefaz['cnpj'], $dataSefaz['razao_social']];
                        if (isset($dataSefaz['certificado_path'])) {
                            $sql .= ", certificado_path = ?";
                            $params[] = $dataSefaz['certificado_path'];
                        }
                        $sql .= " WHERE id = ?";
                        $params[] = $existing['id'];
                        $db->prepare($sql)->execute($params);
                    } else {
                        $fields = ["certificado_senha", "ambiente", "csc_id", "csc", "cnpj", "razao_social"];
                        $vals = [$dataSefaz['certificado_senha'], $dataSefaz['ambiente'], $dataSefaz['csc_id'], $dataSefaz['csc'], $dataSefaz['cnpj'], $dataSefaz['razao_social']];
                        $holders = ["?", "?", "?", "?", "?", "?"];
                        
                        if (isset($dataSefaz['certificado_path'])) {
                            $fields[] = "certificado_path";
                            $vals[] = $dataSefaz['certificado_path'];
                            $holders[] = "?";
                        }
                        
                        $db->prepare("INSERT INTO sefaz_config (" . implode(',', $fields) . ") VALUES (" . implode(',', $holders) . ")")
                           ->execute($vals);
                    }
                    
                    // CRITICAL: Sync Matriz record in 'filiais' table
                    $stmtMatriz = $db->query("SELECT id FROM filiais WHERE principal = 1 LIMIT 1");
                    $matrizId = $stmtMatriz->fetchColumn();
                    if ($matrizId) {
                        $db->prepare("UPDATE filiais SET nome = ?, cnpj = ?, telefone = ?, email = ?, csc_id = ?, csc_token = ?, ambiente = ? WHERE id = ?")
                           ->execute([
                               $identityData['nome'], 
                               $identityData['cnpj'], 
                               $identityData['telefone'], 
                               $identityData['email'], 
                               $dataSefaz['csc_id'], 
                               $dataSefaz['csc'], 
                               $dataSefaz['ambiente'], 
                               $matrizId
                           ]);
                    }

                    $audit->record('Configurações Globais de Certificado & Identidade Atualizadas', 'configuracoes');
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

                setFlash('success', 'Configurações salvas com sucesso');
                $this->redirect('configuracoes.php');

            } catch (\Exception $e) {
                setFlash('danger', 'Erro ao salvar configurações: ' . $e->getMessage());
                $this->redirect('configuracoes.php');
            }
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

            try {
                $model->save($data);

                // CRITICAL: If this is the Matriz, sync to Global Config (sefaz_config)
                $stmtMatriz = $db->query("SELECT id FROM filiais WHERE principal = 1 LIMIT 1");
                $matrizId = $stmtMatriz->fetchColumn();
                
                if ($matrizId == $id) {
                    $existing = $db->query("SELECT id FROM sefaz_config LIMIT 1")->fetch();
                    $sefazData = [
                        'csc_id' => $data['csc_id'] ?? '',
                        'csc_token' => $data['csc_token'] ?? '',
                        'ambiente' => $data['ambiente'] ?? 'homologacao',
                        'certificado_path' => $data['certificado_pfx'] ?? null,
                        'certificado_senha' => $data['certificado_senha'] ?? null
                    ];

                    if ($existing) {
                        $sqlSefaz = "UPDATE sefaz_config SET csc_id = ?, csc_token = ?, ambiente = ?";
                        $paramsSefaz = [$sefazData['csc_id'], $sefazData['csc_token'], $sefazData['ambiente']];
                        
                        if ($sefazData['certificado_path']) {
                            $sqlSefaz .= ", certificado_path = ?, certificado_senha = ?";
                            $paramsSefaz[] = $sefazData['certificado_path'];
                            $paramsSefaz[] = $sefazData['certificado_senha'];
                        }
                        
                        $sqlSefaz .= " WHERE id = ?";
                        $paramsSefaz[] = $existing['id'];
                        $db->prepare($sqlSefaz)->execute($paramsSefaz);
                    } else {
                        $db->prepare("INSERT INTO sefaz_config (csc_id, csc_token, ambiente, certificado_path, certificado_senha) VALUES (?, ?, ?, ?, ?)")
                           ->execute([$sefazData['csc_id'], $sefazData['csc_token'], $sefazData['ambiente'], $sefazData['certificado_path'], $sefazData['certificado_senha']]);
                    }
                }

                setFlash('success', 'Unidade salva com sucesso');
                $this->redirect('configuracoes.php#unidades');
            } catch (\Exception $e) {
                setFlash('danger', 'Erro ao salvar unidade: ' . $e->getMessage());
                $this->redirect('configuracoes.php#unidades');
            }
        }
    }
}
