<?php
namespace App\Controllers;

use App\Models\OS;
use App\Models\Client;

class OSController extends BaseController {
    public function index() {
        $model = new OS();
        $orders = $model->getActive();

        ob_start();
        $data = ['orders' => $orders];
        extract($data);
        require __DIR__ . "/../../../views/os_list.view.php";
        $content = ob_get_clean();

        $this->render('layouts/main', [
            'title' => 'Gestão de Ordens de Serviço',
            'pageTitle' => 'Workflow Técnico e Operacional',
            'content' => $content
        ]);
    }

    public function view($id) {
        $model = new OS();
        $os = $model->findWithDetails($id);

        if (!$os) $this->redirect('os.php');

        ob_start();
        require __DIR__ . "/../../../views/os_details.view.php";
        $content = ob_get_clean();

        $this->render('layouts/main', [
            'title' => "OS #{$os['numero_os']}",
            'pageTitle' => "Detalhamento da Ordem de Serviço",
            'content' => $content
        ]);
    }

    public function save() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $model = new OS();
            $id = $model->create($_POST);
            $this->redirect("os.php?action=view&id=$id");
        }
    }
}
