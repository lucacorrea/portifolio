@extends('layouts.app')
@section('title', 'Entrar')
@section('content')
<div class="auth-wrap">
    <div class="auth-intro">
        <span class="eyebrow">Acesso à plataforma</span>
        <h1>Bem-vindo ao PCCT</h1>
        <p>Entre para acessar seu painel, gerenciar materiais ou estudar com os conteúdos publicados.</p>
    </div>
    <form class="panel form-card" method="POST" action="{{ route('login.store') }}">
        @csrf
        <h2>Acessar conta</h2>
        <label for="email">E-mail</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
        <label for="password">Senha</label>
        <input id="password" type="password" name="password" autocomplete="current-password" required>
        <button type="submit" class="button primary wide">Entrar</button>
        <p class="text-small">Ainda não tem conta? <a href="{{ route('cadastro') }}">Cadastre-se como estudante</a>.</p>
    </form>
</div>
@endsection
