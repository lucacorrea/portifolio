@extends('layouts.app')
@section('title', 'Administração')
@section('content')
<div class="page-top"><span class="eyebrow">Painel administrativo</span><h1>Gestão do PCCT</h1><p>Organize os acessos e os conteúdos do Ensino Médio.</p></div>
<div class="admin-grid">
    <section class="panel editor" id="usuarios">
        <h2>Novo professor ou estudante</h2>
        <p class="muted text-small">Administradores são criados somente pelo comando seguro no terminal.</p>
        <form method="POST" action="{{ route('admin.usuarios.store') }}" class="stack-form">@csrf
            <div class="form-grid">
                <div class="field"><label>Nome completo *</label><input name="name" value="{{ old('name') }}" required></div>
                <div class="field"><label>E-mail *</label><input type="email" name="email" value="{{ old('email') }}" required></div>
                <div class="field"><label>Perfil *</label><select name="role" required><option value="professor">Professor</option><option value="estudante">Estudante</option></select></div>
                <div class="field"><label>Instituição</label><select name="instituicao_id"><option value="">Não vinculada</option>@foreach($instituicoes as $item)<option value="{{ $item->id }}">{{ $item->nome }}</option>@endforeach</select></div>
                <div class="field"><label>Formação (professor)</label><input name="formacao" maxlength="150"></div>
                <div class="field"><label>Senha *</label><input type="password" name="password" minlength="8" required></div>
                <div class="field"><label>Confirmar senha *</label><input type="password" name="password_confirmation" minlength="8" required></div>
            </div>
            <button class="button primary" type="submit">Cadastrar usuário</button>
        </form>
        <h3>Contas cadastradas</h3>
        <div class="table-scroll"><table><thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th></tr></thead><tbody>
            @foreach($usuarios as $usuario)<tr><td>{{ $usuario->name }}</td><td>{{ $usuario->email }}</td><td>{{ $usuario->role ?? 'Sem perfil' }}</td></tr>@endforeach
        </tbody></table></div>
        <div class="pagination-bar">
            @if($usuarios->previousPageUrl())<a href="{{ $usuarios->previousPageUrl() }}">Anterior</a>@endif
            @if($usuarios->nextPageUrl())<a href="{{ $usuarios->nextPageUrl() }}">Próxima</a>@endif
        </div>
    </section>
    <div class="admin-stack">
        <section class="panel editor">
            <h2>Instituições</h2>
            <form method="POST" action="{{ route('admin.instituicoes.store') }}" class="stack-form">@csrf
                <div class="field"><label>Nome *</label><input name="nome" required maxlength="255" placeholder="Ex.: IFAM Campus Coari"></div>
                <div class="field"><label>Localidade</label><input name="localidade" maxlength="255"></div>
                <div class="field"><label>Descrição</label><textarea name="descricao" rows="2"></textarea></div>
                <button class="button primary" type="submit">Adicionar instituição</button>
            </form>
            <p class="muted text-small">{{ $instituicoes->count() }} instituição(ões) cadastrada(s)</p>
        </section>
        <section class="panel editor">
            <h2>Disciplinas</h2>
            <form method="POST" action="{{ route('admin.disciplinas.store') }}" class="stack-form">@csrf
                <div class="field"><label>Nome *</label><input name="nome" maxlength="255" required></div>
                <div class="field"><label>Área de conhecimento *</label><select name="area_conhecimento" required>
                    @foreach(['Linguagens','Matemática','Ciências da Natureza','Ciências Humanas','Formação Técnica'] as $area)<option value="{{ $area }}">{{ $area }}</option>@endforeach
                </select></div>
                <button class="button primary" type="submit">Adicionar disciplina</button>
            </form>
            <p class="muted text-small">{{ $disciplinas->count() }} disciplina(s) cadastrada(s)</p>
        </section>
        <section class="panel editor">
            <h2>Habilidades da BNCC</h2>
            <form method="POST" action="{{ route('admin.habilidades.store') }}" class="stack-form">@csrf
                <div class="field"><label>Código oficial *</label><input name="codigo" maxlength="40" placeholder="Ex.: EM13MAT101" required></div>
                <div class="field"><label>Nome *</label><input name="nome" maxlength="255" required></div>
                <div class="field"><label>Disciplina *</label><select name="disciplina_id" required><option value="">Selecione</option>
                    @foreach($disciplinas as $disciplina)<option value="{{ $disciplina->id }}">{{ $disciplina->nome }}</option>@endforeach
                </select></div>
                <div class="field"><label>Descrição</label><textarea name="descricao" rows="3"></textarea></div>
                <div class="field"><label>Ano/série (opcional)</label><input name="ano_escolar" maxlength="80" placeholder="Ex.: Ensino Médio"></div>
                <button class="button primary" type="submit">Adicionar habilidade</button>
            </form>
            <p class="muted text-small">{{ $habilidades->count() }} habilidade(s) cadastrada(s). Utilize apenas códigos e descrições conferidos na BNCC oficial.</p>
        </section>
    </div>
</div>
@endsection
