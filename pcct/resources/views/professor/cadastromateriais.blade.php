
   @extends('professor.layout')

   @section('navbar')

<!-- Adicionar css depois, arrumar ícones e etc -->

    <div class="text-center bg-success-subtle">  
        <h3 class="mt-2 ms-5 container">Cadastro de objetos de apredizagem</h3>
    </div>

    <div class="container">

        <form action="/professor/cadastromateriais" method="POST" class="mt-3">
        <fieldset class="border rounded p-3 mb-3 bg-light me-5 ms-5">
            @csrf
            
            <div class="row">
                <h5 class="mt-1 ms-2"><i class="bi bi-mortarboard"></i> 1.Identificação</h3>
                <div class="col-6">
                    <label class="mt-2 ms-2">Curso:</label>
                    <select name="curso" class="form-select mt-2 ms-2">
                        <option disabled selected>Selecione o curso</option>
                        <option value="Informática para Web">Informática para Web</option>
                        <option value="Administração">Administração</option>
                        <option value="Manutenção e Suporte para Internet">Manutenção e Suporte para Internet</option>
                        <option value="Agropecuária">Agropecuária</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="mt-2 ms-2">Disciplina:</label>
                    <select name="nome_disciplina" class="form-select mt-2 ms-2">
                        <option disabled selected>Selecione sua disciplina</option>
                        <option value="Matématica">
                            Matématica
                        </option>
                        <option value="Geografia">
                            Geografia
                        </option>
                        <option value="Infórmatica básica">
                            Infórmatica básica
                        </option>
                        <option value="História">
                            História
                        </option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="mt-2 ms-2">Tématica:</label>
                    <select name="tematica" class="form-select mt-2 ms-2">

                        <option disabled selected>Selecione a tématica</option>

                        <optgroup label="Matématica">
                            <option value="Logaritimo">Logaritimo</option>
                            <option value="Planocartesiano">Plano cartesiano</option>
                            <option value="Moda,modal,média,mediana">Moda, modal, média, mediana</option>
                        </optgroup>

                        <optgroup label="Geogarfia">
                            <option value="Solo">Solo</option>
                            <option value="Urbanização">Urbanização</option>
                            <option value="RevoluçãoIndustrial">Revolução Industrial</option>
                        </optgroup>

                        <optgroup label="Infórmatica Básica">
                            <option value="Word">Word</option>
                            <option value="PowerPoint">Power Point</option>
                            <option value="Excel">Excel</option>
                        </optgroup>

                        <optgroup label="Hitória">
                            <option value="Povosantigos">Povos antigos</option>
                            <option value="BrasilCôlonia">Brasil Côlonia</option>
                            <option value="oRevoluçãoFrancessa">Revolução Francessa</option>
                        </optgroup>

                    </select>
                </div>
            </div>

        </fieldset>

        <fieldset class="border rounded p-3 mb-3 bg-light me-5 ms-5">
        <div class="row">

            <h5 class="mt-1 ms-2"><i class="bi bi-file-earmark"></i> 2.Contéudo</h5>
            <div class="col-12">

            <label class="mt-2 ms-2">Título:</label>
            <input type="text" name="titulo" placeholder="Título do contéudo" class="form-control mt-2 ms-2">

            </div>
            <div class="col-12">

            <label class="mt-2 ms-2">Descrição:</label>
            <textarea name="descricao" placeholder="Breve contexto do objeto de apredizagem" class="form-control mt-2 ms-2"></textarea>

            </div>  
            <div class="col-6">

            <label class="mt-2 ms-2">BNCC:</label>
            <select name="bncc" class="form-select mt-2 ms-2">

                <option disabled selected>Código de habilidade da BNCC</option>

                <option value="EM13MAT101">
                    EM13MAT101
                </option>

                <option value="EM13LP01">
                    EM13LP01
                </option>

                <option value="EM13CNT101">
                    EM13CNT101
                </option>

                <option value="EM13CHS101">
                    EM13CHS101
                </option>

            </select>

            </div>
            <div class="col-6">

                <label class="mt-2 ms-2">Tipo de objeto:</label>
                <select name="tipo_objeto" class="form-select mt-2 ms-2">

                    <option disabled selected>Selecione seu objeto</option>

                    <option value="video">
                        Vídeo
                    </option>

                    <option value="slide">
                        Slide
                    </option>

                    <option value="documento_pdf">
                        Documento PDF
                    </option>

                    <option value="podcast_audio">
                        Podcast/Aúdio
                    </option>

                    <option value="Quiz_avaliação">
                        Quiz/Avaliação
                    </option>

                    <option value="mapa_mental">
                        Mapa Mental
                    </option>

                </select>
            </div>
        </div>
        </fieldset>

        <fieldset class="border rounded p-3 mb-3 bg-light me-5 ms-5">
        <div class="row">   
            <h5 class="mt-1 ms-2"> <i class="bi bi-card-image"></i> 3.Mídia e Acesso</h5>
            <div class="col-12">

                <label class="mt-2 ms-2">Imagem:</label>
                <input type="file" accept="image/*" name="imagem" class="form-control mt-2 ms-2">

            </div>
            <div class="col-12">

                <label class="mt-2 ms-2">Tags:</label>
                <input type="text" name="tags" class="form-control mt-2 ms-2">

            </div>
            <div class="col-12">

                <label class="mt-2 ms-2">Link:</label>
                <input type="url" name="link" class="form-control mt-2 ms-2">

            </div>
                
        </div>
        </fieldset>

            <div class="text-center">

                <button type="submit" class="btn btn-success px-5 py-2 mt-3 col-6">Cadastrar Objeto de aprendizagem</button>

            </div>

        </form>
    </div>

    @endsection
