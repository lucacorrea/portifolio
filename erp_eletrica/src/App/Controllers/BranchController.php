<?php
namespace App\Controllers;

use App\Models\Filial;

class BranchController extends BaseController {
    public function index() {
        $model = new Filial();
        $branches = $model->all();

        $this->render('branches', [
            'branches' => $branches,
            'title' => 'Gestão de Filiais & Unidades',
            'pageTitle' => 'Administração de Unidades de Negócio'
        ]);
    }

    public function save() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $model = new \App\Models\Filial();
            $data = $_POST;

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
