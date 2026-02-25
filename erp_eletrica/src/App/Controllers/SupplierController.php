<?php
namespace App\Controllers;

use App\Models\Supplier;

class SupplierController extends BaseController {
    public function index() {
        $model = new Supplier();
        $suppliers = $model->all();

        $this->render('suppliers', [
            'suppliers' => $suppliers,
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
