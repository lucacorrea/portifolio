<?php
$paginaAtual = 'novo-protocolo';

$paginaTitulo = 'Novo Protocolo';
$paginaDescricao = 'Cadastre o cliente, selecione o serviço e encaminhe o atendimento para análise administrativa.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepcionista';
$textoBotaoAcao = 'Voltar ao Dashboard';
$linkBotaoAcao = '/agroForest/public/?pagina=dashboard';
$mostrarBusca = false;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Protocolo - Recepção</title>

    <link rel="stylesheet" href="../public/assets/css/recepcao.css">
    <link rel="stylesheet" href="../public/assets/css/novo-protocolo.css">
</head>
<body>
    <div class="layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="content">
            <?php include __DIR__ . '/includes/topbar.php'; ?>

            <section class="page-section">
                <div class="card-box">
                    <div class="card-header">
                        <h2>Abertura de Protocolo</h2>
                        <p>
                            Preencha os dados do cliente, informe o tipo de serviço e registre as observações iniciais do atendimento.
                        </p>
                    </div>

                    <div class="card-body">
                        <form action="salvarProtocolo.php" method="POST" enctype="multipart/form-data" class="form-protocolo">
                            <div class="form-grid">
                                <div class="form-group form-col-2">
                                    <h3 class="section-title">Dados do Cliente</h3>
                                </div>

                                <div class="form-group">
                                    <label for="nome_cliente">Nome do Cliente</label>
                                    <input type="text" id="nome_cliente" name="nome_cliente" placeholder="Digite o nome completo" required>
                                </div>

                                <div class="form-group">
                                    <label for="cpf_cnpj">CPF / CNPJ</label>
                                    <input type="text" id="cpf_cnpj" name="cpf_cnpj" placeholder="Digite o CPF ou CNPJ">
                                </div>

                                <div class="form-group">
                                    <label for="telefone">Telefone</label>
                                    <input type="text" id="telefone" name="telefone" placeholder="(00) 00000-0000" required>
                                </div>

                                <div class="form-group">
                                    <label for="email">E-mail</label>
                                    <input type="email" id="email" name="email" placeholder="cliente@email.com">
                                </div>

                                <div class="form-group form-col-2">
                                    <label for="endereco">Endereço</label>
                                    <input type="text" id="endereco" name="endereco" placeholder="Rua, número, bairro, cidade...">
                                </div>

                                <div class="form-group form-col-2">
                                    <h3 class="section-title">Dados do Atendimento</h3>
                                </div>

                                <div class="form-group">
                                    <label for="tipo_servico">Tipo de Serviço</label>
                                    <select id="tipo_servico" name="tipo_servico" required>
                                        <option value="">Selecione</option>
                                        <option value="orcamento">Orçamento</option>
                                        <option value="analise_documental">Análise Documental</option>
                                        <option value="cadastro_servico">Cadastro de Serviço</option>
                                        <option value="revisao">Revisão de Solicitação</option>
                                        <option value="urgente">Atendimento Urgente</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="prioridade">Prioridade</label>
                                    <select id="prioridade" name="prioridade" required>
                                        <option value="normal">Normal</option>
                                        <option value="media">Média</option>
                                        <option value="alta">Alta</option>
                                        <option value="urgente">Urgente</option>
                                    </select>
                                </div>

                                <div class="form-group form-col-2">
                                    <label for="descricao_servico">Descrição do Serviço</label>
                                    <textarea id="descricao_servico" name="descricao_servico" rows="5" placeholder="Descreva o que o cliente precisa..." required></textarea>
                                </div>

                                <div class="form-group form-col-2">
                                    <label for="observacoes">Observações da Recepção</label>
                                    <textarea id="observacoes" name="observacoes" rows="4" placeholder="Anotações importantes para o administrativo..."></textarea>
                                </div>

                                <div class="form-group form-col-2">
                                    <h3 class="section-title">Documentos</h3>
                                </div>

                                <div class="form-group form-col-2">
                                    <label for="anexos">Anexar Arquivos</label>
                                    <input type="file" id="anexos" name="anexos[]" multiple>
                                    <small class="field-help">Você pode anexar documentos, imagens ou comprovantes do atendimento.</small>
                                </div>
                            </div>

                            <div class="form-actions">
                                <a href="dashboard.php" class="btn-secondary">Cancelar</a>
                                <button type="submit" class="btn-primary">Salvar Protocolo</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card-box">
                    <div class="card-header">
                        <h2>Orientações para a Recepção</h2>
                        <p>
                            Antes de encaminhar para o administrativo, confirme se os dados básicos do cliente e o tipo do serviço estão corretos.
                        </p>
                    </div>

                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-card">
                                <strong>1. Conferir dados</strong>
                                <p>Verifique nome, telefone e tipo de solicitação antes de salvar.</p>
                            </div>

                            <div class="info-card">
                                <strong>2. Registrar observações</strong>
                                <p>Inclua tudo que possa ajudar o setor administrativo na elaboração do orçamento.</p>
                            </div>

                            <div class="info-card">
                                <strong>3. Validar anexos</strong>
                                <p>Se houver documento obrigatório, confira antes de encaminhar o protocolo.</p>
                            </div>

                            <div class="info-card">
                                <strong>4. Classificar prioridade</strong>
                                <p>Atendimentos urgentes devem seguir com destaque para o próximo setor.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <?php include __DIR__ . '/includes/footer.php'; ?>
        </main>
    </div>
</body>
</html>