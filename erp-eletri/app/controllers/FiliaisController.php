<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Filial;
use App\Middleware\AuthMiddleware;

class FiliaisController extends Controller
{
    public function __construct()
    {
        (new AuthMiddleware())->handle();
    }

    public function index()
    {
        $filialModel = new Filial();
        $filiais = $filialModel->getAll();
        
        $this->view('filiais/index', ['filiais' => $filiais]);
    }
}
