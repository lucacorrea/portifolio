@extends('layouts.app')
@section('title', 'Biblioteca de materiais')
@section('content')
<section class="hero">
    <div>
        <span class="eyebrow">Conhecimento em um só lugar</span>
        <h1>Biblioteca de aprendizagem do Ensino Médio</h1>
        <p>Encontre vídeos, apresentações, documentos, atividades e outros recursos alinhados às disciplinas e habilidades da BNCC.</p>
        <a class="button light" href="#filtros">Explorar materiais</a>
    </div>
    <div class="hero-number"><span>{{ $materiais->total() }}</span><small>material(is) disponível(is)</small></div>
</section>

<section id="filtros" class="section">
    <div class="section-heading">
        <div><span class="eyebrow">Acervo</span><h2>Materiais publicados</h2></div>
    </div>
    <form class="filters panel" method="GET" action="{{ route('catalogo') }}">
        <div class="field flex-2">
            <label for="q">Pesquisar conteúdo</label>
            <input id="q" name="q" value="{{ request('q') }}" maxlength="120" placeholder="Título, assunto, palavra-chave...">
        </div>
        <div class="field">
            <label for="disciplina">Disciplina</label>
            <select id="disciplina" name="disciplina">
                <option value="">Todas</option>
                @foreach($disciplinas as $disciplina)
                    <option value="{{ $disciplina->id }}" @selected(request('disciplina') == $disciplina->id)>{{ $disciplina->nome }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="serie">Série</label>
            <select id="serie" name="serie">
                <option value="">Todas</option>
                @foreach([1, 2, 3] as $serie)
                    <option value="{{ $serie }}" @selected(request('serie') == $serie)>{{ $serie }}º ano</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="tipo">Formato</label>
            <select id="tipo" name="tipo">
                <option value="">Todos</option>
                @foreach(['video'=>'Vídeo','slide'=>'Slides','documento_pdf'=>'Documento','podcast_audio'=>'Áudio','quiz_avaliacao'=>'Atividade','mapa_mental'=>'Mapa mental','outro'=>'Outro'] as $valor=>$rotulo)
                    <option value="{{ $valor }}" @selected(request('tipo') === $valor)>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="bncc">Código BNCC</label>
            <input id="bncc" name="bncc" maxlength="40" value="{{ request('bncc') }}" placeholder="Ex.: EM13MAT101">
        </div>
        <div class="filter-actions">
            <button class="button primary" type="submit">Filtrar</button>
            <a class="button outline" href="{{ route('catalogo') }}">Limpar</a>
        </div>
    </form>

    @if($materiais->isEmpty())
        <div class="empty-state panel">
            <h3>Nenhum material encontrado</h3>
            <p>Tente outros filtros. Professores podem publicar novos conteúdos pelo painel.</p>
        </div>
    @else
        <div class="cards">
            @foreach($materiais as $material)
                <article class="material-card panel">
                    <a class="material-cover" href="{{ route('materiais.show', $material) }}">
                        @if($material->imagem)
                            <img src="{{ route('materiais.midia', [$material, 'capa']) }}" alt="Capa de {{ $material->titulo }}" loading="lazy">
                        @else
                            <span class="cover-placeholder">PCCT</span>
                        @endif
                    </a>
                    <div class="card-body">
                        <div class="card-meta"><span>{{ $material->disciplina->nome ?? $material->nome_disciplina }}</span><span>{{ $material->ano_escolar }}º ano</span></div>
                        <h3><a href="{{ route('materiais.show', $material) }}">{{ $material->titulo }}</a></h3>
                        <p>{{ \Illuminate\Support\Str::limit($material->descricao, 125) }}</p>
                        <div class="card-footer"><span class="pill">{{ str_replace('_', ' ', $material->tipo_objeto) }}</span><a href="{{ route('materiais.show', $material) }}">Ver detalhes</a></div>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="pagination-bar">
            <span>Mostrando {{ $materiais->firstItem() }}–{{ $materiais->lastItem() }} de {{ $materiais->total() }}</span>
            <div>
                @if($materiais->previousPageUrl())<a class="button outline small" href="{{ $materiais->previousPageUrl() }}">Anterior</a>@endif
                @if($materiais->nextPageUrl())<a class="button outline small" href="{{ $materiais->nextPageUrl() }}">Próxima</a>@endif
            </div>
        </div>
    @endif
</section>
@endsection
