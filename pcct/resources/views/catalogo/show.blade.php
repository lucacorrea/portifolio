@extends('layouts.app')
@section('title', $material->titulo)
@section('content')
<div class="breadcrumbs"><a href="{{ route('catalogo') }}">Biblioteca</a> / Material</div>
<article class="detail-layout">
    <div class="panel detail-main">
        <div class="tag-list"><span class="pill">{{ $material->disciplina->nome ?? $material->nome_disciplina }}</span><span class="pill">{{ $material->ano_escolar }}º ano</span><span class="pill">{{ str_replace('_', ' ', $material->tipo_objeto) }}</span></div>
        <h1>{{ $material->titulo }}</h1>
        <p class="lead">{{ $material->tematica }}</p>
        @if($material->imagem)
            <img class="detail-image" src="{{ route('materiais.midia', [$material, 'capa']) }}" alt="Imagem de {{ $material->titulo }}">
        @endif
        <h2>Sobre este conteúdo</h2>
        <div class="preline">{{ $material->descricao }}</div>
        @if($material->tags)
            <p class="muted"><strong>Palavras-chave:</strong> {{ $material->tags }}</p>
        @endif
    </div>
    <aside class="panel detail-aside">
        <h2>Acessar material</h2>
        @if($material->arquivo)
            <a class="button primary wide" target="_blank" rel="noopener noreferrer" href="{{ route('materiais.midia', [$material, 'arquivo']) }}">Abrir arquivo</a>
        @endif
        @if($material->link && in_array(parse_url($material->link, PHP_URL_SCHEME), ['http', 'https'], true))
            <a class="button outline wide" target="_blank" rel="noopener noreferrer" href="{{ $material->link }}">Abrir link externo</a>
        @endif
        <hr>
        <dl>
            <dt>Professor(a)</dt><dd>{{ $material->professor->user->name ?? 'Não informado' }}</dd>
            <dt>Série</dt><dd>{{ $material->ano_escolar }}º ano do Ensino Médio</dd>
            <dt>Disciplina</dt><dd>{{ $material->disciplina->nome ?? $material->nome_disciplina }}</dd>
        </dl>
        <h3>Habilidades da BNCC</h3>
        @forelse($material->habilidades as $habilidade)
            <div class="skill"><strong>{{ $habilidade->codigo }}</strong><span>{{ $habilidade->nome }}</span></div>
        @empty
            <p class="muted text-small">Nenhuma habilidade associada.</p>
        @endforelse
    </aside>
</article>
@endsection
