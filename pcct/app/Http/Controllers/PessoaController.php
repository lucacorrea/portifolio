<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PessoaController extends Controller
{
    public function create()
    {
        return view('professor.cadastro');
    }

    public function store(Request $request)
    {
        dd($request->all());
    }


}
