<!-- Formulário -->
<form method="POST" enctype="multipart/form-data">
    <div class="row">
        <!-- Nome -->
        <div class="col-md-6 mb-3">
            <input type="text" class="form-control" name="nome" placeholder="Nome do produto" required>
        </div>

        <!-- Preço -->
        <div class="col-md-6 mb-3">
            <input type="text" class="form-control" name="preco" placeholder="Preço (ex: 29.90)" required>
        </div>

        <!-- Categoria -->
        <div class="col-12 mb-3">
            <input type="text" class="form-control" name="categoria" placeholder="Categoria (Flores, Buquê...)" required>
        </div>

        <!-- Descrição -->
        <div class="col-12 mb-3">
            <textarea name="descricao" class="form-control" rows="4" placeholder="Descrição do produto" required></textarea>
        </div>

        <!-- Quantidade -->
        <div class="col-md-6 mb-3">
            <input type="number" class="form-control" name="quantidade" placeholder="Quantidade em estoque" required>
        </div>

        <!-- Imagem -->
        <div class="col-md-6 mb-3">
            <input type="file" name="imagem" class="form-control" accept="image/*" required>
        </div>
    </div>

    <button type="submit" class="btn amado-btn mt-3 w-100">Cadastrar</button>
</form>
