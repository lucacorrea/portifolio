@extends('layouts.app')
@section('title', 'Meu painel')
@section('content')
<div class="page-top">
    <div><span class="eyebrow">Área de {{ $user->role === 'estudante' ? 'estudante' : $user->role }}</span><h1>Olá, {{ $user->name }}!</h1><p>Bem-vindo(a) ao PCCT do Ensino Médio.</p></div>
</div>
<div class="stats">
    @foreach($stats as $rotulo => $valor)
        <div class="panel stat"><span>{{ $rotulo }}</span><strong>{{ $valor }}</strong></div>
    @endforeach
</div>
<section class="section">
    <h2>O que deseja fazer?</h2>
    <div class="action-grid">
        <a class="panel action-card" href="{{ route('catalogo') }}"><strong>Explorar biblioteca</strong><span>Pesquisar materiais por disciplina, série, tema ou BNCC.</span></a>
        @if($user->role === 'professor')
            <a class="panel action-card" href="{{ route('professor.materiais.index') }}"><strong>Gerenciar meus materiais</strong><span>Criar, editar, publicar ou retirar conteúdo.</span></a>
            <a class="panel action-card" href="{{ route('professor.materiais.create') }}"><strong>Adicionar material</strong><span>Publique vídeos, slides, documentos e atividades.</span></a>
        @elseif($user->role === 'admin')
            <a class="panel action-card" href="{{ route('admin.index') }}"><strong>Administração</strong><span>Gerenciar professores, alunos, instituições, disciplinas e habilidades.</span></a>
        @else
            <div class="panel action-card"><strong>Aprenda no seu ritmo</strong><span>Os conteúdos publicados pelos professores ficam disponíveis na biblioteca.</span></div>
        @endif
    </div>
</section>
@endsection
