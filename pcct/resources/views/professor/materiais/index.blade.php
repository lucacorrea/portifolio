@extends('layouts.app')
@section('title', 'Meus materiais')
@section('content')
<div class="page-top split">
    <div><span class="eyebrow">Área do professor</span><h1>Meus materiais</h1><p>Organize seus recursos didáticos do Ensino Médio.</p></div>
    <a class="button primary" href="{{ route('professor.materiais.create') }}">+ Novo material</a>
</div>
<div class="panel table-panel">
    @if($materiais->isEmpty())
        <div class="empty-state"><h3>Você ainda não criou materiais.</h3><p>Comece cadastrando seu primeiro objeto de aprendizagem.</p></div>
    @else
        <div class="table-scroll">
            <table>
                <thead><tr><th>Título</th><th>Disciplina</th><th>Série</th><th>Publicação</th><th>Ações</th></tr></thead>
                <tbody>
                @foreach($materiais as $material)
                    <tr>
                        <td><strong>{{ $material->titulo }}</strong><small>{{ $material->created_at?->format('d/m/Y') }}</small></td>
                        <td>{{ $material->disciplina->nome ?? $material->nome_disciplina }}</td>
                        <td>{{ $material->ano_escolar }}º ano</td>
                        <td><span @class(['pill', 'pill-green' => $material->status === 'publicado'])>{{ $material->status }}</span></td>
                        <td class="actions">
                            <a href="{{ route('materiais.show', $material) }}">Visualizar</a>
                            <a href="{{ route('professor.materiais.edit', $material) }}">Editar</a>
                            <form method="POST" action="{{ route('professor.materiais.destroy', $material) }}" onsubmit="return confirm('Excluir definitivamente este material?')">
                                @csrf @method('DELETE')
                                <button class="text-danger" type="submit">Excluir</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination-bar">
            <span>{{ $materiais->total() }} material(is)</span>
            <div>
                @if($materiais->previousPageUrl())<a class="button outline small" href="{{ $materiais->previousPageUrl() }}">Anterior</a>@endif
                @if($materiais->nextPageUrl())<a class="button outline small" href="{{ $materiais->nextPageUrl() }}">Próxima</a>@endif
            </div>
        </div>
    @endif
</div>
@endsection
