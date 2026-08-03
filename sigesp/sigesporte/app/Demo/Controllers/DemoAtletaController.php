<?php
declare(strict_types=1);

namespace Sigesp\Demo\Controllers;

use Sigesp\Core\{Request, Response, View};
use Sigesp\Demo\{AtletasDemoData, DemoData};

final class DemoAtletaController
{
    public function index(Request $request): Response
    {
        $filters = ['q' => (string) $request->query('q', ''), 'status' => (string) $request->query('status', '')];
        $perPage = (int) $request->query('por_pagina', 15);
        return $this->view('atletas/index', [
            'result' => AtletasDemoData::paginate($filters, (int) $request->query('page', 1), $perPage),
            'filters' => $filters,
            'page' => DemoData::page('atletas'),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('atletas/form', ['title' => 'Novo atleta', 'atleta' => null, 'mode' => 'create']);
    }

    public function edit(Request $request, string $id): Response
    {
        return $this->view('atletas/form', ['title' => 'Editar atleta', 'atleta' => $this->athlete($id), 'mode' => 'edit']);
    }

    public function show(Request $request, string $id): Response
    {
        return $this->view('atletas/show', ['atleta' => $this->athlete($id), 'section' => 'perfil']);
    }

    public function documents(Request $request, string $id): Response
    {
        return $this->view('atletas/documentos', ['atleta' => $this->athlete($id), 'section' => 'documentos']);
    }

    public function wallet(Request $request, string $id): Response
    {
        return $this->view('atletas/carteira', ['atleta' => $this->athlete($id), 'section' => 'carteira']);
    }

    public function store(Request $request): Response
    {
        return Response::redirect('/atletas/1?simulated=1');
    }

    public function update(Request $request, string $id): Response
    {
        return Response::redirect('/atletas/' . max(1, (int) $id) . '?simulated=1');
    }

    private function athlete(string $id): array
    {
        return AtletasDemoData::find(max(1, (int) $id)) ?? AtletasDemoData::all()[0];
    }

    private function view(string $view, array $data): Response
    {
        $data['demoMode'] = true;
        $data['demoUser'] = ['nome' => 'Marcos Oliveira', 'perfil' => 'Administrador do Sistema'];
        return new Response(View::render($view, $data));
    }
}
