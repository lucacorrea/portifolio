<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Membros - Igreja de Deus Nascer de Novo</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- CSS Customizado -->
    <link rel="stylesheet" href="/css/style.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-church"></i>
            </div>
            <div>
                <div class="sidebar-title">Igreja de Deus</div>
                <div class="sidebar-subtitle">Nascer de Novo</div>
            </div>
        </div>

        <nav class="nav-menu">
            <li class="nav-item">
                <a href="#" class="nav-link active" data-pagina="dashboard">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link" data-pagina="membros">
                    <i class="fas fa-users"></i>
                    <span>Membros</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link" data-pagina="novo-membro">
                    <i class="fas fa-user-plus"></i>
                    <span>Novo Membro</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link" data-pagina="relatorios">
                    <i class="fas fa-file-pdf"></i>
                    <span>Relatórios</span>
                </a>
            </li>
        </nav>
    </aside>

    <!-- CONTEÚDO PRINCIPAL -->
    <div class="main-content">
        <!-- HEADER -->
        <header class="header">
            <h1 class="header-title">Sistema de Membros</h1>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="abrirModalCadastro()">
                    <i class="fas fa-plus"></i> Novo Membro
                </button>
            </div>
        </header>

        <!-- ALERTAS -->
        <div id="alertas" style="padding: 0 30px; padding-top: 20px;"></div>

        <!-- CONTEÚDO -->
        <div class="content">
            <!-- DASHBOARD -->
            <div id="pagina-dashboard" class="pagina">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Dashboard</h2>
                    </div>
                    <div class="card-body">
                        <!-- Cards de Estatísticas -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                            <div class="stat-card">
                                <div class="stat-label">Total de Membros</div>
                                <div class="stat-value" id="totalMembros">0</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">Últimos 30 Dias</div>
                                <div class="stat-value">--</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">Ativos</div>
                                <div class="stat-value">--</div>
                            </div>
                        </div>

                        <!-- Gráficos -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Por Tipo de Integração</h3>
                                </div>
                                <div class="chart-container">
                                    <canvas id="graficoTipo"></canvas>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Por Sexo</h3>
                                </div>
                                <div class="chart-container">
                                    <canvas id="graficoSexo"></canvas>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Por Estado Civil</h3>
                                </div>
                                <div class="chart-container">
                                    <canvas id="graficoEstadoCivil"></canvas>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Faixa Etária</h3>
                                </div>
                                <div class="chart-container">
                                    <canvas id="graficoFaixaEtaria"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MEMBROS -->
            <div id="pagina-membros" class="pagina" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Membros</h2>
                    </div>
                    <div class="card-body">
                        <!-- Barra de Busca -->
                        <div style="display: grid; grid-template-columns: 1fr 150px; gap: 10px; margin-bottom: 20px;">
                            <input type="text" id="inputBusca" class="form-control" placeholder="Buscar membro...">
                            <select id="selectFiltro" class="form-select">
                                <option value="nome">Por Nome</option>
                                <option value="cpf">Por CPF</option>
                                <option value="telefone">Por Telefone</option>
                            </select>
                        </div>

                        <!-- Tabela -->
                        <div class="table-container">
                            <table class="table" id="tabelaMembros">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>CPF</th>
                                        <th>Telefone</th>
                                        <th>Tipo</th>
                                        <th>Data Integração</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="6" class="text-center">Carregando...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginação -->
                        <div class="pagination"></div>
                    </div>
                </div>
            </div>

            <!-- NOVO MEMBRO -->
            <div id="pagina-novo-membro" class="pagina" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Novo Membro</h2>
                    </div>
                    <div class="card-body">
                        <form id="formCadastroPage" onsubmit="event.preventDefault(); salvarMembro();">
                            <!-- Dados Pessoais -->
                            <div class="form-section">
                                <div class="form-section-title">Dados Pessoais</div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Nome Completo *</label>
                                        <input type="text" id="nome_completo_page" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Data de Nascimento</label>
                                        <input type="date" id="data_nascimento_page" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>Sexo</label>
                                        <select id="sexo_page" class="form-select">
                                            <option value="">Selecione...</option>
                                            <option value="M">Masculino</option>
                                            <option value="F">Feminino</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>CPF</label>
                                        <input type="text" id="cpf_page" class="form-control" placeholder="000.000.000-00">
                                    </div>
                                    <div class="form-group">
                                        <label>RG</label>
                                        <input type="text" id="rg_page" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>Tipo Sanguíneo</label>
                                        <select id="tipo_sanguineo_page" class="form-select">
                                            <option value="">Selecione...</option>
                                            <option value="O+">O+</option>
                                            <option value="O-">O-</option>
                                            <option value="A+">A+</option>
                                            <option value="A-">A-</option>
                                            <option value="B+">B+</option>
                                            <option value="B-">B-</option>
                                            <option value="AB+">AB+</option>
                                            <option value="AB-">AB-</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Nacionalidade</label>
                                        <input type="text" id="nacionalidade_page" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>Naturalidade</label>
                                        <input type="text" id="naturalidade_page" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>Estado (UF)</label>
                                        <input type="text" id="estado_uf_page" class="form-control" maxlength="2">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Escolaridade</label>
                                        <select id="escolaridade_page" class="form-select">
                                            <option value="">Selecione...</option>
                                            <option value="Fundamental">Fundamental</option>
                                            <option value="Médio">Médio</option>
                                            <option value="Superior">Superior</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Profissão</label>
                                        <input type="text" id="profissao_page" class="form-control">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Estado Civil</label>
                                        <select id="estado_civil_page" class="form-select">
                                            <option value="">Selecione...</option>
                                            <option value="Solteiro">Solteiro</option>
                                            <option value="Casado">Casado</option>
                                            <option value="Divorciado">Divorciado</option>
                                            <option value="Viúvo">Viúvo</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Cônjuge</label>
                                        <input type="text" id="conjuge_page" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>Filhos</label>
                                        <input type="number" id="filhos_page" class="form-control" min="0">
                                    </div>
                                </div>
                            </div>

                            <!-- Endereço -->
                            <div class="form-section">
                                <div class="form-section-title">Endereço Residencial</div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Rua</label>
                                        <input type="text" id="endereco_rua_page" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>Número</label>
                                        <input type="text" id="endereco_numero_page" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>Bairro</label>
                                        <input type="text" id="endereco_bairro_page" class="form-control">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>CEP</label>
                                        <input type="text" id="endereco_cep_page" class="form-control" placeholder="00000-000">
                                    </div>
                                    <div class="form-group">
                                        <label>Cidade</label>
                                        <input type="text" id="endereco_cidade_page" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>Estado (UF)</label>
                                        <input type="text" id="endereco_uf_page" class="form-control" maxlength="2">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Telefone</label>
                                    <input type="tel" id="telefone_page" class="form-control" placeholder="(00) 0000-0000">
                                </div>
                            </div>

                            <!-- Dados Eclesiásticos -->
                            <div class="form-section">
                                <div class="form-section-title">Dados Eclesiásticos</div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Tipo de Integração</label>
                                        <select id="tipo_integracao_page" class="form-select">
                                            <option value="">Selecione...</option>
                                            <option value="Batismo">Batismo</option>
                                            <option value="Mudança">Mudança</option>
                                            <option value="Aclamação">Aclamação</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Data de Integração</label>
                                        <input type="date" id="data_integracao_page" class="form-control">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Batismo em Águas</label>
                                        <input type="date" id="batismo_aguas_page" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>Batismo no Espírito Santo</label>
                                        <input type="date" id="batismo_espirito_santo_page" class="form-control">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Procedência</label>
                                        <input type="text" id="procedencia_page" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>Congregação</label>
                                        <input type="text" id="congregacao_page" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>Área</label>
                                        <input type="text" id="area_page" class="form-control">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Núcleo</label>
                                    <input type="text" id="nucleo_page" class="form-control">
                                </div>
                            </div>

                            <!-- Foto -->
                            <div class="form-section">
                                <div class="form-section-title">Foto (3x4)</div>
                                <div class="form-group">
                                    <input type="file" id="foto_page" class="form-control" accept="image/*">
                                    <small class="text-muted">Máximo 5MB. Formatos: JPG, PNG, GIF</small>
                                </div>
                            </div>

                            <!-- Botões -->
                            <div class="card-footer">
                                <button type="reset" class="btn btn-outline">Limpar</button>
                                <button type="submit" class="btn btn-primary">Salvar Membro</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- RELATÓRIOS -->
            <div id="pagina-relatorios" class="pagina" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Relatórios</h2>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Relatórios Disponíveis</h3>
                                </div>
                                <div class="card-body">
                                    <button class="btn btn-secondary btn-block" onclick="gerarRelatorioTodos()" style="width: 100%; margin-bottom: 10px;">
                                        <i class="fas fa-file-pdf"></i> Lista de Todos os Membros
                                    </button>
                                    <button class="btn btn-secondary btn-block" onclick="gerarRelatorioEstatisticas()" style="width: 100%;">
                                        <i class="fas fa-chart-bar"></i> Relatório de Estatísticas
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DE CADASTRO -->
    <div id="modalCadastro" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Novo Membro</h2>
                <button class="modal-close" type="button">&times;</button>
            </div>
            <form id="formCadastro">
                <div class="form-section">
                    <div class="form-section-title">Dados Pessoais</div>
                    <div class="form-group">
                        <label>Nome Completo *</label>
                        <input type="text" id="nome_completo" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Data de Nascimento</label>
                            <input type="date" id="data_nascimento" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Sexo</label>
                            <select id="sexo" class="form-select">
                                <option value="">Selecione...</option>
                                <option value="M">Masculino</option>
                                <option value="F">Feminino</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>CPF</label>
                            <input type="text" id="cpf" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>RG</label>
                            <input type="text" id="rg" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nacionalidade</label>
                            <input type="text" id="nacionalidade" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Naturalidade</label>
                            <input type="text" id="naturalidade" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Estado (UF)</label>
                            <input type="text" id="estado_uf" class="form-control" maxlength="2">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tipo Sanguíneo</label>
                            <select id="tipo_sanguineo" class="form-select">
                                <option value="">Selecione...</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Escolaridade</label>
                            <select id="escolaridade" class="form-select">
                                <option value="">Selecione...</option>
                                <option value="Fundamental">Fundamental</option>
                                <option value="Médio">Médio</option>
                                <option value="Superior">Superior</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Profissão</label>
                            <input type="text" id="profissao" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">Documentos</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Título de Eleitor</label>
                            <input type="text" id="titulo_eleitor" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>CTP</label>
                            <input type="text" id="ctp" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>CDI</label>
                            <input type="text" id="cdi" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">Filiação</div>
                    <div class="form-group">
                        <label>Pai</label>
                        <input type="text" id="filiacao_pai" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Mãe</label>
                        <input type="text" id="filiacao_mae" class="form-control">
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">Estado Civil</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Estado Civil</label>
                            <select id="estado_civil" class="form-select">
                                <option value="">Selecione...</option>
                                <option value="Solteiro">Solteiro</option>
                                <option value="Casado">Casado</option>
                                <option value="Divorciado">Divorciado</option>
                                <option value="Viúvo">Viúvo</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Cônjuge</label>
                            <input type="text" id="conjuge" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Filhos</label>
                            <input type="number" id="filhos" class="form-control" min="0">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">Endereço Residencial</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Rua</label>
                            <input type="text" id="endereco_rua" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Número</label>
                            <input type="text" id="endereco_numero" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Bairro</label>
                            <input type="text" id="endereco_bairro" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>CEP</label>
                            <input type="text" id="endereco_cep" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Cidade</label>
                            <input type="text" id="endereco_cidade" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Estado (UF)</label>
                            <input type="text" id="endereco_uf" class="form-control" maxlength="2">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="tel" id="telefone" class="form-control">
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">Dados Eclesiásticos</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tipo de Integração</label>
                            <select id="tipo_integracao" class="form-select">
                                <option value="">Selecione...</option>
                                <option value="Batismo">Batismo</option>
                                <option value="Mudança">Mudança</option>
                                <option value="Aclamação">Aclamação</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Data de Integração</label>
                            <input type="date" id="data_integracao" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Batismo em Águas</label>
                            <input type="date" id="batismo_aguas" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Batismo no Espírito Santo</label>
                            <input type="date" id="batismo_espirito_santo" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Procedência</label>
                            <input type="text" id="procedencia" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Congregação</label>
                            <input type="text" id="congregacao" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Área</label>
                            <input type="text" id="area" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Núcleo</label>
                        <input type="text" id="nucleo" class="form-control">
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">Foto (3x4)</div>
                    <div class="form-group">
                        <input type="file" id="foto" class="form-control" accept="image/*">
                        <small class="text-muted">Máximo 5MB. Formatos: JPG, PNG, GIF</small>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="reset" class="btn btn-outline">Limpar</button>
                    <button type="submit" class="btn btn-primary">Salvar Membro</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DE VISUALIZAÇÃO -->
    <div id="modalVisualizacao" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Detalhes do Membro</h2>
                <button class="modal-close" type="button">&times;</button>
            </div>
            <div id="conteudoVisualizacao"></div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/js/app.js"></script>
</body>
</html>
