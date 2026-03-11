<?php
namespace App\Controllers;

use App\Models\Filial;

class BranchController extends BaseController {
    public function index() {
        $model = new Filial();
        $isMatriz = $_SESSION['is_matriz'] ?? false;
        
        // Se não for matriz, só vê a própria
        $branches = $model->getAllBranches($isMatriz ? null : $_SESSION['filial_id']);

        $this->render('branches', [
            'branches' => $branches,
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

            // Handle Certificate Upload
            if (isset($_FILES['certificado']) && $_FILES['certificado']['error'] == 0) {
                $pfxContent = file_get_contents($_FILES['certificado']['tmp_name']);
                $senha = $data['certificado_senha'] ?? '';
                $certs = [];
                
                if (!openssl_pkcs12_read($pfxContent, $certs, $senha)) {
                    $this->redirect('filiais.php?msg=Erro: Senha incorreta ou arquivo de certificado inválido');
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

            // Unificar padrão de senha (Base64) para consistência entre Global e Filial
            if (!empty($data['certificado_senha'])) {
                $data['certificado_senha'] = base64_encode($data['certificado_senha']);
            }

            $model->save($data);
            $msg = $id ? 'Unidade atualizada com sucesso' : 'Nova unidade registrada com sucesso';
            $this->redirect('filiais.php?msg=' . $msg);
        }
    }
}
