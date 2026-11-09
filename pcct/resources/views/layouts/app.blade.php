<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'PCCT') · Ensino Médio</title>
    <link rel="stylesheet" href="{{ asset('css/pcct.css') }}">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="{{ route('catalogo') }}" aria-label="PCCT: página inicial">
            <span class="brand-icon">P</span>
            <span>PCCT <small>Ensino Médio</small></span>
        </a>
        <nav class="navigation" aria-label="Navegação principal">
            <a href="{{ route('catalogo') }}" @class(['active' => request()->routeIs('catalogo')])>Biblioteca</a>
            @auth
                <a href="{{ route('painel') }}" @class(['active' => request()->routeIs('painel')])>Painel</a>
                @if(auth()->user()->role === 'professor')
                    <a href="{{ route('professor.materiais.index') }}">Meus materiais</a>
                @elseif(auth()->user()->role === 'admin')
                    <a href="{{ route('admin.index') }}">Administração</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="nav-logout" type="submit">Sair</button>
                </form>
            @else
                <a href="{{ route('login') }}">Entrar</a>
                <a class="nav-cta" href="{{ route('cadastro') }}">Sou estudante</a>
            @endauth
        </nav>
    </div>
</header>

<main class="container main-content">
    @if(session('success'))
        <div class="notice success" role="status">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="notice error" role="alert">
            <strong>Verifique as informações:</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @yield('content')
</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <span>PCCT · Repositório de Objetos de Aprendizagem</span>
        <span>Conteúdos para o Ensino Médio</span>
    </div>
</footer>
</body>
</html>
