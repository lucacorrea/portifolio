<?php
declare(strict_types=1);
namespace Sigesp\Modules\Atletas\Controllers;
use Sigesp\Core\{Controller,Csrf,Flash,Request,Response};
use Sigesp\Modules\Atletas\{Repositories\AtletaRepository,Services\AtletaService};
final class AtletaController extends Controller
{
    public function index(Request $request): Response { $this->authorize('atletas.visualizar'); $perPage = in_array((int) $request->query('por_pagina', 15), [15,30,50], true) ? (int) $request->query('por_pagina', 15) : 15; $filters=['q'=>(string)$request->query('q',''),'status'=>(string)$request->query('status','')]; $result=(new AtletaRepository())->paginate($filters,(int)$request->query('page',1),$perPage); return $this->render('atletas/index', compact('result','filters')); }
    public function create(Request $request): Response { $this->authorize('atletas.criar'); return $this->render('atletas/form', ['title' => 'Novo atleta']); }
    public function store(Request $request): Response { $this->authorize('atletas.criar'); if (!Csrf::validate($request->input('_token'))) { Flash::add('error','Solicitação expirada.'); return Response::redirect('/atletas/novo'); } try { $id=(new AtletaService())->create($request->all()); Flash::add('success','Atleta cadastrado com sucesso.'); return Response::redirect('/atletas/'.$id); } catch (\Throwable $e) { Flash::add('error',$e instanceof \InvalidArgumentException ? $e->getMessage() : 'Não foi possível salvar o atleta.'); return Response::redirect('/atletas/novo'); } }
    public function show(Request $request, string $id): Response { $this->authorize('atletas.visualizar'); $atleta=(new AtletaRepository())->find((int)$id); if (!$atleta) return $this->render('errors/404',['title'=>'Atleta não encontrado']); return $this->render('atletas/show',compact('atleta')); }
}
