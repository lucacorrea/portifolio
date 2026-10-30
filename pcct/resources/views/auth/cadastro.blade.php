@extends('layouts.app')
@section('title', 'Cadastro de estudante')
@section('content')
<div class="auth-wrap">
    <div class="auth-intro">
        <span class="eyebrow">Ensino Médio</span>
        <h1>Comece a aprender</h1>
        <p>Crie uma conta de estudante para consultar materiais e habilidades da BNCC. Professores recebem acesso pelo administrador da instituição.</p>
    </div>
    <form class="panel form-card" method="POST" action="{{ route('cadastro.store') }}">
        @csrf
        <h2>Cadastro do estudante</h2>
        <label for="name">Nome completo</label>
        <input id="name" name="name" value="{{ old('name') }}" maxlength="255" required>
        <label for="instituicao_id">Instituição (opcional)</label>
        <select id="instituicao_id" name="instituicao_id">
            <option value="">Não informar</option>
            @foreach($instituicoes as $instituicao)
                <option value="{{ $instituicao->id }}" @selected(old('instituicao_id') == $instituicao->id)>{{ $instituicao->nome }}</option>
            @endforeach
        </select>
        <label for="email">E-mail</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
        <label for="password">Senha (mínimo 8 caracteres)</label>
        <input id="password" type="password" name="password" autocomplete="new-password" minlength="8" required>
        <label for="password_confirmation">Confirme a senha</label>
        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
        <button class="button primary wide" type="submit">Criar minha conta</button>
        <p class="text-small">Já tem conta? <a href="{{ route('login') }}">Entrar</a>.</p>
    </form>
</div>
@endsection
