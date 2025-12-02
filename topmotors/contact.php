<section class="contact-section">
    <div class="container">

        <div class="row">
            <div class="col-12 text-center mb-4">
                <h2 class="contact-title">Cadastro de Cliente</h2>
                <p>Preencha seus dados abaixo para realizar seu cadastro na Top Motors.</p>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <form class="form-contact contact_form" action="cad_cliente.php" method="POST">

                    <div class="row">

                        <!-- Nome -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <input class="form-control" name="nome" type="text" placeholder="Nome" required>
                            </div>
                        </div>

                        <!-- Sobrenome -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <input class="form-control" name="sobrenome" type="text" placeholder="Sobrenome" required>
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <input class="form-control" name="email" type="email" placeholder="Email" required>
                            </div>
                        </div>

                        <!-- Telefone -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <input class="form-control" name="telefone" type="text" placeholder="Telefone" required>
                            </div>
                        </div>

                        <!-- Cidade -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <input class="form-control" name="cidade" type="text" placeholder="Cidade" required>
                            </div>
                        </div>

                        <!-- Endereço -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <input class="form-control" name="endereco" type="text" placeholder="Endereço completo" required>
                            </div>
                        </div>

                        <!-- CEP -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <input class="form-control" name="cep" type="text" placeholder="CEP" required>
                            </div>
                        </div>

                        <!-- Número da residência -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <input class="form-control" name="numero" type="text" placeholder="Número" required>
                            </div>
                        </div>

                    </div>

                    <div class="form-group mt-3 text-center">
                        <button type="submit" class="button button-contactForm boxed-btn">
                            Finalizar Cadastro
                        </button>
                    </div>

                </form>
            </div>
        </div>

    </div>
</section>
