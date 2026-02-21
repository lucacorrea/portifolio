<?php
namespace App\Controllers;

use App\Models\Setting;

class SettingController extends BaseController {
    public function index() {
        $model = new Setting();
        $settings = $model->getAll();

        ob_start();
        $data = ['settings' => $settings];
        extract($data);
        require __DIR__ . "/../../../views/settings.view.php";
        $content = ob_get_clean();

        $this->render('layouts/main', [
            'title' => 'Configurações do Sistema',
            'pageTitle' => 'Parâmetros Técnicos & Identidade',
            'content' => $content
        ]);
    }

    public function save() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $model = new Setting();
            foreach ($_POST as $key => $value) {
                if ($key != 'action') {
                    $model->save($key, $value);
                }
            }
            $this->redirect('configuracoes.php?msg=Configurações salvas');
        }
    }
}
