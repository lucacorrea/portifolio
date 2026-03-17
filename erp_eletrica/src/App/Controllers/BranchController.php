<?php
namespace App\Controllers;

use App\Models\Filial;

class BranchController extends BaseController {
    public function index() {
        $model = new Filial();
        $isMatriz = $_SESSION['is_matriz'] ?? false;
        
        // Se não for matriz, só vê a própria
        $branches = $model->getAllBranches($isMatriz ? null : $_SESSION['filial_id']);

        // Fetch Global Certificate info
        $db = \App\Config\Database::getInstance()->getConnection();
        $globalCert = $db->query("SELECT certificado_path FROM sefaz_config LIMIT 1")->fetchColumn();

        $this->render('branches', [
            'branches' => $branches,
            'globalCert' => $globalCert,
            'title' => 'Gestão de Filiais & Unidades',
            'pageTitle' => 'Administração de Unidades de Negócio'
        ]);
    }

    public function save() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = $_POST;
            $id = $data['id'] ?? null;

            // Security check
            if (!($_SESSION['is_matriz'] ?? false) && $id != $_SESSION['filial_id']) {
                $this->redirect('filiais.php?msg=Erro: Acesso negado para gerenciar esta unidade');
            }

            $model = new \App\Models\Filial();

            // Handle Certificate Upload exactly like acainhadinhos
            if (isset($_FILES['certificado']) && $_FILES['certificado']['error'] == 0) {
                
                $senha = $data['certificado_senha'] ?? '';
                if ($senha === '') {
                    $this->redirect('filiais.php?msg=Erro: Informe a senha do certificado para validar o novo arquivo.');
                    return;
                }

                $pfxContent = file_get_contents($_FILES['certificado']['tmp_name']);
                
                // NFePHP validation exactly as in acainhadinhos
                require_once dirname(__DIR__, 3) . '/nfce/vendor/autoload.php';
                
                try {
                    $certificate = \NFePHP\Common\Certificate::readPfx($pfxContent, $senha);
                    unset($certificate); // Validated successfully
                } catch (\Exception $e) {
                    $this->redirect('filiais.php?msg=Erro: Certificado inválido ou senha incorreta: ' . urlencode($e->getMessage()));
                    return;
                }

                $dir = dirname(__DIR__, 3) . "/storage/certificados/";
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                
                $refId = $id ?: "new_" . time();
                $filename = "cert_" . $refId . "_" . uniqid() . ".pfx";
                if (move_uploaded_file($_FILES['certificado']['tmp_name'], $dir . $filename)) {
                    $data['certificado_pfx'] = $filename;
                }
            }

            // Explicity DO NOT base64 encode the password, just like Açaidinhos
            if (empty($data['certificado_senha'])) {
                 unset($data['certificado_senha']); // Do not update blank passwords if they are empty
            }

            $model->save($data);
            $msg = $id ? 'Unidade atualizada com sucesso' : 'Nova unidade registrada com sucesso';
            $this->redirect('filiais.php?msg=' . $msg);
        }
    }
}
