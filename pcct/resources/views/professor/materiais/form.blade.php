@extends('layouts.app')
@section('title', $material->exists ? 'Editar material' : 'Novo material')
@section('content')
@php
    $editing = $material->exists;
    $selecionadas = array_map('intval', old('habilidades', $material->habilidades->pluck('id')->all()));
@endphp
<div class="page-top">
    <a class="back-link" href="{{ route('professor.materiais.index') }}">← Voltar aos meus materiais</a>
    <h1>{{ $editing ? 'Editar material' : 'Cadastrar objeto de aprendizagem' }}</h1>
    <p>Conteúdo destinado ao Ensino Médio. Salve como rascunho ou publique na biblioteca.</p>
</div>
<form class="panel editor" method="POST" enctype="multipart/form-data" action="{{ $editing ? route('professor.materiais.update', $material) : route('professor.materiais.store') }}">
    @csrf
    @if($editing) @method('PUT') @endif
    <h2>1. Identificação pedagógica</h2>
    <div class="form-grid">
        <div class="field flex-2"><label for="titulo">Título *</label><input id="titulo" name="titulo" maxlength="255" required value="{{ old('titulo', $material->titulo) }}" placeholder="Ex.: Funções do 1º grau"></div>
        <div class="field"><label for="disciplina_id">Disciplina *</label><select id="disciplina_id" name="disciplina_id" required>
            <option value="">Selecione</option>
            @foreach($disciplinas as $disciplina)
                <option value="{{ $disciplina->id }}" @selected(old('disciplina_id', $material->disciplina_id) == $disciplina->id)>{{ $disciplina->nome }}</option>
            @endforeach
        </select></div>
        <div class="field"><label for="ano_escolar">Série *</label><select id="ano_escolar" name="ano_escolar" required>
            <option value="">Selecione</option>
            @foreach(['1'=>'1º ano','2'=>'2º ano','3'=>'3º ano'] as $valor => $titulo)
                <option value="{{ $valor }}" @selected(old('ano_escolar', $material->ano_escolar) == $valor)>{{ $titulo }}</option>
            @endforeach
        </select></div>
        <div class="field flex-2"><label for="tematica">Tema *</label><input id="tematica" name="tematica" required maxlength="255" value="{{ old('tematica', $material->tematica) }}" placeholder="Ex.: Álgebra"></div>
        <div class="field"><label for="tipo_objeto">Formato *</label><select id="tipo_objeto" name="tipo_objeto" required>
            <option value="">Selecione</option>
            @foreach(['video'=>'Vídeo','slide'=>'Slides','documento_pdf'=>'Documento/PDF','podcast_audio'=>'Áudio','quiz_avaliacao'=>'Quiz/Avaliação','mapa_mental'=>'Mapa mental','outro'=>'Outro'] as $valor=>$titulo)
                <option value="{{ $valor }}" @selected(old('tipo_objeto', $material->tipo_objeto) === $valor)>{{ $titulo }}</option>
            @endforeach
        </select></div>
        <div class="field"><label for="status">Visibilidade *</label><select id="status" name="status">
            <option value="rascunho" @selected(old('status', $material->status ?? 'rascunho') === 'rascunho')>Rascunho (privado)</option>
            <option value="publicado" @selected(old('status', $material->status) === 'publicado')>Publicado (visível na biblioteca)</option>
        </select></div>
    </div>
    <h2>2. Descrição e habilidades</h2>
    <div class="field"><label for="descricao">Descrição do conteúdo *</label><textarea id="descricao" name="descricao" rows="6" maxlength="10000" required>{{ old('descricao', $material->descricao) }}</textarea></div>
    <div class="field"><label for="habilidades">Habilidades da BNCC (opcional)</label>
        <select id="habilidades" name="habilidades[]" multiple size="6">
            @foreach($habilidades as $habilidade)
                <option value="{{ $habilidade->id }}" data-disciplina="{{ $habilidade->disciplina_id }}" @selected(in_array($habilidade->id, $selecionadas))>{{ $habilidade->codigo }} · {{ $habilidade->nome }}</option>
            @endforeach
        </select>
        <small>Use Ctrl para selecionar várias. Cadastre códigos oficiais da BNCC no painel administrativo. Somente habilidades da disciplina escolhida serão aceitas.</small>
    </div>
    <div class="field"><label for="tags">Palavras-chave</label><input id="tags" name="tags" maxlength="255" value="{{ old('tags', $material->tags) }}" placeholder="Ex.: matemática, funções, gráficos"></div>
    <h2>3. Mídia e acesso</h2>
    <div class="form-grid">
        <div class="field"><label for="link">Link externo (http/https)</label><input id="link" type="url" name="link" maxlength="255" value="{{ old('link', $material->link) }}" placeholder="https://..."></div>
        <div class="field"><label for="arquivo">Arquivo (máx. 10 MB)</label><input id="arquivo" type="file" name="arquivo" accept=".pdf,.ppt,.pptx,.doc,.docx,.mp3,.mp4,.webm">
            @if($material->arquivo)<small>Arquivo atual: {{ basename($material->arquivo) }}. Enviar outro arquivo substitui o anterior.</small>@endif
        </div>
        <div class="field"><label for="imagem">Imagem de capa (máx. 2 MB)</label><input id="imagem" type="file" name="imagem" accept="image/png,image/jpeg,image/webp,image/gif">
            @if($material->imagem)<small>Uma nova imagem substitui a capa atual.</small>@endif
        </div>
    </div>
    <p class="muted text-small">Forneça ao menos um link ou um arquivo. Os arquivos serão acessíveis na biblioteca quando o material estiver publicado; para conteúdo privado, não anexe dados sensíveis.</p>
    <div class="form-actions"><a class="button outline" href="{{ route('professor.materiais.index') }}">Cancelar</a><button class="button primary" type="submit">{{ $editing ? 'Salvar alterações' : 'Salvar material' }}</button></div>
</form>
@endsection
