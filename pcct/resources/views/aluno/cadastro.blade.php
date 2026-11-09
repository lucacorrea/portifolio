@extends('professor.layout')

   @section('navbar')

<!-- Adicionar css depois, arrumar ícones e etc e fazer o controller -->

    <div class=" text-center bg-success-subtle">  
        <h3 class="mt-2 ms-5 container">Cadastro do Aluno</h3>
    </div>

    <div class="container">

        <form action="/aluno/cadastro" method="POST" class="mt-3">
        <fieldset class="border rounded p-3 mb-3 bg-light me-5 ms-5">
            @csrf
            
            <div class="row">

                <label class="mt-1 mb-2"> Instituição:</label>
                    <div class="position-relative">
                        <i class="bi bi-buildings position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                            <input type="text" name="instituicao" placeholder="   Ex: IFAM - Campus Manaus Centro" class="form-control">
                    </div>
                <label class="mt-3 mb-2"> Nome completo:</label>
                    <div class="position-relative">
                        <i class="bi bi-person position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                            <input type="text" name="titulo" placeholder="   Como você deseja se indentificar" class="form-control">
                    </div>
                <label class="mt-3 mb-2">E-mail:</label>
                    <div class="position-relative">
                        <i class="bi bi-envelope position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                        <input type="email" name="email" placeholder="   Ex:nomeifam@gmail.com" class="form-control ">
                    </div>
                <label class="mt-3 mb-2"> Senha:</label>
                    <div class="position-relative">
                        <i class="bi bi-lock position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                        <input type="password" name="senha" placeholder="   Crie uma senha segura" class="form-control mb-2">
                    </div>  
            </div>
        </fieldset>
         <div class="text-center">
            <button type="submit" class="btn btn-success px-5 py-2 mt-3 col-6 container">Cadastrar Professor</button>
        </div> 
        </form>
    </div>
    @endsection