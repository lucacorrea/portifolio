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
                $dir = dirname(__DIR__, 3) . "/storage/certificados/";
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                
                $filename = "cert_" . $data['id'] . "_" . uniqid() . ".pfx";
                if (move_uploaded_file($_FILES['certificado']['tmp_name'], $dir . $filename)) {
                    $data['certificado_pfx'] = $filename;
                }
            }

            $model->save($data);
            $this->redirect('filiais.php?msg=Filial atualizada com sucesso');
        }
    }
}
