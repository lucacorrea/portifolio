<?php
namespace App\Controllers;

use App\Services\OSService;

class OSController extends BaseController {
    private $service;

    public function __construct() {
        $this->service = new OSService();
    }

    public function index() {
        $orders = (new \App\Models\OS())->getActive();
        $this->render('os_list', ['orders' => $orders]);
    }

    public function view() {
        $id = $_GET['id'] ?? null;
        if (!$id) $this->redirect('os.php');

        $os = (new \App\Models\OS())->findWithDetails($id);
        if (!$os) $this->redirect('os.php');

        $this->render('os_details', [
            'os' => $os,
            'title' => "OS #{$os['numero_os']}",
            'pageTitle' => "Ordem de Serviço #{$os['numero_os']}"
        ]);
    }

    public function save() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            validateCsrf($_POST['csrf_token'] ?? '');
            $id = $this->service->createWithSla($_POST);
            $this->redirect("os.php?action=view&id=$id");
        }
    }

    public function upload() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            validateCsrf($_POST['csrf_token'] ?? '');
            $id = $_POST['os_id'];
            $this->service->uploadPhotos($id, $_FILES['fotos']);
            $this->redirect("os.php?action=view&id=$id");
        }
    }

    public function sign() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            validateCsrf($_POST['csrf_token'] ?? '');
            $id = $_POST['os_id'];
            $this->service->saveSignature($id, $_POST['assinatura']);
            $this->redirect("os.php?action=view&id=$id");
        }
    }
}
