<?php
namespace App\Controllers;

use App\Models\Supplier;

class SupplierController extends BaseController {
    public function index() {
        $model = new Supplier();
        $page = (int)($_GET['page'] ?? 1);
        $pagination = $model->paginate(6, $page);
        $suppliers = $pagination['data'];

        $this->render('suppliers', [
            'suppliers' => $suppliers,
            'pagination' => $pagination,
            'title' => 'Gestão de Fornecedores',
            'pageTitle' => 'Parceiros e Cadeia de Suprimentos'
        ]);
    }

    public function save() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $model = new Supplier();
            $model->save($_POST);
            $this->redirect('fornecedores.php?msg=Fornecedor salvo');
        }
    }
}
